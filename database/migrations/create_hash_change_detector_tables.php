<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hashesTable = config('laravel-hash-change-detector.tables.hashes', 'hashes');
        $publishersTable = config('laravel-hash-change-detector.tables.publishers', 'publishers');
        $publishesTable = config('laravel-hash-change-detector.tables.publishes', 'publishes');

        // Create hashes table
        Schema::create($hashesTable, function (Blueprint $table) {
            $table->id();
            $table->morphs('hashable');
            $table->string('attribute_hash', 32);
            $table->string('composite_hash', 32)->nullable();
            $table->string('main_model_type')->nullable();
            $table->unsignedBigInteger('main_model_id')->nullable();
            $table->timestamps();

            // Index for finding related hashes of a main model
            $table->index(['main_model_type', 'main_model_id']);
            // Unique index to prevent duplicate hash records
            $table->unique(['hashable_type', 'hashable_id']);
        });

        // Create publishers table
        Schema::create($publishersTable, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('model_type');
            $table->string('publisher_class');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index('model_type');
            $table->index('status');
            $table->unique('name');
        });

        // Create publishes table
        Schema::create($publishesTable, function (Blueprint $table) use ($hashesTable, $publishersTable) {
            $table->id();
            $table->foreignId('hash_id')->constrained($hashesTable)->cascadeOnDelete();
            $table->foreignId('publisher_id')->constrained($publishersTable)->cascadeOnDelete();
            $table->string('published_hash', 32);
            $table->timestamp('published_at')->nullable();
            $table->enum('status', ['pending', 'dispatched', 'deferred', 'published', 'failed'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_try')->nullable();
            $table->timestamps();

            // Prevent duplicate publish records for same hash/publisher combination
            $table->unique(['hash_id', 'publisher_id']);
            // Index for finding records that need processing
            $table->index(['status', 'next_try']);
        });
    }

    public function down(): void
    {
        $hashesTable = config('laravel-hash-change-detector.tables.hashes', 'hashes');
        $publishersTable = config('laravel-hash-change-detector.tables.publishers', 'publishers');
        $publishesTable = config('laravel-hash-change-detector.tables.publishes', 'publishes');

        Schema::dropIfExists($publishesTable);
        Schema::dropIfExists($publishersTable);
        Schema::dropIfExists($hashesTable);
    }
};
