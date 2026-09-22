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
        Schema::create('kardexs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha_movimiento')->useCurrent();
            $table->text('observacion')->nullable();
            $table->integer('cantidad');
            $table->decimal('costo_unitario', 10, 2);
            $table->decimal('costo_total', 10, 2);
            $table->integer('saldo_cantidad');
            $table->decimal('saldo_costo_unitario', 10, 2);
            $table->foreignId('tipo_movimiento_id')->constrained()->restrictOnDelete();
            $table->foreignId('producto_id')->constrained()->restrictOnDelete();
            $table->foreignId('lote_id')->constrained()->restrictOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('entrada_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salida_id')->nullable()->constrained()->nullOnDelete();
            // $table->foreignId('ajuste_id')->constrained()->onDelete('cascade')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kardexs');
    }
};
