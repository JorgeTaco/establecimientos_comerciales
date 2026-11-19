<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfiles_comerciales', function (Blueprint $table) {
            $table->string('horario_atencion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('perfiles_comerciales', function (Blueprint $table) {
            $table->dropColumn('horario_atencion');
        });
    }
};
