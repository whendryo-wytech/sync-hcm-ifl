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
        Schema::create('device_users', static function (Blueprint $table) {
            $table->id();
            $table->integer('hcm_id');
            $table->text('name')->nullable();
            $table->string('pis')->nullable();
            $table->string('cpf')->nullable();
            $table->string('rfid')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};
