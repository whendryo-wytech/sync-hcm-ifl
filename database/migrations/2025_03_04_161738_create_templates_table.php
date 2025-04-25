<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('templates', static function (Blueprint $table) {
            $table->id();
            $table->integer('hcm_id');
            $table->string('name')->nullable();
            $table->string('pis')->nullable();
            $table->string('cpf')->nullable();
            $table->string('rfid')->nullable();
            $table->longText('template')->nullable();
            $table->boolean('valid')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
