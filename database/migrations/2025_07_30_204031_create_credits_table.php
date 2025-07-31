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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->string('sync_id');
            $table->string('agency');
            $table->string('collection_state');
            $table->string('frequency')->nullable();
            $table->string('payment_date')->nullable();
            $table->string('award_date')->nullable();
            $table->string('due_date')->nullable();
            $table->integer('days_past_due');
            
            $table->integer('total_fees')->nullable();
            $table->integer('paid_fees')->nullable();
            $table->integer('pending_fees')->nullable();

            $table->float('monthly_fee_amount')->nullable();
            $table->float('total_amount');
            $table->float('capital');
            $table->float('interest');
            $table->float('mora');
            $table->float('safe');
            $table->float('management_collection_expenses')->nullable();
            $table->float('collection_expenses');
            $table->float('legal_expenses');
            $table->float('other_values');

            $table->string('sync_status')->nullable();
            $table->string('last_sync_date')->nullable();
            $table->string('management_status')->nullable();
            $table->string('management_tray')->nullable();
            $table->string('management_promise')->nullable();

            $table->string('date_offer')->nullable();
            $table->string('date_promise')->nullable();
            $table->string('date_notification')->nullable();

            $table->foreignId('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
            
            $table->foreignId('business_id');
            $table->foreign('business_id')
                ->references('id')
                ->on('businesses');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
