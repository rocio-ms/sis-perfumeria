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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();

            // Tipo de alerta
            $table->string('tipo');

            // Información que verá el usuario
            $table->string('titulo');
            $table->text('mensaje');

            // Registro que originó la notificación
            $table->string('referencia_tipo');
            $table->unsignedBigInteger('referencia_id');

            // Estado de lectura
            $table->boolean('leida')->default(false);
            $table->timestamp('leida_at')->nullable();

            // Estado de solución
            $table->boolean('solucionada')->default(false);
            $table->timestamp('solucionada_at')->nullable();

            $table->timestamps();

            // Índice para buscar rápidamente una notificación
            // relacionada con un producto o lote.
            $table->index(
                ['tipo', 'referencia_tipo', 'referencia_id'],
                'notificaciones_referencia_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
