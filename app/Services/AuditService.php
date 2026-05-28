<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TransactionLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /** Fields never written to the audit log, regardless of model. */
    private const GLOBAL_HIDDEN = ['password', 'remember_token', 'initial_password'];

    public static function log(
        string $action,
        Model  $model,
        array  $before = [],
        array  $after  = []
    ): void {
        if (!Setting::getCached('transaction_log_enabled', '1')) return;

        try {
            $hidden = array_merge(
                self::GLOBAL_HIDDEN,
                $model->auditHidden ?? []
            );

            foreach ($hidden as $key) {
                unset($before[$key], $after[$key]);
            }

            $changes = null;
            if ($action === 'created' && $after) {
                $changes = ['after' => $after];
            } elseif ($action === 'updated' && ($before || $after)) {
                $changes = ['before' => $before, 'after' => $after];
            }

            // Determine human-readable label for the record
            $label = method_exists($model, 'getAuditLabel')
                ? $model->getAuditLabel()
                : ($model->name ?? $model->title ?? (string) $model->getKey());

            $user = auth()->user();

            TransactionLog::create([
                'user_id'     => $user?->id,
                'user_name'   => $user?->name ?? 'System',
                'action'      => $action,
                'model_type'  => class_basename($model),
                'model_id'    => $model->getKey(),
                'model_label' => $label,
                'changes'     => $changes,
                'ip_address'  => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Audit logging must never break the main operation
        }
    }
}
