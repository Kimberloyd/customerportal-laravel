<?php

namespace App\Services;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\DataSubjectRequest;
use App\Models\LoginAttempt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAudit;
use App\Models\User;
use App\Support\UserAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class AccountDeletionService
{
    public function schedule(User $user, Request $httpRequest): DataSubjectRequest
    {
        return DB::transaction(function () use ($user, $httpRequest) {
            $activeAdminIds = $this->lockActiveAdministrators();
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->guardAccountRemoval($locked, $httpRequest, $activeAdminIds);
            $purgeAfter = now()->addDays(max(1, (int) config('account-deletion.retention_days')));

            $locked->forceFill([
                'is_active' => false,
                'session_version' => ($locked->session_version ?? 0) + 1,
                'deactivated_at' => now(),
                'purge_after' => $purgeAfter,
                'deletion_reason' => 'administrator_requested',
            ])->save();

            $this->deleteAuthenticationArtifacts($locked);

            UserAudit::record(
                $locked,
                'deletion scheduled',
                "role={$locked->role}, purge_after={$purgeAfter->toIso8601String()}",
                $httpRequest,
            );

            $dataRequest = DataSubjectRequest::create([
                'subject_user_id' => $locked->id,
                'subject_reference' => $this->subjectReference($locked->id),
                'requested_by_user_id' => $httpRequest->user()?->id,
                'request_type' => 'routine_deletion',
                'status' => 'scheduled',
                'requested_at' => now(),
                'deadline_at' => $purgeAfter,
                'results' => ['retention_days' => max(1, (int) config('account-deletion.retention_days'))],
            ]);

            $locked->delete();

            return $dataRequest;
        });
    }

    public function restore(int $userId, Request $httpRequest): User
    {
        return DB::transaction(function () use ($userId, $httpRequest) {
            $user = User::onlyTrashed()->lockForUpdate()->findOrFail($userId);

            abort_if($user->purge_after?->isPast(), 409, 'This account has reached its deletion date and can no longer be restored.');

            $user->forceFill([
                'is_active' => true,
                'deactivated_at' => null,
                'purge_after' => null,
                'deletion_reason' => null,
            ]);
            $user->restore();

            DataSubjectRequest::query()
                ->where('subject_user_id', $user->id)
                ->where('request_type', 'routine_deletion')
                ->where('status', 'scheduled')
                ->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'results' => json_encode(['restored' => true]),
                ]);

            UserAudit::record($user, 'deletion cancelled', 'Account restored during retention period.', $httpRequest);

            return $user;
        });
    }

    /**
     * Permanently removes accounts whose configured retention period ended.
     */
    public function purgeDue(): int
    {
        $count = 0;

        User::onlyTrashed()
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $this->purge($user);
                    $count++;
                }
            });

        return $count;
    }

    public function eraseNow(User $user, Request $httpRequest): DataSubjectRequest
    {
        $dataRequest = DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_reference' => $this->subjectReference($user->id),
            'requested_by_user_id' => $httpRequest->user()?->id,
            'request_type' => 'erasure',
            'status' => 'processing',
            'requested_at' => now(),
            'deadline_at' => now()->addDays(max(1, (int) config('account-deletion.erasure_deadline_days'))),
        ]);

        try {
            DB::transaction(function () use ($user, $httpRequest, $dataRequest) {
                $activeAdminIds = $this->lockActiveAdministrators();
                $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
                $this->guardAccountRemoval($locked, $httpRequest, $activeAdminIds);
                $this->purge($locked, $dataRequest);
            });
        } catch (Throwable $exception) {
            $dataRequest->forceFill([
                'status' => 'failed',
                'failure_reason' => 'Erasure could not be completed. Review the application logs and retry.',
            ])->save();

            throw $exception;
        }

        return $dataRequest->refresh();
    }

    /**
     * Builds a portable report without exposing password hashes, reset tokens,
     * session payloads, or public conversation bearer-token hashes.
     *
     * @return array<string, mixed>
     */
    public function export(User $user, Request $httpRequest): array
    {
        $customerIds = Customer::where('user_id', $user->id)->pluck('id');
        $orders = PurchaseOrder::whereIn('customer_id', $customerIds)
            ->with(['items', 'auditLogs'])
            ->get();

        $conversationFields = [
            'id', 'customer_id', 'assigned_user_id', 'subject', 'body', 'parent_id',
            'sender_type', 'is_read', 'status', 'channel', 'external_sender_id',
            'external_sender_name', 'external_page_id', 'external_message_id',
            'created_at', 'updated_at', 'sent_at',
        ];
        $conversations = CustomerMessage::query()
            ->where(function ($query) use ($user, $customerIds) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhereIn('customer_id', $customerIds);
            })
            ->with('replyAttempts:id,customer_message_id,client_key,attempted_at')
            ->get($conversationFields);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
                'deactivated_at' => $user->deactivated_at?->toIso8601String(),
                'purge_after' => $user->purge_after?->toIso8601String(),
            ],
            'linked_customers' => Customer::whereIn('id', $customerIds)->get()->toArray(),
            'purchase_orders' => $orders->toArray(),
            'conversations' => $conversations->toArray(),
            'admin_audits' => AdminAudit::where(function ($query) use ($user) {
                $query->where('actor_user_id', $user->id)
                    ->orWhere(function ($entityQuery) use ($user) {
                        $entityQuery->where('entity_type', 'user')->where('entity_id', $user->id);
                    });
            })->get()->toArray(),
            'order_audits_as_actor' => PurchaseOrderAudit::where('actor_user_id', $user->id)->get()->toArray(),
            'login_attempts' => LoginAttempt::where('email', $user->email)->get()->toArray(),
            'security_artifact_counts' => [
                'sessions' => $this->countWhereIfTableExists('sessions', 'user_id', $user->id),
                'password_reset_tokens' => $this->countWhereIfTableExists(
                    'password_reset_tokens',
                    'email',
                    $user->email,
                ),
            ],
        ];

        DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_reference' => $this->subjectReference($user->id),
            'requested_by_user_id' => $httpRequest->user()?->id,
            'request_type' => 'data_export',
            'status' => 'completed',
            'requested_at' => now(),
            'deadline_at' => now()->addDays(max(1, (int) config('account-deletion.erasure_deadline_days'))),
            'completed_at' => now(),
            'results' => ['format' => 'json'],
        ]);

        return $report;
    }

    private function purge(User $user, ?DataSubjectRequest $dataRequest = null): void
    {
        DB::transaction(function () use ($user, $dataRequest) {
            $locked = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            $email = $locked->email;

            $counts = [
                'sessions_deleted' => $this->deleteWhereIfTableExists('sessions', 'user_id', $locked->id),
                'reset_tokens_deleted' => $this->deleteWhereIfTableExists(
                    'password_reset_tokens',
                    'email',
                    $email,
                ),
                'login_attempts_deleted' => LoginAttempt::where('email', $email)->delete(),
                'customers_detached' => Customer::where('user_id', $locked->id)->update(['user_id' => null]),
                'conversations_detached' => CustomerMessage::where('assigned_user_id', $locked->id)
                    ->update(['assigned_user_id' => null]),
            ];

            PurchaseOrderAudit::where('actor_user_id', $locked->id)
                ->update(['actor_user_id' => null, 'ip_address' => null]);

            AdminAudit::where('actor_user_id', $locked->id)
                ->update(['actor_user_id' => null, 'ip_address' => null]);

            AdminAudit::where('entity_type', 'user')->where('entity_id', $locked->id)
                ->update([
                    'entity_id' => null,
                    'details' => 'Personal data removed after account erasure.',
                    'ip_address' => null,
                ]);

            $requestIds = DataSubjectRequest::where('subject_user_id', $locked->id)
                ->whereIn('status', ['scheduled', 'processing'])
                ->pluck('id');
            if ($dataRequest && ! $requestIds->contains($dataRequest->id)) {
                $requestIds->push($dataRequest->id);
            }

            $this->deleteManagedProfileImage($locked->profile_image);
            $locked->forceDelete();

            DataSubjectRequest::whereKey($requestIds)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'results' => json_encode($counts),
                'failure_reason' => null,
            ]);

            AdminAudit::create([
                'entity_type' => 'data_subject_request',
                'entity_id' => null,
                'action' => 'account erasure completed',
                'details' => 'Erasure completed for subject reference '.$this->subjectReference($locked->id).'.',
                'actor_user_id' => $dataRequest?->requested_by_user_id,
                'actor_role' => null,
                'ip_address' => null,
                'request_id' => $dataRequest?->id ?? (string) Str::uuid(),
                'created_at' => now(),
            ]);
        });
    }

    private function deleteAuthenticationArtifacts(User $user): void
    {
        $this->deleteWhereIfTableExists('sessions', 'user_id', $user->id);
        $this->deleteWhereIfTableExists('password_reset_tokens', 'email', $user->email);
    }

    private function deleteWhereIfTableExists(string $table, string $column, mixed $value): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->where($column, $value)->delete();
    }

    private function countWhereIfTableExists(string $table, string $column, mixed $value): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->where($column, $value)->count();
    }

    /** @return array<int, int> */
    private function lockActiveAdministrators(): array
    {
        return User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param array<int, int> $activeAdminIds */
    private function guardAccountRemoval(User $user, Request $httpRequest, array $activeAdminIds): void
    {
        abort_if($user->id === $httpRequest->user()?->id, 409, 'You cannot remove your current account.');
        abort_if(
            $user->role === 'admin' && $user->is_active && count($activeAdminIds) <= 1,
            409,
            'Create or activate another administrator before removing this account.',
        );
    }

    private function deleteManagedProfileImage(?string $path): void
    {
        if (! $path || Str::contains($path, ['://', '..', '\\'])) {
            return;
        }

        Storage::disk('public')->delete(ltrim($path, '/'));
    }

    private function subjectReference(int $userId): string
    {
        return hash_hmac('sha256', 'user:'.$userId, (string) config('app.key'));
    }
}
