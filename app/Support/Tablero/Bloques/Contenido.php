<?php

namespace App\Support\Tablero\Bloques;

use App\Enums\EstadoProyecto;
use App\Models\ElementoContenido;
use App\Models\Proyecto;
use App\Support\Tablero\Contexto;
use Illuminate\Support\Carbon;

/**
 * Contenido (Content Manager) y Desarrollo del cliente. Como Audiencias, son trabajo en curso: el semáforo
 * mira presencia y tareas vencidas, no la antigüedad del dato.
 */
class Contenido extends Bloque
{
    public function clave(): string
    {
        return 'contenido';
    }

    public function titulo(): string
    {
        return 'Contenido y desarrollo';
    }

    public function icono(): string
    {
        return 'fa-folder-tree';
    }

    public function permisos(): array
    {
        return ['contenido', 'desarrollo'];
    }

    public function calcular(Contexto $ctx): array
    {
        $cifras = [];
        $hayDatos = false;
        $vencidas = 0;
        $enlace = null;
        $soloContenido = true;

        if ($ctx->puede('contenido')) {
            // cm_items usa borrado suave: el modelo ya excluye los eliminados.
            $fila = ElementoContenido::query()->where('cliente_id', $ctx->cliente->id)->selectRaw('count(*) as total, max(updated_at) as ultima')->first();
            $total = (int) ($fila->total ?? 0);
            $ultima = $total > 0 && $fila->ultima ? Carbon::parse($fila->ultima) : null;
            $hayDatos = $hayDatos || $total > 0;

            $cifras[] = self::cifra('Elementos de contenido', $total > 0 ? (string) $total : 'Ninguno', self::fecha($ultima), 'Contenido · Content Manager', ['nota' => $ultima ? 'La fecha es la de la última edición.' : null]);
            $enlace = ['url' => route('admin.contenido.index'), 'etiqueta' => 'Abrir Contenido'];
        }

        if ($ctx->puede('desarrollo')) {
            $soloContenido = false;
            $proyectos = Proyecto::query()
                ->where('cliente_id', $ctx->cliente->id)
                ->where('estado', EstadoProyecto::Activo->value)
                ->selectRaw('count(*) as total, avg(porcentaje_avance) as avance, max(updated_at) as ultima')
                ->first();
            $activos = (int) ($proyectos->total ?? 0);
            $ultima = $activos > 0 && $proyectos->ultima ? Carbon::parse($proyectos->ultima) : null;
            $tareas = $ctx->tablero()['tareas'];
            $vencidas = $tareas['vencidas'];
            $hayDatos = $hayDatos || $activos > 0 || $tareas['abiertas'] > 0;

            $cifras[] = self::cifra('Proyectos activos', $activos > 0 ? (string) $activos : 'Ninguno', self::fecha($ultima), 'Desarrollo · proyectos', ['nota' => $ultima ? 'La fecha es la de la última edición.' : null]);
            $cifras[] = self::cifra('Avance promedio', $activos > 0 ? round((float) $proyectos->avance).' %' : null, self::fecha($ultima), 'Desarrollo · porcentaje de avance de cada proyecto');
            $cifras[] = self::cifra('Tareas abiertas', (string) $tareas['abiertas'], self::fecha($ctx->hoy), 'Desarrollo · tareas de sus proyectos');
            $cifras[] = self::cifra('Tareas vencidas', (string) $vencidas, self::fecha($ctx->hoy), 'Desarrollo · tareas con fecha límite pasada');
            $enlace = ['url' => route('admin.desarrollo.index'), 'etiqueta' => 'Abrir Desarrollo'];
        }

        if (! $hayDatos) {
            $estado = self::NEUTRO;
            $motivo = 'Sin contenido, proyectos ni tareas para este cliente.';
        } elseif ($vencidas > 0) {
            $estado = self::AMARILLO;
            $motivo = $vencidas.' '.($vencidas === 1 ? 'tarea vencida' : 'tareas vencidas').'.';
        } else {
            $estado = self::VERDE;
            $motivo = 'Hay trabajo registrado y sin tareas vencidas.';
        }

        // Contenido trabaja con el cliente activo del menú; Desarrollo no.
        return $this->bloque($estado, $motivo, $cifras, $enlace, ['requiere_cliente_activo' => $soloContenido]);
    }
}
