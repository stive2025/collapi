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
        Schema::create('condonations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_id');
            $table->foreign('credit_id')->references('id')->on('credits');
            $table->decimal('amount', 15, 2);
            $table->jsonb('prev_dates')->nullable();
            $table->jsonb('post_dates')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('condonations');
    }
};
