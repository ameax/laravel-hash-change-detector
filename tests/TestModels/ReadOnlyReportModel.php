<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Tests\TestModels;

use ameax\HashChangeDetector\Contracts\Hashable;
use ameax\HashChangeDetector\Traits\TracksHashesOnly;
use Illuminate\Database\Eloquent\Model;

/**
 * Example of a read-only model that represents a database view or external table.
 * Changes are only made through direct SQL, never through Eloquent.
 */
class ReadOnlyReportModel extends Model implements Hashable
{
    use TracksHashesOnly;

    protected $table = 'report_summaries';

    // Disable Eloquent mutations
    public static $readOnly = true;

    protected $fillable = [];

    public function getHashableAttributes(): array
    {
        return [
            'report_date',
            'total_sales',
            'total_orders',
            'average_order_value',
            'top_product_id',
        ];
    }

    public function getHashCompositeDependencies(): array
    {
        return []; // Read-only models typically don't track relations
    }

    /**
     * Override save to prevent accidental writes.
     */
    public function save(array $options = [])
    {
        if (static::$readOnly) {
            throw new \RuntimeException('Cannot save read-only model: '.static::class);
        }

        return parent::save($options);
    }

    /**
     * Override delete to prevent accidental deletion.
     */
    public function delete()
    {
        if (static::$readOnly) {
            throw new \RuntimeException('Cannot delete read-only model: '.static::class);
        }

        return parent::delete();
    }
}
