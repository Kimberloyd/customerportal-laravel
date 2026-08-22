<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Browsing customers now happens inline in the Admin dashboard's
 * Customers tab (see Admin\DashboardController) rather than a
 * standalone page -- this controller only keeps the actions that
 * still need their own endpoints.
 */
class CustomerController extends Controller
{
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

        return redirect()->route('admin.dashboard', ['tab' => 'customers'])->with('success', 'Customer deleted successfully.');
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
}
