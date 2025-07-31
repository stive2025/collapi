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
        Schema::create('campains', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('state');
            $table->string('type');
            $table->timestamp('begin_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->text('agents');

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
        Schema::dropIfExists('campains');
    }
};
