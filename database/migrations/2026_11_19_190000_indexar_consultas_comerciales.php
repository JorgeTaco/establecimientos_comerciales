<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimientos', function (Blueprint $tabla) {
            $tabla->index(['parroquia', 'tipo'], 'est_parroquia_tipo_idx');
            $tabla->index(['nombre', 'id'], 'est_nombre_id_idx');
            $tabla->index(['latitud', 'longitud'], 'est_coordenadas_idx');
        });
        Schema::table('oportunidades', function (Blueprint $tabla) {
            $tabla->index('estado', 'oport_estado_idx');
            $tabla->index(['primer_pedido_en', 'user_id'], 'oport_primer_pedido_usuario_idx');
        });
    }

    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $tabla) {
            $tabla->dropIndex('oport_estado_idx');
            $tabla->dropIndex('oport_primer_pedido_usuario_idx');
        });
        Schema::table('establecimientos', function (Blueprint $tabla) {
            $tabla->dropIndex('est_coordenadas_idx');
            $tabla->dropIndex('est_nombre_id_idx');
            $tabla->dropIndex('est_parroquia_tipo_idx');
        });
    }
};
