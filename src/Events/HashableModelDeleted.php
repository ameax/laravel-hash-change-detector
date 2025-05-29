<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Events;

use ameax\HashChangeDetector\Models\Hash;
use Illuminate\Foundation\Events\Dispatchable;

class HashableModelDeleted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Hash $hash,
        public string $modelClass,
        public int $modelId
    ) {}
}
