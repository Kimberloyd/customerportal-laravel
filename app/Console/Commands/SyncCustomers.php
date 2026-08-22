<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\InventoryApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('customers:sync')]
#[Description('Upsert local customers from the inventoryapp customers API')]
class SyncCustomers extends Command
{
    public function handle(InventoryApiClient $inventory): int
    {
        $rows = array_map(InventoryApiClient::mapCustomer(...), $inventory->allCustomers());

        $created = 0;
        $claimed = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $customer = Customer::where('external_id', $row['external_id'])->first();

            if (! $customer) {
                $customer = Customer::whereNull('external_id')
                    ->whereRaw('LOWER(company_name) = ?', [strtolower($row['company_name'])])
                    ->first();

                if ($customer) {
                    $claimed++;
                }
            }

            if ($customer) {
                $customer->update([
                    'external_id' => $row['external_id'],
                    'company_name' => $row['company_name'],
                    'channel' => $row['channel'],
                ]);
                $updated++;

                continue;
            }

            $customer = Customer::create([
                'external_id' => $row['external_id'],
                'company_name' => $row['company_name'],
                'channel' => $row['channel'],
                'is_active' => true,
            ]);
            $customer->update(['customer_code' => $this->buildCustomerCode($customer->company_name, $customer->id)]);
            $created++;
        }

        $this->info("Synced {$updated} customers ({$claimed} newly claimed by name match), created {$created} new.");

        return self::SUCCESS;
    }

    /**
     * Ports CustomerController::buildCustomerCode() exactly: first letter
     * of each alphanumeric "word" in the company name, uppercased, capped
     * at 8 characters, falling back to "CUST" if the name yields no
     * tokens at all (e.g. all-punctuation).
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
