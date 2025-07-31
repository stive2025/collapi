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
            $table->integer('new_credits');
            $table->integer('nro_syncs');
            $table->string('code_sync');
            $table->string('nro_credits');

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
