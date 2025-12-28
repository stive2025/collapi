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
        Schema::create('collection_directions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('address')->nullable();
            $table->string('province')->nullable();
            $table->string('canton')->nullable();
            $table->string('parish')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->foreignId('client_id');
            $table->foreign('client_id')
                ->references('id')
                ->on('clients');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_directions');
    }
};
