<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Listeners;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Events\RelatedModelUpdated;

class HandleRelatedModelUpdated
{
    /**
     * Handle the event.
     */
    public function handle(RelatedModelUpdated $event): void
    {
        $updatedModel = $event->model;

        // Get parent models from the updated model
        if (method_exists($updatedModel, 'getParentModels')) {
            foreach ($updatedModel->getParentModels() as $parent) {
                if ($parent instanceof Hashable) {
                    $parent->updateHash();
                }
            }
        }

        // Also check for models that have this model as a relation
        $this->updateModelsTrackingThisRelation($updatedModel);
    }

    /**
     * Find and update models that track this model as a relation.
     */
    protected function updateModelsTrackingThisRelation(\Illuminate\Database\Eloquent\Model $model): void
    {
        // Get the hash record to find main models
        $hash = $model->getCurrentHash();
        if ($hash && $hash->main_model_type && $hash->main_model_id) {
            $mainModel = $hash->main_model_type::find($hash->main_model_id);
            if ($mainModel && $mainModel instanceof Hashable) {
                $mainModel->updateHash();
            }
        }
    }
}
