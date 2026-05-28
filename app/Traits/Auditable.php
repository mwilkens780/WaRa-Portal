<?php

namespace App\Traits;

use App\Services\AuditService;

/**
 * Add to any Eloquent model to automatically write transaction log entries.
 * Sensitive fields can be excluded per model: protected array $auditHidden = ['secret_field'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $after = array_diff_key(
                $model->getAttributes(),
                array_flip(['password', 'remember_token', 'initial_password'])
            );
            AuditService::log('created', $model, [], $after);
        });

        static::updated(function ($model) {
            $changed = $model->getChanges();
            if (empty($changed)) return;

            $before = array_intersect_key($model->getOriginal(), $changed);
            AuditService::log('updated', $model, $before, $changed);
        });

        static::deleted(function ($model) {
            AuditService::log('deleted', $model);
        });
    }

    public function getAuditLabel(): string
    {
        return $this->name ?? $this->title ?? (string) $this->getKey();
    }
}
