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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('codigo_barras')->nullable()->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('unidad_medida', [
                'unidad',
                'pieza',
                'juego',
                'set', 
                'par',
                'coleccion', 
                'paquete',
                'ml',
                'tubo',
                'equipo',
                'docena',
                'frasco',
                'bolsita',
                'gr',
                'kit',
                'cajita',
            ]);
            $table->integer('stock_minimo')->default(0);
            $table->integer('stock_actual')->default(0);
            $table->unsignedInteger('volumen_ml')->nullable();
            $table->string('imagen_url')->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('categoria_id')->constrained()->onDelete('cascade');
            $table->foreignId('marca_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('empresa_id')->constrained()->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
