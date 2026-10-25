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
        Schema::table('productos', function (Blueprint $table) {
            $table->enum('ubicacion', [
                'Vitrina Grande 1° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
                'Mostrador ubicado en el interior de la Tienda Lado Sur',
                'Mostrador de madera ubicado en la pared interior de la Tienda Lado Sur',
                'Encima de la Vitrina Mediana Ubicada en el Interior de la Tienda Lado Sur', 
                'Encima de Vitrina Grande ubicado al Interior de la Tienda Lado Este',
                'Encima de Octogonal ubicado al Interior de la Tienda Lado Este', 
                'Mostrador Metalico Ubicado en la pared interior de la Tienda Lado Este',
                'Vitrina Grande 5° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
                'Exhibidor Metalico ubicado en el interior de la Tienda Lado Norte (A la entrada)',
                'Exhibidor Metalico ubicado en el interior de la Tienda Lado Norte (A la entrada N° 02)',
                'Andamio Metalico Dexion lado Este de la Tienda',
                'Vitrina Grande 4° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
                'Caja Antigua ubicada en el interior de la Tienda Lado Este',
                'Vitrina Grande 6° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
                'Vitrina Grande 2° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
                'Octogonal ubicado en el interior de la Tienda',
                'Mostrador mediano ubicado en el interior de la Tienda Lado Sur',
                'Vitrina grande ubicada en el interior de la Tienda Lado Este',
                'Vitrina grande ubicada al interior de la Tienda Lado Oeste',
                'Mostrador ubicado en el interior de la Tienda Lado Este',
                'Vitrina Grande Ubicado al Interior exterior de la Tienda Lado Oeste',
                'Vitrina Grande 3° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
                'Vitrina Mediana Lado Sur de la Tienda',
                'Armado Metalico Ranurado Dexion ubicado en el fondo de la Tienda lado Este',
                'Exhibidor Metalico Circular ubicado en el interior de la Tienda Lado Sur',
            ])->nullable()->after('descripcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('ubicacion');
        });
    }
};
