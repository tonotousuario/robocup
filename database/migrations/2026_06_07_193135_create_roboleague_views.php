<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW vista_posiciones AS
            SELECT
                i.id_inscripcion,
                r.id_robot,
                r.nombre AS robot,
                c.id_categoria,
                c.nombre AS categoria,
                MIN(t.tiempo_logrado + t.penalizacion_segundos) AS mejor_tiempo,
                COUNT(t.id_intento) AS intentos
            FROM inscripciones i
            JOIN robots r ON r.id_robot = i.id_robot
            JOIN categorias c ON c.id_categoria = r.id_categoria
            JOIN intentos_tiempos t ON t.id_inscripcion = i.id_inscripcion
            WHERE c.tipo_evaluacion = 'Tiempo'
            GROUP BY i.id_inscripcion, r.id_robot, r.nombre, c.id_categoria, c.nombre;
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW vista_emparejamientos AS
            SELECT
                e.id_encuentro,
                e.ronda,
                c.nombre AS categoria,
                pe.id_inscripcion,
                r.nombre AS robot,
                pe.puntos_obtenidos,
                pe.es_ganador
            FROM encuentros e
            JOIN categorias c ON c.id_categoria = e.id_categoria
            LEFT JOIN participantes_encuentro pe ON pe.id_encuentro = e.id_encuentro
            LEFT JOIN inscripciones i ON i.id_inscripcion = pe.id_inscripcion
            LEFT JOIN robots r ON r.id_robot = i.id_robot;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vista_posiciones');
        DB::statement('DROP VIEW IF EXISTS vista_emparejamientos');
    }
};
