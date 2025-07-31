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
        Schema::create('collection_contacts', function (Blueprint $table) {
            $table->id();

            $table->string('phone_number');
            $table->string('phone_type');
            $table->string('phone_status');

            $table->string('calls_effective');
            $table->string('calls_not_effective');

            $table->integer('created_by');
            $table->integer('updated_by');
            $table->integer('deleted_by');

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
        Schema::dropIfExists('collection_contacts');
    }
};
