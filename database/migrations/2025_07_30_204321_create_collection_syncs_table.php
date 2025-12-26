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
        Schema::create('collection_syncs', function (Blueprint $table) {
            $table->id();

            $table->string('sync_type');
            $table->string('state');
            $table->string('state_description');
            $table->integer('new_credits')->nullable();
            $table->integer('nro_syncs')->nullable();
            $table->string('nro_credits')->nullable();
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
        Schema::dropIfExists('collection_syncs');
    }
};
