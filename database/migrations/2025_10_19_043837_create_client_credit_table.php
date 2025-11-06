<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientCreditTable extends Migration
{
    public function up()
    {
        Schema::create('client_credit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('credit_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['client_id', 'credit_id']); // Evita duplicados
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('client_credit');
    }
}