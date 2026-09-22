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
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_salida', 20)->unique();
            $table->enum('tipo_salida', ['venta', 'consumo', 'merma', 'devolucion']);
            $table->date('fecha_salida')->useCurrent();
            $table->foreignId('cliente_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('observacion')->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('venta_id')
                ->nullable()
                ->constrained('ventas')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
