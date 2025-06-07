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
        $hashParentsTable = config('laravel-hash-change-detector.tables.hash_parents', 'hash_parents');

        // Create hashes table
        Schema::create($hashesTable, function (Blueprint $table) {
            $table->id();
            $table->morphs('hashable');
            $table->string('attribute_hash', 32);
            $table->string('composite_hash', 32)->nullable();
            $table->timestamps();

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

        // Create hash_parents table for tracking parent-child relationships
        Schema::create($hashParentsTable, function (Blueprint $table) use ($hashesTable) {
            $table->id();
            $table->foreignId('child_hash_id')->constrained($hashesTable)->cascadeOnDelete();
            $table->string('parent_model_type');
            $table->unsignedBigInteger('parent_model_id');
            $table->string('relation_name')->nullable(); // Store which relation this came from
            $table->timestamps();
            
            // Prevent duplicate parent-child relationships
            $table->unique(['child_hash_id', 'parent_model_type', 'parent_model_id'], 'unique_parent_child');
            // Index for finding all children of a parent
            $table->index(['parent_model_type', 'parent_model_id'], 'parent_model_index');
        });
    }

    public function down(): void
    {
        $hashesTable = config('laravel-hash-change-detector.tables.hashes', 'hashes');
        $publishersTable = config('laravel-hash-change-detector.tables.publishers', 'publishers');
        $publishesTable = config('laravel-hash-change-detector.tables.publishes', 'publishes');
        $hashParentsTable = config('laravel-hash-change-detector.tables.hash_parents', 'hash_parents');

        Schema::dropIfExists($hashParentsTable);
        Schema::dropIfExists($publishesTable);
        Schema::dropIfExists($publishersTable);
        Schema::dropIfExists($hashesTable);
    }
};
