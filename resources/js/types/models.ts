export type Institucion = {
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
