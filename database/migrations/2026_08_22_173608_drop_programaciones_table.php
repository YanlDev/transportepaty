<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Programación eliminado por completo: sin utilidad para el negocio a
 * esta altura. `down()` no recrea la tabla porque su valor real eran los
 * datos, no el esquema — restaurar la estructura vacía no revierte nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('programaciones');
    }

    public function down(): void
    {
        // Irreversible a propósito: ver docblock de la clase.
    }
};
