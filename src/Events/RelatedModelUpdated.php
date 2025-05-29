<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class RelatedModelUpdated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Model $model,
        public string $action // 'created', 'updated', 'deleted'
    ) {}
}
