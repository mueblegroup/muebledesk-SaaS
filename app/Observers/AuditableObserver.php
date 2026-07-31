<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function __construct(private ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->log($this->event($model, 'created'), class_basename($model).' created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $old = collect($changes)->mapWithKeys(fn ($value, $key) => [$key => $model->getOriginal($key)])->all();
        $this->logger->log($this->event($model, 'updated'), class_basename($model).' updated', $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->logger->log($this->event($model, 'deleted'), class_basename($model).' deleted', $model, $model->getAttributes());
    }

    private function event(Model $model, string $action): string
    {
        return str(class_basename($model))->snake()->append('.'.$action)->toString();
    }
}
