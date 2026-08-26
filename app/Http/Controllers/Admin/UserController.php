<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
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
    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(private readonly AdminUserListing $userListing) {}

    public function index(Request $request): Response
    {
        $this->requireAdmin();

        return Inertia::render('Admin/Users/Index', $this->userListing->get($request->query()));
    }

    public function create(Request $request): Response
    {
        $this->requireAdmin();

        $allowAdminCreation = $this->allowAdminCreationFromRequest($request);

        return Inertia::render('Admin/Users/Create', [
            'allowAdminCreation' => $allowAdminCreation,
            'customers' => $this->customerOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();

        $allowAdminCreation = $this->allowAdminCreationFromRequest($request);
        $values = $this->validateUserForm($request, null, $allowAdminCreation);

        DB::transaction(function () use ($values, $request) {
            $user = User::create([
                'full_name' => $values['full_name'],
                'email' => $values['email'],
                'role' => $values['role'],
                'is_active' => $request->input('is_active') === '1',
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

    public function edit(Request $request, User $user): Response
    {
        $this->requireAdmin();

        $allowAdminCreation = $this->allowAdminCreationFromRequest($request) || $user->role === 'admin';

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            'allowAdminCreation' => $allowAdminCreation,
            'customers' => $this->customerOptions(),
            'selectedCustomerId' => Customer::where('user_id', $user->id)->value('id'),
            'isSelf' => $user->id === Auth::id(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->requireAdmin();

        $allowAdminCreation = $this->allowAdminCreationFromRequest($request) || $user->role === 'admin';
        $values = $this->validateUserForm($request, $user, $allowAdminCreation);

        $previousRole = $user->role;
        $previousActive = $user->is_active;
        $changes = [];
        $isSelf = $user->id === Auth::id();

        $user->full_name = $values['full_name'];
        $user->email = $values['email'];

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

        DB::transaction(function () use ($user, $request) {
            Customer::where('user_id', $user->id)->update(['user_id' => null]);
            UserAudit::record($user, 'deleted', "email={$user->email}, role={$user->role}", $request);
            $user->delete();
        });

        return redirect()->route('admin.dashboard', ['tab' => 'accounts'])
            ->with('success', 'Account deleted.');
    }

    private function requireAdmin(): void
    {
        abort_if(Auth::user()->role !== 'admin', 403);
    }

    private function allowAdminCreationFromRequest(Request $request): bool
    {
        if (Auth::user()->role !== 'admin') {
            return false;
        }

        return $request->input('allow_admin') === '1';
    }

    private function customerOptions()
    {
        return Customer::where('is_active', true)
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'user_id']);
    }

    /**
     * @return array{full_name: string, email: string, password: string, role: string, customer_id: ?int}
     */
    private function validateUserForm(Request $request, ?User $user, bool $allowAdminCreation): array
    {
        $fullName = trim((string) $request->input('full_name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');
        $selectedRole = strtolower(trim((string) $request->input('role', $user?->role ?? 'employee')));
        $selectedCustomerId = $request->input('customer_id') ? (int) $request->input('customer_id') : null;

        $allowedRoles = ['employee', 'customer'];
        if ($allowAdminCreation) {
            $allowedRoles[] = 'admin';
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
            if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
                throw ValidationException::withMessages([
                    'password' => 'Use at least '.self::MIN_PASSWORD_LENGTH.' characters.',
                ]);
            }
            if ($password !== $passwordConfirmation) {
                throw ValidationException::withMessages(['password_confirmation' => 'Enter the same password again.']);
            }
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
        $emailTaken = User::where('email', $email)->when($user, fn ($q) => $q->where('id', '!=', $user->id))->exists();
        if ($emailTaken) {
            throw ValidationException::withMessages(['email' => 'An account with that email already exists. Use a different email address.']);
        }

        return [
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
            'role' => $selectedRole,
            'customer_id' => $selectedCustomerId,
        ];
    }
}
