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
        // T1: bloquear inspección si la inscripción no está Pagada
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_validar_pago_inspeccion()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM inscripciones
                    WHERE id_inscripcion = NEW.id_inscripcion
                      AND estado_pago = 'Pagado'
                ) THEN
                    RAISE EXCEPTION 'La inscripcion % no esta Pagada; no puede inspeccionarse', NEW.id_inscripcion;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validar_pago_inspeccion
            BEFORE INSERT ON inspecciones_checklist
            FOR EACH ROW EXECUTE FUNCTION fn_validar_pago_inspeccion();
        SQL);

        // T2: bloquear participante de encuentro si no hay inspeccion Aprobado
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_validar_aprobacion_encuentro()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM inspecciones_checklist
                    WHERE id_inscripcion = NEW.id_inscripcion
                      AND estado_aprobacion = 'Aprobado'
                ) THEN
                    RAISE EXCEPTION 'La inscripcion % no tiene inspeccion Aprobado; no puede competir', NEW.id_inscripcion;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validar_aprobacion_encuentro
            BEFORE INSERT ON participantes_encuentro
            FOR EACH ROW EXECUTE FUNCTION fn_validar_aprobacion_encuentro();
        SQL);

        // T3: bloquear registro de tiempos si no hay inspeccion Aprobado
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_validar_aprobacion_tiempo()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM inspecciones_checklist
                    WHERE id_inscripcion = NEW.id_inscripcion
                      AND estado_aprobacion = 'Aprobado'
                ) THEN
                    RAISE EXCEPTION 'La inscripcion % no tiene inspeccion Aprobado; no puede registrar tiempos', NEW.id_inscripcion;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_validar_aprobacion_tiempo
            BEFORE INSERT ON intentos_tiempos
            FOR EACH ROW EXECUTE FUNCTION fn_validar_aprobacion_tiempo();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_pago_inspeccion ON inspecciones_checklist');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_validar_pago_inspeccion');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_aprobacion_encuentro ON participantes_encuentro');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_validar_aprobacion_encuentro');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_validar_aprobacion_tiempo ON intentos_tiempos');
        DB::unprepared('DROP FUNCTION IF EXISTS fn_validar_aprobacion_tiempo');
    }
};
