<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestPostModel extends Model implements Hashable
{
    use InteractsWithHashes;

    protected $table = 'test_posts';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'published',
    ];

    protected $casts = [
        'published' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TestUserModel::class, 'user_id');
    }

    public function getHashableAttributes(): array
    {
        return ['title', 'content', 'published'];
    }

    public function getHashableRelations(): array
    {
        return [];
    }

    /**
     * Get parent model relations that should be notified when this model changes.
     * Since countries track posts through hasManyThrough, we need to notify them too.
     */
    public function getParentModelRelations(): array
    {
        // Notify both direct parent (user) and indirect parent (country)
        return ['user', 'user.country'];
    }
}
