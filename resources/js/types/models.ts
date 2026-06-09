export type Institucion = {
    id_institucion: number;
    nombre: string;
    tipo: 'Pública' | 'Privada' | 'Independiente';
    estado: string;
};

export type InstitucionRow = {
    id_institucion: number;
    nombre: string;
    tipo: 'Pública' | 'Privada' | 'Independiente';
    estado: string;
};

export type UsuarioRow = {
    id: number;
    name: string;
    apellidos: string;
    email: string;
    telefono: string | null;
    rol: 'Administrador' | 'Juez' | 'Coach' | 'Piloto';
};

export type RobotRow = {
    id_robot: number;
    nombre: string;
    categoria: string | null;
    institucion: string | null;
    piloto: string | null;
    id_piloto: number;
};

export type CategoriaOpcion = {
    id_categoria: number;
    nombre: string;
};

export type InstitucionOpcion = {
    id_institucion: number;
    nombre: string;
};

export type PilotoOpcion = {
    id: number;
    nombre: string;
};

export type InscripcionRow = {
    id_inscripcion: number;
    robot: string | null;
    categoria: string | null;
    piloto: string | null;
    tarifa: string | null;
    monto_pagado: string;
    estado_pago: 'Pendiente' | 'Pagado' | 'Cancelado';
};

export type RobotInscribible = {
    id_robot: number;
    nombre: string;
};

export type TarifaVigente = {
    descripcion: string;
    monto: string;
};

export type InspeccionEstado = 'Pendiente' | 'Aprobado' | 'Rechazado' | 'Descalificado';

export type InspeccionListItem = {
    id_inscripcion: number;
    robot: string | null;
    categoria: string | null;
    piloto: string | null;
    peso_maximo_g: number | null;
    dimensiones_maximas: string | null;
    estado_pago: string;
    estado: InspeccionEstado;
    inspeccion: {
        peso_medido_g: number;
        dimensiones_medidas: string;
        estado_aprobacion: string;
        observaciones: string | null;
    } | null;
};

export type IntentoTiempoData = {
    numero_vuelta: number;
    tiempo_logrado: string;
    penalizacion_segundos: string;
};

export type CompetidorTiempo = {
    id_inscripcion: number;
    robot: string | null;
    posicion: number | null;
    mejor_tiempo: string | null;
    intentos: IntentoTiempoData[];
};

export type CategoriaTiempoOpcion = {
    id_categoria: number;
    nombre: string;
};

export type AmonestacionRow = {
    id_amonestacion: number;
    id_inscripcion: number;
    motivo: string;
    numero_round: number | null;
};

export type ParticipanteBracket = {
    id_inscripcion: number;
    robot: string | null;
    es_ganador: boolean;
    reparacion_segundos_consumidos: number;
    reparacion_iniciada_en: string | null;
    reparacion_restante: number;
};

export type ReparacionActiva = {
    robot: string | null;
    reparacion_iniciada_en: string;
    reparacion_segundos_consumidos: number;
};

export type EncuentroBracket = {
    id_encuentro: number;
    ronda: string;
    id_encuentro_siguiente: number | null;
    participantes: ParticipanteBracket[];
    tipo_resultado: string | null;
    marcador: Record<string, number>;
    amonestaciones: AmonestacionRow[];
};

export type CategoriaCombateOpcion = {
    id_categoria: number;
    nombre: string;
};

export type CajaPorCategoria = {
    categoria: string;
    pagadas: number;
    recaudado: string;
};

export type ReporteCaja = {
    total_recaudado: string;
    pagadas: number;
    pendientes: number;
    canceladas: number;
    por_categoria: CajaPorCategoria[];
};

export type PosicionReporte = {
    id_inscripcion: number;
    robot: string | null;
    categoria: string | null;
    mejor_tiempo: string | null;
    intentos: number;
};

export type EmparejamientoVigente = {
    id_encuentro: number;
    categoria: string | null;
    ronda: string;
    robots: (string | null)[];
};

export type ProyeccionEnVivo = {
    id_encuentro: number;
    ronda: string;
    robots: (string | null)[];
    marcador: { robot: string | null; rounds: number }[];
    amonestaciones: { robot: string | null; motivo: string }[];
};

export type ProyeccionPosicion = {
    robot: string | null;
    categoria: string | null;
    mejor_tiempo: string | null;
};

export type Podio = {
    campeon: string | null;
    subcampeon: string | null;
    tercero: string | null;
};
