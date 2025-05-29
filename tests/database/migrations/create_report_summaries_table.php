<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('report_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->decimal('total_sales', 10, 2);
            $table->integer('total_orders');
            $table->decimal('average_order_value', 8, 2);
            $table->unsignedBigInteger('top_product_id')->nullable();
            $table->timestamps();

            $table->unique('report_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('report_summaries');
    }
};
