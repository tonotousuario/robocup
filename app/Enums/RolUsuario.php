<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Administrador = 'Administrador';
    case Juez = 'Juez';
    case Coach = 'Coach';
    case Piloto = 'Piloto';
}
