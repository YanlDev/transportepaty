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
        Schema::table('descanso_debidos', function (Blueprint $table) {
            $table->text('notas')->nullable()->after('dias_debidos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('descanso_debidos', function (Blueprint $table) {
            $table->dropColumn('notas');
        });
    }
};
