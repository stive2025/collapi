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
        Schema::create('management', function (Blueprint $table) {
            $table->id();
            $table->string('state');
            $table->string('substate');
            $table->text('observation')->nullable();
            $table->timestamp('promise_date');
            $table->float('promise_amount')->nullable();
            $table->integer('created_by');
            
            $table->integer('call_id')->nullable();
            $table->text('call_collection')->nullable();
            $table->integer('days_past_due');
            $table->integer('paid_fees');
            $table->integer('pending_fees');
            $table->float('managed_amount');
            $table->string('nro_notification')->nullable();

            $table->foreignId('client_id');
            $table->foreign('client_id')
                ->references('id')
                ->on('clients');

            $table->foreignId('credit_id');
            $table->foreign('credit_id')
                ->references('id')
                ->on('credits');
            
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
        Schema::dropIfExists('management');
    }
};
