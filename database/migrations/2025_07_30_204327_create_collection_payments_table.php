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
        Schema::create('collection_payments', function (Blueprint $table) {
            $table->id();

            $table->integer('created_by')->nullable();

            $table->timestamp('payment_date');
            $table->timestamp('payment_deposit_date')->nullable();
            $table->float('payment_value');
            $table->float('payment_difference')->nullable();
            $table->string('payment_type');
            $table->string('payment_method')->nullable();
            $table->string('financial_institution')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->nullable();
            $table->integer('payment_prints')->nullable();

            $table->float('fee')->nullable();
            $table->float('capital');
            $table->float('interest');
            $table->float('mora');
            $table->float('safe')->nullable();
            $table->float('management_collection_expenses')->nullable();
            $table->float('collection_expenses')->nullable();
            $table->float('legal_expenses')->nullable();
            $table->float('other_values');

            $table->text('prev_dates')->nullable();

            $table->string('with_management')->nullable();
            $table->integer('management_auto')->nullable();
            $table->integer('days_past_due_auto')->nullable();
            $table->integer('management_prev')->nullable();
            $table->integer('days_past_due_prev')->nullable();
            $table->string('post_management')->nullable();
            
            $table->foreignId('credit_id');
            $table->foreign('credit_id')
                ->references('id')
                ->on('credits');

            $table->foreignId('business_id');
            $table->foreign('business_id')
                ->references('id')
                ->on('businesses');

            $table->foreignId('campain_id');
            $table->foreign('campain_id')
                ->references('id')
                ->on('campains');

            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_payments');
    }
};
