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
        Schema::create('sesion_cajas', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha_apertura');
            $table->timestamp('fecha_cierre')->nullable();
            $table->decimal('monto_apertura', 10, 2);
            $table->decimal('monto_cierre_teorico', 10, 2)->nullable();
            $table->decimal('monto_cierre_real', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->enum('estado', ['abierta', 'cerrada'])->default('cerrada');
            $table->string('observaciones')->nullable();
            $table->foreignId('caja_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesion_cajas');
    }
};
