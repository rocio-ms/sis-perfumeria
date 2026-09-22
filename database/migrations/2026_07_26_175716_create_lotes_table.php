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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_lote', 50);
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('cantidad_inicial');
            $table->integer('cantidad_actual');
            $table->foreignId('detalle_entrada_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
