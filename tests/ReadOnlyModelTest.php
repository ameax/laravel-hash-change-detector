<?php

declare(strict_types=1);

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use ameax\HashChangeDetector\Models\Hash;
use ameax\HashChangeDetector\Tests\TestModels\ReadOnlyReportModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Drop table if exists then create
    Schema::dropIfExists('report_summaries');
    Schema::create('report_summaries', function ($table) {
        $table->id();
        $table->date('report_date');
        $table->decimal('total_sales', 10, 2);
        $table->integer('total_orders');
        $table->decimal('average_order_value', 8, 2);
        $table->unsignedBigInteger('top_product_id')->nullable();
        $table->timestamps();

        $table->unique('report_date');
    });
});

it('prevents save operations on read-only models', function () {
    $report = new ReadOnlyReportModel;
    $report->report_date = '2024-01-01';
    $report->total_sales = 1000;

    expect(fn () => $report->save())->toThrow(RuntimeException::class, 'Cannot save read-only model');
});

it('prevents delete operations on read-only models', function () {
    // Insert directly into database
    DB::table('report_summaries')->insert([
        'report_date' => '2024-01-01',
        'total_sales' => 1000,
        'total_orders' => 10,
        'average_order_value' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = ReadOnlyReportModel::first();

    expect(fn () => $report->delete())->toThrow(RuntimeException::class, 'Cannot delete read-only model');
});

it('can initialize hash for read-only models', function () {
    // Insert directly into database (simulating external data source)
    DB::table('report_summaries')->insert([
        'report_date' => '2024-01-01',
        'total_sales' => 5000.50,
        'total_orders' => 50,
        'average_order_value' => 100.01,
        'top_product_id' => 123,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = ReadOnlyReportModel::first();

    // Should not have hash initially
    expect($report->getCurrentHash())->toBeNull();

    // Initialize hash
    $report->initializeHash();

    // Should now have hash
    $hash = $report->getCurrentHash();
    expect($hash)->not->toBeNull();
    // Attributes are sorted: average_order_value, report_date, top_product_id, total_orders, total_sales
    expect($hash->attribute_hash)->toBe(md5('100.01|2024-01-01|123|50|5000.5'));
});

it('detects changes in read-only models via direct database detection', function () {
    // Insert initial data
    DB::table('report_summaries')->insert([
        'report_date' => '2024-01-01',
        'total_sales' => 5000.00,
        'total_orders' => 50,
        'average_order_value' => 100.00,
        'top_product_id' => 123,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = ReadOnlyReportModel::first();
    $report->initializeHash();

    $initialHash = $report->getCurrentHash()->attribute_hash;

    // Update directly in database (simulating external update)
    DB::table('report_summaries')
        ->where('id', $report->id)
        ->update(['total_sales' => 6000.00]);

    // Run detection job
    $job = new DetectChangesJob(ReadOnlyReportModel::class);
    $job->handle();

    // Check hash was updated
    $report->refresh();
    $newHash = $report->getCurrentHash()->attribute_hash;

    expect($newHash)->not->toBe($initialHash);
    // Attributes are sorted: average_order_value, report_date, top_product_id, total_orders, total_sales
    expect($newHash)->toBe(md5('100|2024-01-01|123|50|6000'));
});

it('can check if read-only model needs hash update', function () {
    // Insert data
    DB::table('report_summaries')->insert([
        'report_date' => '2024-01-01',
        'total_sales' => 5000.00,
        'total_orders' => 50,
        'average_order_value' => 100.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $report = ReadOnlyReportModel::first();

    // Should need update (no hash exists)
    expect($report->needsHashUpdate())->toBeTrue();

    // Initialize hash
    $report->initializeHash();

    // Should not need update now
    expect($report->needsHashUpdate())->toBeFalse();

    // Update directly in database
    DB::table('report_summaries')
        ->where('id', $report->id)
        ->update(['total_sales' => 6000.00]);

    // Clear model cache to simulate fresh load
    $report = ReadOnlyReportModel::find($report->id);

    // Should need update now
    expect($report->needsHashUpdate())->toBeTrue();
});

it('handles bulk initialization of read-only models', function () {
    // Insert multiple records
    $records = [];
    for ($i = 1; $i <= 5; $i++) {
        $records[] = [
            'report_date' => "2024-01-0{$i}",
            'total_sales' => 1000 * $i,
            'total_orders' => 10 * $i,
            'average_order_value' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('report_summaries')->insert($records);

    // No hashes should exist
    $hashCount = Hash::where('hashable_type', ReadOnlyReportModel::class)->count();
    expect($hashCount)->toBe(0);

    // Initialize all hashes
    ReadOnlyReportModel::each(function ($report) {
        $report->initializeHash();
    });

    // All should have hashes now
    $hashCount = Hash::where('hashable_type', ReadOnlyReportModel::class)->count();
    expect($hashCount)->toBe(5);
});
