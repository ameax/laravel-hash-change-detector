<?php

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

    protected function updateParentHashes(): void
    {
        if ($this->testModel) {
            // Force reload of relations before updating hash
            $this->testModel->load('testRelations');
            $this->testModel->updateHash();
        }
    }
}