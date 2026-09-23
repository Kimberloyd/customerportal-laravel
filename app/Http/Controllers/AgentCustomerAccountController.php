<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerAccess;
use App\Support\UserAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AgentCustomerAccountController extends Controller
{
    public function create(): Response
    {
        $agent = $this->agent();
        $employeeIds = CustomerAccess::teamEmployeeIds($agent);

        return Inertia::render('CustomerAccounts/Create', [
            'customers' => CustomerAccess::applyToClaimableCustomers(Customer::query(), $agent)
                ->where('is_active', true)->whereNull('user_id')
                ->orderBy('company_name')->get(['id', 'company_name', 'assigned_employee_id']),
            'assignedCustomers' => Customer::query()
                ->whereIn('assigned_employee_id', $employeeIds)
                ->with('user:id,full_name,email,phone,is_active')
                ->orderBy('company_name')
                ->get(['id', 'company_name', 'customer_code', 'channel', 'user_id', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        $agent = $this->agent();
        $values = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'customer_id' => ['required', 'integer'],
        ], [
            'customer_id.required' => 'Select the customer this account belongs to.',
            'password.confirmed' => 'Enter the same password again.',
        ]);

        $employeeIds = CustomerAccess::teamEmployeeIds($agent);

        DB::transaction(function () use ($values, $agent, $employeeIds, $request) {
            $customer = Customer::lockForUpdate()->find($values['customer_id']);
            if (! $customer || ! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => 'Choose an active customer from the list.']);
            }
            if ($customer->user_id) {
                throw ValidationException::withMessages(['customer_id' => 'This customer already has a portal account.']);
            }
            if ($customer->assigned_employee_id && ! in_array($customer->assigned_employee_id, $employeeIds, true)) {
                throw ValidationException::withMessages(['customer_id' => 'This customer is assigned to an agent outside your team.']);
            }

            $user = User::create([
                'full_name' => trim($values['full_name']),
                'email' => strtolower(trim($values['email'])),
                'phone' => filled($values['phone']) ? trim($values['phone']) : null,
                'role' => User::ROLE_CUSTOMER, 'is_active' => true,
                'password_hash' => Hash::make($values['password']), 'session_version' => 0,
            ]);
            // A teammate's customer keeps its existing assignment -- only an
            // unassigned customer picks up the agent creating the account.
            $customer->update(['user_id' => $user->id, 'assigned_employee_id' => $customer->assigned_employee_id ?? $agent->id]);
            UserAudit::record($user, 'created', "customer account created by agent {$agent->id}", $request);
        });

        return redirect()->route('customer-accounts.create')->with('success', 'Customer account created and assigned to you.');
    }

    private function agent(): User
    {
        $user = Auth::user();
        abort_unless($user?->role === User::ROLE_AGENT, 403);

        return $user;
    }
}
