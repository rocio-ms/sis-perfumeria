<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->enum('unidad_medida', [
                'unidad',
                'pieza',
                'juego',
                'set',
                'par',
                'coleccion',
                'paquete',
                'ml',
                'l',
                'tubo',
                'equipo',
                'docena',
                'frasco',
                'bolsita',
                'gr',
                'kit',
                'cajita',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
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
            ])->change();
        });
    }
};
