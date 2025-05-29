<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ameax\HashChangeDetector\HashChangeDetector
 */
class HashChangeDetector extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ameax\HashChangeDetector\HashChangeDetector::class;
    }
}
