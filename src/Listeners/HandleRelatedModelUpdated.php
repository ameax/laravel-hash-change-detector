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

        // Get parent models from the updated model's getParentModelRelations() method
        if (method_exists($updatedModel, 'getParentModelRelations')) {
            // Store parent references before deletion
            static $parentReferences = [];

            if ($action === 'deleting') {
                // Store parent references before the model is deleted
                foreach ($updatedModel->getParentModelRelations() as $relationName) {
                    try {
                        // Handle nested relations (e.g., 'user.country')
                        if (str_contains($relationName, '.')) {
                            $parts = explode('.', $relationName);
                            $parent = $updatedModel;
                            foreach ($parts as $part) {
                                $parent = $parent->$part;
                                if (! $parent) {
                                    break;
                                }
                            }
                        } else {
                            $parent = $updatedModel->$relationName;
                        }

                        if ($parent && $parent instanceof Hashable) {
                            $modelKey = get_class($updatedModel).':'.$updatedModel->getKey();
                            $parentReferences[$modelKey][] = $parent;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            } elseif ($action === 'deleted') {
                // Update parent hashes after deletion using stored references
                $modelKey = get_class($updatedModel).':'.$updatedModel->getKey();
                if (isset($parentReferences[$modelKey])) {
                    foreach ($parentReferences[$modelKey] as $parent) {
                        $parent->load($parent->getHashableRelations());
                        $parent->updateHash();
                    }
                    unset($parentReferences[$modelKey]);
                }
            } else {
                // For create/update, update immediately
                foreach ($updatedModel->getParentModelRelations() as $relationName) {
                    try {
                        // Handle nested relations (e.g., 'user.country')
                        if (str_contains($relationName, '.')) {
                            $parts = explode('.', $relationName);
                            $parent = $updatedModel;
                            foreach ($parts as $part) {
                                $parent = $parent->$part;
                                if (! $parent) {
                                    break;
                                }
                            }
                        } else {
                            $parent = $updatedModel->$relationName;
                        }

                        if ($parent && $parent instanceof Hashable) {
                            $parent->updateHash();
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
    }
}
