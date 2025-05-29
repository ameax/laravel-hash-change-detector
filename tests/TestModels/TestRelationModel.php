<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestRelationModel extends Model implements Hashable
{
    use InteractsWithHashes;

    protected $fillable = [
        'test_model_id',
        'key',
        'value',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function getHashableAttributes(): array
    {
        return ['key', 'value', 'order'];
    }

    public function getHashableRelations(): array
    {
        return [];
    }

    public function testModel(): BelongsTo
    {
        return $this->belongsTo(TestModel::class);
    }

    /**
     * Get parent models that should be notified of changes.
     */
    public function getParentModels(): \Illuminate\Support\Collection
    {
        $parents = collect();

        if ($this->testModel) {
            $parents->push($this->testModel);
        }

        return $parents;
    }
}
