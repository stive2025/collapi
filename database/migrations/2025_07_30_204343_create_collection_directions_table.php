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
            $table->string('direction');
            $table->string('province');
            $table->string('canton');
            $table->string('parish');
            $table->string('neighborhood');
            $table->string('latitude');
            $table->string('longitude');
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
