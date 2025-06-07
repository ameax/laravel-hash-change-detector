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
                            $dependent = $updatedModel->$relationName;
                        }

                        if ($dependent && $dependent instanceof Hashable) {
                            $modelKey = get_class($updatedModel).':'.$updatedModel->getKey();
                            $dependentReferences[$modelKey][] = $dependent;
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
                            $dependent = $updatedModel->$relationName;
                        }

                        if ($dependent && $dependent instanceof Hashable) {
                            $dependent->updateHash();
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
    }
}
