<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\InteractsWithHashes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TestCountryModel extends Model implements Hashable
{
    use InteractsWithHashes;

    protected $table = 'test_countries';

    protected $fillable = [
        'name',
        'code',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(TestUserModel::class, 'country_id');
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(
            TestPostModel::class,
            TestUserModel::class,
            'country_id', // Foreign key on users table
            'user_id',    // Foreign key on posts table
            'id',         // Local key on countries table
            'id'          // Local key on users table
        );
    }

    public function getHashableAttributes(): array
    {
        return ['name', 'code'];
    }

    public function getHashableRelations(): array
    {
        return ['posts'];
    }
}
