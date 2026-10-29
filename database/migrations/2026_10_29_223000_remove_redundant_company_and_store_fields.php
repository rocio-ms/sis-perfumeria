<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_comercial',
                'direccion',
                'telefono',
                'email',
            ]);
        });

        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropUnique('tiendas_ruc_unique');
        });

        Schema::table('tiendas', function (Blueprint $table) {
            $table->dropColumn([
                'ruc',
                'razon_social',
            ]);
        });

        Schema::table('marcas', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable(false)->change();
        });

        Schema::table('tiendas', function (Blueprint $table) {
            $table->string('ruc', 11)->nullable()->unique();
            $table->string('razon_social')->nullable();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email')->nullable();
        });
    }
};
