<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ports app/customers/customer_routes.py. User-linking (creating
 * portal credentials for a customer) stays out of scope here -- in
 * Flask that's entirely a /admin/users concern, never done from this
 * screen -- and is deferred to a future Admin phase. Adds one
 * deliberate improvement beyond Flask, approved for this phase: a
 * deactivate/reactivate toggle, same reasoning as Phase 5's Products
 * addition.
 */
class CustomerController extends Controller
{
    private const CHANNEL_OPTIONS = ['HOSPITAL', 'PHARMACY', 'DISPENSING', 'OTHERS'];

    public function index(Request $request): Response
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', 'active'))) ?: 'active';
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $query = Customer::query();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $pattern = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($pattern) {
                $q->whereRaw('LOWER(customer_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(channel, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(contact_person) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(address) LIKE ?', [$pattern]);
            });
        }

        $customers = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        abort_if(Auth::user()->role === 'customer', 403);

        return Inertia::render('Customers/Create', [
            'channelOptions' => self::CHANNEL_OPTIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $attributes = $this->validateCustomerForm($request, requireCompanyName: true);

        DB::transaction(function () use ($attributes, $request) {
            $customer = Customer::create($attributes);
            $customer->update(['customer_code' => $this->buildCustomerCode($customer->company_name, $customer->id)]);
            CustomerAudit::record($customer, 'created', $request);
        });

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function edit(Customer $customer): Response
    {
        abort_if(Auth::user()->role === 'customer', 403);

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
            'channelOptions' => self::CHANNEL_OPTIONS,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        $attributes = $this->validateCustomerForm($request, requireCompanyName: false);

        DB::transaction(function () use ($customer, $attributes, $request) {
            $customer->update($attributes);
            CustomerAudit::record($customer, 'updated', $request);
        });

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        if ($customer->user_id) {
            $linkedUser = User::find($customer->user_id);
            if ($linkedUser !== null) {
                return back()->with('error', 'Delete the linked customer credentials before deleting this customer.');
            }
            // Repair a historical dangling link before continuing.
            $customer->user_id = null;
            $customer->save();
        }

        if ($customer->orders()->exists() || $customer->messages()->exists()) {
            return back()->with('error', 'Customers with orders or message history cannot be deleted.');
        }

        DB::transaction(function () use ($customer, $request) {
            CustomerAudit::record($customer, 'deleted', $request);
            $customer->delete();
        });

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }

    public function toggleActive(Request $request, Customer $customer)
    {
        abort_if(Auth::user()->role === 'customer', 403);

        DB::transaction(function () use ($customer, $request) {
            $customer->is_active = ! $customer->is_active;
            $customer->save();
            CustomerAudit::record($customer, $customer->is_active ? 'reactivated' : 'deactivated', $request);
        });

        return back()->with('success', $customer->is_active ? 'Customer reactivated.' : 'Customer deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCustomerForm(Request $request, bool $requireCompanyName): array
    {
        $companyName = trim((string) $request->input('company_name', ''));
        if ($requireCompanyName && $companyName === '') {
            throw ValidationException::withMessages([
                'company_name' => 'Company name is required.',
            ]);
        }

        $channel = $this->normalizeChannel($request->input('channel'));
        if (! in_array($channel, self::CHANNEL_OPTIONS, true)) {
            throw ValidationException::withMessages([
                'channel' => 'Please select a valid channel.',
            ]);
        }

        return [
            'company_name' => $companyName,
            'channel' => $channel,
            'contact_person' => $this->nullableTrim($request->input('contact_person')),
            'email' => $this->nullableTrim($request->input('email')),
            'phone' => $this->nullableTrim($request->input('phone')),
            'address' => $this->nullableTrim($request->input('address')),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function normalizeChannel(?string $value): string
    {
        $channel = strtoupper(trim((string) $value));

        return $channel === '' ? 'OTHERS' : $channel;
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Ports build_customer_code() exactly: first letter of each
     * alphanumeric "word" in the company name, uppercased, capped at 8
     * characters, falling back to "CUST" if the name yields no tokens
     * at all (e.g. all-punctuation).
     */
    private function buildCustomerCode(string $companyName, int $customerId): string
    {
        preg_match_all('/[A-Za-z0-9]+/', $companyName, $matches);
        $acronym = '';
        foreach ($matches[0] as $token) {
            $acronym .= strtoupper($token[0]);
        }
        $acronym = substr($acronym, 0, 8) ?: 'CUST';

        return "{$acronym}-{$customerId}";
    }
}
