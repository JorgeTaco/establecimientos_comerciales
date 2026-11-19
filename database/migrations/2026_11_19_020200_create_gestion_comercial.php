<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('establecimiento_id')->constrained('establecimientos');
            $table->string('estado')->default('por_contactar');
            $table->text('notas')->nullable();
            $table->date('proxima_gestion')->nullable();
            $table->timestamp('primer_pedido_en')->nullable();
            $table->text('condiciones_acordadas')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'establecimiento_id']);
        });

        Schema::create('propuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidad_id')->constrained('oportunidades');
            $table->json('productos');
            $table->uuid('referencia')->nullable();
            $table->unique(['oportunidad_id', 'referencia']);
            $table->text('mensaje');
            $table->string('respuesta')->nullable();
            $table->text('mensaje_respuesta')->nullable();
            $table->timestamp('respondida_en')->nullable();
            $table->timestamps();
        });

        Schema::create('reportes_establecimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos');
            $table->foreignId('user_id')->constrained();
            $table->text('descripcion');
            $table->string('estado')->default('pendiente');
            $table->text('resolucion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes_establecimientos');
        Schema::dropIfExists('propuestas');
        Schema::dropIfExists('oportunidades');
    }
};
