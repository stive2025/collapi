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
        Schema::create('collection_credits', function (Blueprint $table) {
            $table->id();

            $table->string('collection_state');
            $table->integer('days_past_due');

            $table->integer('paid_fees')->nullable();
            $table->integer('pending_fees')->nullable();

            $table->float('total_amount');
            $table->float('capital');
            $table->float('interest');
            $table->float('mora');
            $table->float('safe');
            $table->float('management_collection_expenses')->nullable();
            $table->float('collection_expenses');
            $table->float('legal_expenses');
            $table->float('other_values');

            $table->foreignId('credit_id');
            $table->foreign('credit_id')
                ->references('id')
                ->on('credits');
            
            $table->foreignId('campain_id');
            $table->foreign('campain_id')
                ->references('id')
                ->on('campains');

            $table->foreignId('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
            
            $table->date('date');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_credits');
    }
};
