<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 6)->index();
            $table->string('account_ref', 14)->nullable();
            $table->string('user_ref', 12)->nullable();
            $table->integer('quantity', false, true);
            $table->decimal('value', 8, 2, true);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('account_ref')->references('ref')->on('accounts')->onDelete('cascade');
            $table->foreign('user_ref')->references('ref')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
