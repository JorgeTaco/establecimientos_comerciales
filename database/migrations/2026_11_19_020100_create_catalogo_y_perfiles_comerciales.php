<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('foto_url', 2048)->nullable();
            $table->string('presentacion')->nullable();
            $table->decimal('precio_comercio', 12, 2)->nullable();
            $table->decimal('precio_sugerido', 12, 2)->nullable();
            $table->unsignedInteger('pedido_minimo')->nullable();
            $table->unsignedInteger('capacidad_semanal')->nullable();
            $table->unsignedInteger('vida_util_dias')->nullable();
            $table->boolean('requiere_refrigeracion')->default(false);
            $table->text('conservacion')->nullable();
            $table->json('zonas_entrega')->nullable();
            $table->string('frecuencia_entrega')->nullable();
            $table->json('documentacion')->nullable();
            $table->timestamp('archivado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('catalogos_compartidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->uuid('token')->unique();
            $table->timestamps();
        });

        Schema::create('perfiles_comerciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->unique()->constrained('establecimientos');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('foto_url', 2048)->nullable();
            $table->string('direccion')->nullable();
            $table->string('canton')->nullable();
            $table->string('sector')->nullable();
            $table->json('productos_buscados')->nullable();
            $table->boolean('tiene_refrigeracion')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_telefono', 40)->nullable();
            $table->string('contacto_email')->nullable();
            $table->json('canales_contacto')->nullable();
            $table->timestamp('contacto_autorizado_en')->nullable();
            $table->string('horario_proveedores')->nullable();
            $table->text('requisitos')->nullable();
            $table->string('modalidad_compra')->nullable();
            $table->unsignedInteger('pedido_inicial')->nullable();
            $table->text('devoluciones')->nullable();
            $table->string('decision_compra')->nullable();
            $table->timestamp('verificado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('categoria_establecimiento', function (Blueprint $table) {
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('establecimiento_id')->constrained('establecimientos');
            $table->primary(['categoria_id', 'establecimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_establecimiento');
        Schema::dropIfExists('perfiles_comerciales');
        Schema::dropIfExists('catalogos_compartidos');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('categorias');
    }
};
