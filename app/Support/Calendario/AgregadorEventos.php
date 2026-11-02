<?php

namespace App\Support\Calendario;

use App\Enums\EstadoProyecto;
use App\Enums\EstadoReunion;
use App\Enums\EstadoFinanza;
use App\Enums\EstadoTarea;
use App\Enums\TipoEventoCalendario;
use App\Models\Finanza;
use App\Models\Proyecto;
use App\Models\Reunion;
use App\Models\Tarea;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Junta, de solo lectura, lo que otros módulos ya saben y que el Calendario
 * debe mostrar: reuniones de Agenda, vencimientos de tareas, entrega estimada
 * de proyectos y vencimientos de cobros. No copia nada a `eventos_calendario`:
 * así un cambio en el módulo de origen se refleja solo y no hay datos duplicados.
 *
 * Cada evento es un arreglo con la misma forma que los manuales (ver
 * CalendarioController) pero con `editable => false` y un `url` al módulo de origen.
 */
class AgregadorEventos
{
    /**
     * @param  int|null  $userId  Usuario que mira el calendario: sus tareas personales
     *                            (sin proyecto) entran; las personales de otros no.
     * @return Collection<int, array<string, mixed>>
     */
    public function entre(CarbonInterface $desde, CarbonInterface $hasta, ?int $userId = null): Collection
    {
        return collect()
            ->merge($this->reuniones($desde, $hasta))
            ->merge($this->tareas($desde, $hasta, $userId))
            ->merge($this->proyectos($desde, $hasta))
            ->merge($this->finanzas($desde, $hasta))
            ->values();
    }

    private function reuniones(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        // `inicia_en` se guarda en la zona de la app, igual que $desde/$hasta (now() de Laravel).
        return Reunion::query()
            ->with('cliente:id,nombre')
            ->where('estado', '!=', EstadoReunion::Cancelada->value)
            ->whereBetween('inicia_en', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->get()
            ->map(fn (Reunion $r) => $this->evento(
                id: 'reunion-'.$r->id,
                titulo: 'Reunión con '.$r->nombre,
                tipo: TipoEventoCalendario::Reunion,
                fecha: $r->inicia_en->toDateString(),
                hora: $r->inicia_en->format('H:i'),
                cliente: $r->cliente?->nombre,
                origen: 'agenda',
                url: route('admin.agenda.index'),
            ));
    }

    private function tareas(CarbonInterface $desde, CarbonInterface $hasta, ?int $userId): Collection
    {
        return Tarea::query()
            ->with(['proyecto:id,nombre,cliente_id', 'proyecto.cliente:id,nombre', 'cliente:id,nombre'])
            ->where('estado', '!=', EstadoTarea::Completada->value)
            ->whereBetween('fecha_limite', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($q) use ($userId) {
                $q->whereNotNull('proyecto_id');
                if ($userId !== null) {
                    $q->orWhere('user_id', $userId);
                }
            })
            ->get()
            ->map(fn (Tarea $t) => $this->evento(
                id: 'tarea-'.$t->id,
                titulo: 'Vence: '.$t->titulo,
                tipo: TipoEventoCalendario::Entrega,
                fecha: $t->fecha_limite->toDateString(),
                hora: null,
                cliente: $t->cliente?->nombre ?? $t->proyecto?->cliente?->nombre,
                origen: 'tarea',
                url: $t->proyecto_id ? route('admin.desarrollo.show', $t->proyecto_id) : route('admin.mi-espacio.index'),
            ));
    }

    private function proyectos(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return Proyecto::query()
            ->with('cliente:id,nombre')
            ->whereNotIn('estado', [EstadoProyecto::Cerrado->value, EstadoProyecto::Cancelado->value])
            ->whereBetween('fecha_entrega_estimada', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (Proyecto $p) => $this->evento(
                id: 'proyecto-'.$p->id,
                titulo: 'Entrega: '.$p->nombre,
                tipo: TipoEventoCalendario::Entrega,
                fecha: $p->fecha_entrega_estimada->toDateString(),
                hora: null,
                cliente: $p->cliente?->nombre,
                origen: 'proyecto',
                url: route('admin.desarrollo.show', $p->id),
            ));
    }

    private function finanzas(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        // Un cobro ya pagado deja de ser un pendiente del calendario.
        return Finanza::query()
            ->with('cliente:id,nombre')
            ->where('estado', '!=', EstadoFinanza::Pagado->value)
            ->whereBetween('fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (Finanza $f) => $this->evento(
                id: 'finanza-'.$f->id,
                titulo: 'Vence pago: '.$f->concepto,
                tipo: TipoEventoCalendario::Pago,
                fecha: $f->fecha_vencimiento->toDateString(),
                hora: null,
                cliente: $f->cliente?->nombre,
                origen: 'finanza',
                url: route('admin.finanzas.index'),
            ));
    }

    /** @return array<string, mixed> */
    private function evento(string $id, string $titulo, TipoEventoCalendario $tipo, string $fecha, ?string $hora, ?string $cliente, string $origen, string $url): array
    {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'tipo' => $tipo->value,
            'fecha' => $fecha,
            'hora' => $hora,
            'cliente' => $cliente,
            'origen' => $origen,
            'editable' => false,
            'url' => $url,
        ];
    }
}
