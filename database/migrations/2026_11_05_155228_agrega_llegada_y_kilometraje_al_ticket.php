<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La llegada a la base deja de calcularse: alguien la registra, con el
     * día y el kilometraje. Taller anota el kilometraje al dar el OK de
     * salida. Con los dos, el ticket sabe cuánto recorrió.
     */
    public function up(): void
    {
        Schema::table('vueltas', function (Blueprint $table) {
            $table->date('llegada_el')->nullable()->after('fin');
            $table->unsignedInteger('km_llegada')->nullable()->after('llegada_el');
            $table->foreignId('llegada_por')->nullable()->after('km_llegada')->constrained('users')->nullOnDelete();
        });

        Schema::table('revisiones_taller', function (Blueprint $table) {
            $table->unsignedInteger('kilometraje')->nullable()->after('trabajos');
        });
    }

    public function down(): void
    {
        Schema::table('revisiones_taller', function (Blueprint $table) {
            $table->dropColumn('kilometraje');
        });

        Schema::table('vueltas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('llegada_por');
            $table->dropColumn(['llegada_el', 'km_llegada']);
        });
    }
};
