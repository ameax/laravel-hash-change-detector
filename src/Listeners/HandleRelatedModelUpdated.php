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
        $action = $event->action;

        // Get dependent models from the updated model's getHashRelationsToNotifyOnChange() method
        if (method_exists($updatedModel, 'getHashRelationsToNotifyOnChange')) {
            // Process dependent models based on action
            // Store parent references before deletion
            static $dependentReferences = [];

            if ($action === 'deleting') {
                // Store dependent references before the model is deleted
                foreach ($updatedModel->getHashRelationsToNotifyOnChange() as $relationName) {
                    try {
                        // Handle nested relations (e.g., 'user.country')
                        if (str_contains($relationName, '.')) {
                            $parts = explode('.', $relationName);
                            $dependent = $updatedModel;
                            foreach ($parts as $part) {
                                $dependent = $dependent->$part;
                                if (! $dependent) {
                                    break;
                                }
                            }
                        } else {
                            // Unset the relation to force a fresh load
                            $updatedModel->unsetRelation($relationName);
                            $updatedModel->load($relationName);
                            $dependent = $updatedModel->$relationName;
                        }

                        if ($dependent) {
                            $modelKey = get_class($updatedModel).':'.$updatedModel->getKey();

                            // Handle collections (HasMany, BelongsToMany, etc.)
                            if ($dependent instanceof \Illuminate\Database\Eloquent\Collection) {
                                foreach ($dependent as $model) {
                                    if ($model instanceof Hashable) {
                                        $dependentReferences[$modelKey][] = $model;
                                    }
                                }
                            } elseif ($dependent instanceof Hashable) {
                                // Handle single model (BelongsTo, HasOne, etc.)
                                $dependentReferences[$modelKey][] = $dependent;
                            }
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            } elseif ($action === 'deleted') {
                // Update dependent hashes after deletion using stored references
                $modelKey = get_class($updatedModel).':'.$updatedModel->getKey();
                if (isset($dependentReferences[$modelKey])) {
                    foreach ($dependentReferences[$modelKey] as $dependent) {
                        $dependent->load($dependent->getHashCompositeDependencies());
                        $dependent->updateHash();
                    }
                    unset($dependentReferences[$modelKey]);
                }
            } else {
                // For create/update, update immediately
                foreach ($updatedModel->getHashRelationsToNotifyOnChange() as $relationName) {
                    try {
                        // Handle nested relations (e.g., 'user.country')
                        if (str_contains($relationName, '.')) {
                            $parts = explode('.', $relationName);
                            $dependent = $updatedModel;
                            foreach ($parts as $part) {
                                $dependent = $dependent->$part;
                                if (! $dependent) {
                                    break;
                                }
                            }
                        } else {
                            // For HasMany and other collection relationships, we need to explicitly load them
                            // Unset the relation to force a fresh load
                            $updatedModel->unsetRelation($relationName);
                            $updatedModel->load($relationName);
                            $dependent = $updatedModel->$relationName;

                        }

                        if ($dependent) {
                            // Handle collections (HasMany, BelongsToMany, etc.)
                            if ($dependent instanceof \Illuminate\Database\Eloquent\Collection) {
                                // Process each model in the collection
                                foreach ($dependent as $model) {
                                    if ($model instanceof Hashable) {
                                        $model->updateHash();
                                    }
                                }
                            } elseif ($dependent instanceof Hashable) {
                                // Handle single model (BelongsTo, HasOne, etc.)
                                // Update single model
                                $dependent->updateHash();
                            }
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
    }
}
