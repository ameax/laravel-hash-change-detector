<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Listeners;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Events\RelatedModelUpdated;
use Illuminate\Database\Eloquent\Model;

class HandleRelatedModelUpdated
{
    /**
     * Stack to track models currently being processed to prevent infinite loops.
     * 
     * @var array<string, bool>
     */
    protected static array $processingStack = [];
    
    /**
     * Current depth of the update chain.
     * 
     * @var int
     */
    protected static int $currentDepth = 0;
    
    /**
     * Maximum allowed depth for update chains.
     * 
     * @var int
     */
    protected const MAX_DEPTH = 10;

    /**
     * Handle the event.
     */
    public function handle(RelatedModelUpdated $event): void
    {
        $updatedModel = $event->model;
        $action = $event->action;
        
        // Ensure the model implements Hashable interface
        if (!($updatedModel instanceof Hashable)) {
            return;
        }
        
        // Create a unique key for this model
        $modelKey = get_class($updatedModel) . ':' . $updatedModel->getKey();
        
        // Skip if this model is already being processed (prevents infinite loops)
        if (isset(self::$processingStack[$modelKey]) && self::$processingStack[$modelKey]) {
            return;
        }
        
        // Check depth limit to prevent runaway recursion
        if (self::$currentDepth >= self::MAX_DEPTH) {
            return;
        }
        
        // Mark this model as being processed
        self::$processingStack[$modelKey] = true;
        self::$currentDepth++;
        
        try {
            $this->processModelUpdate($updatedModel, $action);
        } finally {
            // Always remove from processing stack and decrement depth when done
            unset(self::$processingStack[$modelKey]);
            self::$currentDepth--;
        }
    }
    
    /**
     * Process the model update.
     * 
     * @param \Illuminate\Database\Eloquent\Model&\ameax\HashChangeDetector\Contracts\Hashable $updatedModel
     * @param string $action
     */
    protected function processModelUpdate($updatedModel, string $action): void
    {
        // Get dependent models from the updated model's getHashRelationsToNotifyOnChange() method
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
    
    /**
     * Clear the processing stack (useful for testing).
     */
    public static function clearProcessingStack(): void
    {
        self::$processingStack = [];
        self::$currentDepth = 0;
    }
}
