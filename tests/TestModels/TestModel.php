<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestModel extends Model implements Hashable
{
    use InteractsWithHashes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function getHashableAttributes(): array
    {
        return ['name', 'description', 'price', 'active'];
    }

    public function getHashCompositeDependencies(): array
    {
        return ['testRelations'];
    }

    public function testRelations(): HasMany
    {
        return $this->hasMany(TestRelationModel::class);
    }
}
