<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ingreso', 20)->unique();
            // DOCUMENTO O COMPROBANTE
            $table->enum('tipo_comprobante', ['factura', 'boleta', 'guia_remision', 'nota_compra', 'orden_compra'])->nullable();
            $table->string('numero_comprobante')->nullable();
            $table->date('fecha_ingreso')->useCurrent();
            //MONTO Y NOTAS
            $table->decimal('total_compra', 10, 2);
            $table->text('observacion')->nullable();
            //CLAVES FORÁNEAS
            $table->foreignId('proveedor_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas');
    }
};
