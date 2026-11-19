<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfiles_comerciales', fn (Blueprint $table) => $table->boolean('interes_encuesta')->nullable());
    }

    public function down(): void
    {
        Schema::table('perfiles_comerciales', fn (Blueprint $table) => $table->dropColumn('interes_encuesta'));
    }
};
