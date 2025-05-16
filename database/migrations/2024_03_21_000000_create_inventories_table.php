<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('product_type'); // gold, japan, labubu
            $table->string('item_name');
            $table->string('borrower_name');
            $table->date('borrow_date');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('grams', 10, 2)->nullable(); // For gold products
            $table->decimal('amount_per_gram', 10, 2)->nullable(); // For gold products
            $table->decimal('original_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('downpayment', 10, 2)->nullable(); // For gold products
            $table->string('status')->default('active'); // active, sold, returned
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventories');
    }
}; 