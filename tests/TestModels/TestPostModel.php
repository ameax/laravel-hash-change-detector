<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
     * Get parent models that should be notified of changes.
     * This allows the country to be updated when a post changes.
     */
    public function getParentModels(): Collection
    {
        $parents = collect();

        // Load user relationship if not already loaded
        if (! $this->relationLoaded('user')) {
            $this->load('user.country');
        }

        if ($this->user && $this->user->country) {
            $parents->push($this->user->country);
        }

        return $parents;
    }
}
