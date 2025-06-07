<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestUserModel extends Model implements Hashable
{
    use InteractsWithHashes;

    protected $table = 'test_users';

    protected $fillable = [
        'country_id',
        'name',
        'email',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(TestCountryModel::class, 'country_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(TestPostModel::class, 'user_id');
    }

    public function getHashableAttributes(): array
    {
        return ['name', 'email'];
    }

    public function getHashCompositeDependencies(): array
    {
        return [];
    }
}
