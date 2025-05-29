<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('test_relation_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_model_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('value')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_relation_models');
        Schema::dropIfExists('test_models');
    }
};