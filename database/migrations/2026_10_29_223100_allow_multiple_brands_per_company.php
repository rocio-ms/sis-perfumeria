<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('marcas', 'marcas_empresa_id_unique')) {
            Schema::table('marcas', function (Blueprint $table) {
                $table->dropUnique('marcas_empresa_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasIndex('marcas', 'marcas_empresa_id_unique')) {
            Schema::table('marcas', function (Blueprint $table) {
                $table->unique('empresa_id');
            });
        }
    }
};
