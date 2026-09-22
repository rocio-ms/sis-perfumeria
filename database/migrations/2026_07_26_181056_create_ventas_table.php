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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('sesion_caja_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tipo_comprobante_id')->constrained()->onDelete('cascade');
            $table->foreignId('empresa_emisora_id')->nullable()->constrained('empresas')->restrictOnDelete();
            $table->timestamp('fecha_emision')->useCurrent();
            $table->string('serie');
            $table->string('correlativo');
            $table->string('moneda')->default('soles');
            $table->enum('forma_pago', ['contado', 'credito']);
            $table->foreignId('tipo_pago_id')->constrained()->onDelete('cascade');
            $table->decimal('op_gravada', 10, 2)->default(0);
            $table->decimal('op_exonerada', 10, 2)->default(0);
            $table->decimal('op_inafecta', 10, 2)->default(0);
            $table->decimal('op_gratuita', 10, 2)->default(0);
            $table->decimal('igv', 10, 2)->default(0);
            $table->decimal('descuento_total', 10, 2)->default(0);
            $table->decimal('importe_total', 10,2);
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->foreignId('serie_comprobante_id')->constrained()->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('empresa_emisora_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
