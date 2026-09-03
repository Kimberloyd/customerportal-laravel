<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\LegacyPasswordHasher;
use App\Support\AdminUserListing;
use App\Support\UserAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports the user-management routes of app/admin/admin_routes.py
 * (user_list, user_create, user_edit, user_toggle, user_delete). The
 * /admin/ summary dashboard route is a separate feature, not built
 * this phase.
 */
class UserController extends Controller
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly AdminUserListing $userListing,
        private readonly AccountDeletionService $accountDeletion,
    ) {}

    public function index(Request $request): Response
    {
        $this->requireAdmin();

        return Inertia::render('Admin/Users/Index', [
            ...$this->userListing->get($request->query()),
            'accountForm' => [
                'customers' => $this->customerOptions(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireAdmin();

        return Inertia::render('Admin/Users/Create', [
            'customers' => $this->customerOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();

        $values = $this->validateUserForm($request);

        DB::transaction(function () use ($values, $request) {
            $user = User::create([
                'full_name' => $values['full_name'],
                'email' => $values['email'],
                'phone' => $values['phone'],
                'role' => $values['role'],
                'is_active' => true,
                'password_hash' => Hash::make($values['password']),
                'session_version' => 0,
            ]);

            if ($values['customer_id']) {
                Customer::where('id', $values['customer_id'])->update(['user_id' => $user->id]);
            }

            UserAudit::record($user, 'created', "email={$user->email}, role={$user->role}", $request);
        });

        return redirect()->route('admin.dashboard', ['tab' => 'accounts'])
            ->with('success', 'Account created.');
    }

    public function update(Request $request, User $user)
    {
        $this->requireAdmin();

        $values = $this->validateUserForm($request, $user);

        $previousRole = $user->role;
        $previousActive = $user->is_active;
        $changes = [];
        $isSelf = $user->id === Auth::id();

        $user->full_name = $values['full_name'];
        $user->email = $values['email'];
        $user->phone = $values['phone'];

        if (! $isSelf) {
            $user->role = $values['role'];
        }

        if ($isSelf) {
            $user->is_active = true;
        } else {
            $user->is_active = $request->input('is_active') === '1';
        }

        if ($values['password']) {
            $user->password_hash = Hash::make($values['password']);
            // Invalidate any other active session for this account -- a
            // changed password should immediately sign out other devices,
            // not just leave them logged in on the old credential.
            $user->session_version = ($user->session_version ?? 0) + 1;
            if ($isSelf) {
                // Editing your own password shouldn't also sign out the
                // tab you're doing it from -- only other devices/sessions.
                $request->session()->put('session_version', $user->session_version);
            }
            $changes[] = 'password reset';
        }
        if ($user->role !== $previousRole) {
            // A role change can add or remove customer-scoped data access.
            // End the target's existing sessions so their next request
            // reloads authorization from the updated account record.
            if (! $values['password']) {
                $user->session_version = ($user->session_version ?? 0) + 1;
            }
            $changes[] = "role {$previousRole} -> {$user->role}";
        }
        if ($user->is_active !== $previousActive) {
            $changes[] = $user->is_active ? 'activated' : 'deactivated';
        }

        DB::transaction(function () use ($user, $values, $changes, $request) {
            $user->save();

            $linkedCustomers = Customer::where('user_id', $user->id)->get();
            if ($user->role === 'customer' && $values['customer_id']) {
                foreach ($linkedCustomers as $linkedCustomer) {
                    if ($linkedCustomer->id !== $values['customer_id']) {
                        $linkedCustomer->update(['user_id' => null]);
                    }
                }
                Customer::where('id', $values['customer_id'])->update(['user_id' => $user->id]);
            } else {
                foreach ($linkedCustomers as $linkedCustomer) {
                    $linkedCustomer->update(['user_id' => null]);
                }
            }

            UserAudit::record($user, 'updated', $changes ? implode(', ', $changes) : 'profile details updated', $request);
        });

        return redirect()->route('admin.dashboard', ['tab' => 'accounts'])
            ->with('success', 'Account updated.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->requireAdmin();

        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        $this->assertSecurePassword($password, $passwordConfirmation, $user->password_hash);

        $isSelf = $user->id === Auth::id();

        DB::transaction(function () use ($user, $password, $isSelf, $request) {
            $user->password_hash = Hash::make($password);
            // Same rationale as the password branch in update(): force other
            // sessions to re-authenticate, but don't sign the admin out of
            // the tab they're resetting their own password from.
            $user->session_version = ($user->session_version ?? 0) + 1;
            if ($isSelf) {
                $request->session()->put('session_version', $user->session_version);
            }
            $user->save();

            UserAudit::record($user, 'password reset', "email={$user->email}", $request);
        });

        return back()->with('success', "{$user->full_name}'s password was reset.");
    }

    public function toggleActive(Request $request, User $user)
    {
        $this->requireAdmin();

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your current account.');
        }

        DB::transaction(function () use ($user, $request) {
            $user->is_active = ! $user->is_active;
            $user->save();
            $state = $user->is_active ? 'activated' : 'deactivated';
            UserAudit::record($user, $state, "email={$user->email}", $request);
        });

        return back()->with('success', "{$user->full_name} was ".($user->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->requireAdmin();

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your current account.');
        }

        if (
            $user->role === 'admin'
            && $user->is_active
            && User::where('role', 'admin')->where('is_active', true)->count() <= 1
        ) {
            return back()->with('error', 'Create or activate another administrator before deleting this account.');
        }

        $this->accountDeletion->schedule($user, $request);

        return redirect()->route('admin.dashboard', ['tab' => 'accounts'])
            ->with('success', 'Account access was removed. Permanent deletion is scheduled in '.config('account-deletion.retention_days').' days.');
    }

    public function restore(Request $request, int $user)
    {
        $this->requireAdmin();

        $restored = $this->accountDeletion->restore($user, $request);

        return redirect()->route('admin.dashboard', ['tab' => 'accounts'])
            ->with('success', "{$restored->full_name}'s account was restored.");
    }

    public function exportData(Request $request, int $user)
    {
        $this->requireAdmin();

        $account = User::withTrashed()->findOrFail($user);
        $report = $this->accountDeletion->export($account, $request);
        $filename = 'account-data-'.$account->id.'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function eraseNow(Request $request, int $user)
    {
        $this->requireAdmin();

        $account = User::withTrashed()->findOrFail($user);

        if ($account->id === Auth::id()) {
            return back()->with('error', 'You cannot erase your current account.');
        }

        if ($request->string('confirmation')->trim()->toString() !== $account->full_name) {
            throw ValidationException::withMessages([
                'confirmation' => 'Enter the account name exactly as shown to confirm permanent erasure.',
            ]);
        }

        if (
            $account->role === 'admin'
            && $account->is_active
            && User::where('role', 'admin')->where('is_active', true)->count() <= 1
        ) {
            return back()->with('error', 'Create or activate another administrator before erasing this account.');
        }

        $this->accountDeletion->eraseNow($account, $request);

        return redirect()->route('admin.dashboard', ['tab' => 'accounts'])
            ->with('success', 'The account and its personal data were permanently erased. Retained business records no longer identify the account holder.');
    }

    private function requireAdmin(): void
    {
        abort_if(Auth::user()->role !== 'admin', 403);
    }

    /**
     * Shared by resetPassword() and validateUserForm(). Beyond the minimum
     * length, this rejects reuse of the account's current password.
     */
    private function assertSecurePassword(
        string $password,
        string $confirmation,
        ?string $currentPasswordHash = null,
    ): void {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw ValidationException::withMessages([
                'password' => 'Use at least '.self::MIN_PASSWORD_LENGTH.' characters.',
            ]);
        }
        if ($password !== $confirmation) {
            throw ValidationException::withMessages(['password_confirmation' => 'Enter the same password again.']);
        }

        // Hash::check() throws for anything that isn't bcrypt -- accounts
        // still on a hash from the Flask app (see LegacyPasswordHasher) fail
        // this check the same way they fail Auth::attempt() otherwise. Those
        // get rehashed to bcrypt on their next real login regardless, so this
        // secondary check simply doesn't apply to them yet rather than
        // paying LegacyPasswordHasher's ~15s scrypt cost for a nice-to-have.
        if (
            $currentPasswordHash
            && ! LegacyPasswordHasher::isLegacyHash($currentPasswordHash)
            && Hash::check($password, $currentPasswordHash)
        ) {
            throw ValidationException::withMessages([
                'password' => 'Choose a password different from the current one.',
            ]);
        }

    }

    private function customerOptions()
    {
        return Customer::where('is_active', true)
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'user_id']);
    }

    /**
     * @return array{full_name: string, email: string, phone: ?string, password: string, role: string, customer_id: ?int}
     */
    private function validateUserForm(Request $request, ?User $user = null): array
    {
        $fullName = trim((string) $request->input('full_name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $phone = trim((string) $request->input('phone', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');
        $selectedRole = strtolower(trim((string) $request->input('role', $user?->role ?? 'employee')));
        $selectedCustomerId = $request->input('customer_id') ? (int) $request->input('customer_id') : null;

        // Customer accounts are created only through the employee customer-account
        // flow, which also assigns the customer to its responsible employee.
        // An existing customer account remains editable so an admin can maintain it
        // or move it to a staff role, but no other account can be converted into one.
        $allowedRoles = ['employee', 'admin'];
        if ($user?->role === 'customer') {
            $allowedRoles[] = 'customer';
        }

        if ($fullName === '') {
            throw ValidationException::withMessages(['full_name' => 'Enter a full name.']);
        }
        if ($email === '') {
            throw ValidationException::withMessages(['email' => 'Enter an email address.']);
        }
        if (! in_array($selectedRole, $allowedRoles, true)) {
            throw ValidationException::withMessages(['role' => 'Choose an account type from the list.']);
        }

        $hasPasswordInput = $password !== '' || $passwordConfirmation !== '';
        if (! $user && $password === '') {
            throw ValidationException::withMessages(['password' => 'Enter a password for this account.']);
        }
        if ($hasPasswordInput) {
            $this->assertSecurePassword(
                $password,
                $passwordConfirmation,
                $user?->password_hash,
            );
        }

        if ($selectedRole === 'customer') {
            if (! $selectedCustomerId) {
                throw ValidationException::withMessages(['customer_id' => 'Select a customer to link to this account.']);
            }
            $customer = Customer::find($selectedCustomerId);
            if (! $customer || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'Choose an active customer from the list.']);
            }
            if ($customer->user_id && (! $user || $customer->user_id !== $user->id)) {
                throw ValidationException::withMessages(['customer_id' => 'This customer is already linked to another account. Choose a different customer or unlink the existing account first.']);
            }
        } else {
            $selectedCustomerId = null;
        }

        // Duplicate-email check done proactively (same pattern as
        // Products' SKU uniqueness in Phase 5) rather than reactively
        // catching a DB IntegrityError.
        $emailTaken = User::withTrashed()->where('email', $email)->when($user, fn ($q) => $q->where('id', '!=', $user->id))->exists();
        if ($emailTaken) {
            throw ValidationException::withMessages(['email' => 'An account with that email already exists. Use a different email address.']);
        }

        return [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'password' => $password,
            'role' => $selectedRole,
            'customer_id' => $selectedCustomerId,
        ];
    }
}
