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
        Schema::create('serie_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained()->restrictOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('cascade');
            $table->foreignId('tipo_comprobante_id')->constrained()->onDelete('cascade');
            $table->string('serie', 4);
            $table->unsignedInteger('correlativo_actual')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique([
                'tienda_id',
                'empresa_id',
                'tipo_comprobante_id',
                'serie'
            ],
            'serie_comp_unica'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serie_comprobantes');
    }
};
