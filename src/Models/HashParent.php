<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HashParent extends Model
{
    protected $fillable = [
        'child_hash_id',
        'parent_model_type',
        'parent_model_id',
        'relation_name',
    ];

    protected $casts = [
        'parent_model_id' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('hash-change-detector.tables.hash_parents', 'hash_parents');
    }

    /**
     * Get the child hash.
     */
    public function childHash(): BelongsTo
    {
        return $this->belongsTo(Hash::class, 'child_hash_id');
    }

    /**
     * Get the parent model.
     */
    public function parent(): ?Model
    {
        if (!$this->parent_model_type || !$this->parent_model_id) {
            return null;
        }

        return $this->parent_model_type::find($this->parent_model_id);
    }
}