<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_importadas', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->char('huella', 64)->unique();
            $tabla->string('archivo');
            $tabla->unsignedInteger('fila_encabezado');
            $tabla->json('encabezados');
            $tabla->json('resumen');
            $tabla->timestamps();
        });

        Schema::create('filas_encuesta', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('encuesta_id')->constrained('encuestas_importadas')->cascadeOnDelete();
            $tabla->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $tabla->unsignedInteger('numero');
            $tabla->text('nombre')->nullable();
            $tabla->string('estado');
            $tabla->longText('celdas');
            $tabla->longText('revision');
            $tabla->timestamps();
            $tabla->unique(['encuesta_id', 'numero']);
            $tabla->index(['encuesta_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filas_encuesta');
        Schema::dropIfExists('encuestas_importadas');
    }
};
