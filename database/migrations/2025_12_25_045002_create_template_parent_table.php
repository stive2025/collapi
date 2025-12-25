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
        Schema::create('template_parent', function (Blueprint $table) {
            $table->foreignId('template_id');
            $table->foreign('template_id')
                ->references('id')
                ->on('template_models')
                ->onDelete('cascade');

            $table->foreignId('parent_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('template_models')
                ->onDelete('cascade');

            $table->primary(['template_id', 'parent_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_parent');
    }
};
