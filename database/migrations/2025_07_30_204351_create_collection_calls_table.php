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
        Schema::create('collection_calls', function (Blueprint $table) {
            $table->id();

            $table->string('state');
            $table->string('duration');
            $table->string('media_path')->nullable();
            $table->string('channel');
            $table->string('phone_number');
            
            $table->string('created_by');
            
            $table->foreignId('client_id');
            $table->foreign('client_id')
                ->references('id')
                ->on('clients');
            
            $table->foreignId('credit_id');
            $table->foreign('credit_id')
                ->references('id')
                ->on('credits');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_calls');
    }
};
