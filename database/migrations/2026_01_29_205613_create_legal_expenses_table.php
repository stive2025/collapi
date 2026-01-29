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
        Schema::create('legal_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_id');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('modify_date')->nullable();
            $table->decimal('prev_amount', 12, 2)->default(0);
            $table->decimal('post_amount', 12, 2)->default(0);
            $table->string('detail')->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->string('sync_id')->nullable();
            $table->timestamps();

            $table->foreign('credit_id')->references('id')->on('credits')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_expenses');
    }
};
