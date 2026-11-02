<?php

namespace App\Support\Inicio;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Atajos de Inicio. Un atajo solo se ofrece si el usuario tiene el permiso del módulo
 * y la ruta existe: un botón que lleva a un 403 o a un 404 es peor que no tenerlo.
 */
class AccesosRapidos
{
    /** @var list<array{permiso: string, ruta: string, etiqueta: string, icono: string}> */
    private const CATALOGO = [
        ['permiso' => 'clientes', 'ruta' => 'admin.clientes.index', 'etiqueta' => 'Clientes', 'icono' => 'fa-users'],
        ['permiso' => 'mi-espacio', 'ruta' => 'admin.mi-espacio.index', 'etiqueta' => 'Mi espacio', 'icono' => 'fa-circle-check'],
        ['permiso' => 'calendario', 'ruta' => 'admin.calendario.index', 'etiqueta' => 'Calendario', 'icono' => 'fa-calendar-days'],
        ['permiso' => 'agenda', 'ruta' => 'admin.agenda.index', 'etiqueta' => 'Agenda', 'icono' => 'fa-calendar-check'],
        ['permiso' => 'reportes', 'ruta' => 'admin.reportes.index', 'etiqueta' => 'Reportes', 'icono' => 'fa-file-lines'],
        ['permiso' => 'keywords', 'ruta' => 'admin.keywords.index', 'etiqueta' => 'Keywords', 'icono' => 'fa-key'],
    ];

    /** @return list<array{href: string, etiqueta: string, icono: string}> */
    public static function para(User $usuario): array
    {
        $accesos = [];

        foreach (self::CATALOGO as $a) {
            if ($usuario->tienePermiso($a['permiso']) && Route::has($a['ruta'])) {
                $accesos[] = ['href' => route($a['ruta']), 'etiqueta' => $a['etiqueta'], 'icono' => $a['icono']];
            }
        }

        return $accesos;
    }
}
