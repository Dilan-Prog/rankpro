<?php

namespace App\Support\Calendario;

use App\Enums\EstadoProyecto;
use App\Enums\EstadoReunion;
use App\Enums\EstadoFinanza;
use App\Enums\EstadoTarea;
use App\Enums\TipoEventoCalendario;
use App\Models\AdsCampana;
use App\Models\AdsLanzamiento;
use App\Models\Finanza;
use App\Models\Proyecto;
use App\Models\Reunion;
use App\Models\SeoCampana;
use App\Models\Tarea;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Junta, de solo lectura, lo que otros módulos ya saben y que el Calendario
 * debe mostrar: reuniones de Agenda, vencimientos de tareas, entrega estimada
 * de proyectos, vencimientos de cobros y, como tipo «campaña», el inicio de las
campañas de Ads y SEO y el lanzamiento registrado en Ads. No copia nada a `eventos_calendario`:
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
        // Cada origen se agrega solo si el usuario puede abrir ese módulo: el calendario no debe ser
        // una puerta lateral a cobros, reuniones o entregas de módulos que su rol no ve. Sin usuario
        // no se sabe qué puede ver, así que no se agrega nada (igual que las campañas).
        $usuario = $userId !== null ? User::find($userId) : null;
        if ($usuario === null) {
            return collect();
        }

        $eventos = collect();

        if ($usuario->tienePermiso('agenda')) {
            $eventos = $eventos->merge($this->reuniones($desde, $hasta));
        }

        // Tareas con proyecto pertenecen a Desarrollo; las personales (sin proyecto) a Mi espacio.
        $eventos = $eventos->merge($this->tareas(
            $desde, $hasta, $userId,
            $usuario->tienePermiso('desarrollo'),
            $usuario->tienePermiso('mi-espacio'),
        ));

        if ($usuario->tienePermiso('desarrollo')) {
            $eventos = $eventos->merge($this->proyectos($desde, $hasta));
        }

        if ($usuario->tienePermiso('finanzas')) {
            $eventos = $eventos->merge($this->finanzas($desde, $hasta));
        }

        return $eventos
            ->merge($this->campanas($desde, $hasta, $userId))
            ->values();
    }

    private function reuniones(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        // `inicia_en` se guarda en la zona de la app, igual que $desde/$hasta (now() de Laravel).
        return Reunion::query()
            ->with('cliente:id,nombre,empresa')
            ->where('estado', '!=', EstadoReunion::Cancelada->value)
            ->whereBetween('inicia_en', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->get()
            ->map(fn (Reunion $r) => $this->evento(
                id: 'reunion-'.$r->id,
                titulo: 'Reunión con '.$r->nombre,
                tipo: TipoEventoCalendario::Reunion,
                fecha: $r->inicia_en->toDateString(),
                hora: $r->inicia_en->format('H:i'),
                cliente: $r->cliente?->nombre_comercial,
                clienteId: $r->cliente_id,
                origen: 'agenda',
                url: route('admin.agenda.index'),
            ));
    }

    private function tareas(CarbonInterface $desde, CarbonInterface $hasta, ?int $userId, bool $conProyecto, bool $personales): Collection
    {
        if (! $conProyecto && ! $personales) {
            return collect();
        }

        return Tarea::query()
            ->with(['proyecto:id,nombre,cliente_id', 'proyecto.cliente:id,nombre,empresa', 'cliente:id,nombre,empresa'])
            ->where('estado', '!=', EstadoTarea::Completada->value)
            ->whereBetween('fecha_limite', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($q) use ($userId, $conProyecto, $personales) {
                if ($conProyecto) {
                    $q->whereNotNull('proyecto_id');
                }
                if ($personales && $userId !== null) {
                    $q->orWhere(fn ($p) => $p->whereNull('proyecto_id')->where('user_id', $userId));
                }
            })
            ->get()
            ->map(fn (Tarea $t) => $this->evento(
                id: 'tarea-'.$t->id,
                titulo: 'Vence: '.$t->titulo,
                tipo: TipoEventoCalendario::Entrega,
                fecha: $t->fecha_limite->toDateString(),
                hora: null,
                cliente: $t->cliente?->nombre_comercial ?? $t->proyecto?->cliente?->nombre_comercial,
                clienteId: $t->cliente?->id ?? $t->proyecto?->cliente_id,
                origen: 'tarea',
                url: $t->proyecto_id ? route('admin.desarrollo.show', $t->proyecto_id) : route('admin.mi-espacio.index'),
            ));
    }

    private function proyectos(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return Proyecto::query()
            ->with('cliente:id,nombre,empresa')
            ->whereNotIn('estado', [EstadoProyecto::Cerrado->value, EstadoProyecto::Cancelado->value])
            ->whereBetween('fecha_entrega_estimada', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (Proyecto $p) => $this->evento(
                id: 'proyecto-'.$p->id,
                titulo: 'Entrega: '.$p->nombre,
                tipo: TipoEventoCalendario::Entrega,
                fecha: $p->fecha_entrega_estimada->toDateString(),
                hora: null,
                cliente: $p->cliente?->nombre_comercial,
                clienteId: $p->cliente_id,
                origen: 'proyecto',
                url: route('admin.desarrollo.show', $p->id),
            ));
    }

    private function finanzas(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        // Un cobro ya pagado deja de ser un pendiente del calendario.
        return Finanza::query()
            ->with('cliente:id,nombre,empresa')
            ->where('estado', '!=', EstadoFinanza::Pagado->value)
            ->whereBetween('fecha_vencimiento', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (Finanza $f) => $this->evento(
                id: 'finanza-'.$f->id,
                titulo: 'Vence pago: '.$f->concepto,
                tipo: TipoEventoCalendario::Pago,
                fecha: $f->fecha_vencimiento->toDateString(),
                hora: null,
                cliente: $f->cliente?->nombre_comercial,
                clienteId: $f->cliente_id,
                origen: 'finanza',
                url: route('admin.finanzas.index'),
            ));
    }

    /**
     * Fechas de campañas, de solo lectura. Cada origen se muestra solo si el
     * usuario tiene el permiso de ese módulo (`ads`, `seo`): el calendario no
     * debe ser una puerta lateral a datos de módulos que el rol no puede abrir.
     * Sin usuario no se sabe qué puede ver, así que no se agrega nada.
     *
     * Solo hay fechas reales: `fecha_inicio` de AdsCampana y SeoCampana, y
     * `fecha_lanzamiento` de AdsLanzamiento (hito de la fase de lanzamiento).
     * SEO no tiene hito de lanzamiento propio.
     */
    private function campanas(CarbonInterface $desde, CarbonInterface $hasta, ?int $userId): Collection
    {
        $usuario = $userId !== null ? User::find($userId) : null;
        if ($usuario === null) {
            return collect();
        }

        $rango = [$desde->toDateString(), $hasta->toDateString()];
        $eventos = collect();

        if ($usuario->tienePermiso('ads')) {
            // Las campañas con borrado suave quedan fuera por su scope global (también en whereHas).
            $eventos = $eventos
                ->merge(AdsCampana::query()
                    ->with('cliente:id,nombre,empresa')
                    ->whereBetween('fecha_inicio', $rango)
                    ->get()
                    ->map(fn (AdsCampana $c) => $this->evento(
                        id: 'ads-inicio-'.$c->id,
                        titulo: 'Inicio de campaña Ads: '.$c->nombre,
                        tipo: TipoEventoCalendario::Campana,
                        fecha: $c->fecha_inicio->toDateString(),
                        hora: null,
                        cliente: $c->cliente?->nombre_comercial,
                        clienteId: $c->cliente_id,
                        origen: 'ads',
                        url: route('admin.ads.show', $c->id),
                    )))
                ->merge(AdsLanzamiento::query()
                    ->with('adsCampana.cliente:id,nombre,empresa')
                    ->whereHas('adsCampana')
                    ->whereBetween('fecha_lanzamiento', $rango)
                    ->get()
                    ->map(fn (AdsLanzamiento $l) => $this->evento(
                        id: 'ads-lanzamiento-'.$l->id,
                        titulo: 'Lanzamiento Ads: '.$l->adsCampana->nombre,
                        tipo: TipoEventoCalendario::Campana,
                        fecha: $l->fecha_lanzamiento->toDateString(),
                        hora: null,
                        cliente: $l->adsCampana->cliente?->nombre_comercial,
                        clienteId: $l->adsCampana->cliente_id,
                        origen: 'ads',
                        url: route('admin.ads.show', $l->ads_campana_id),
                    )));
        }

        if ($usuario->tienePermiso('seo')) {
            $eventos = $eventos->merge(SeoCampana::query()
                ->with('cliente:id,nombre,empresa')
                ->whereBetween('fecha_inicio', $rango)
                ->get()
                ->map(fn (SeoCampana $c) => $this->evento(
                    id: 'seo-inicio-'.$c->id,
                    titulo: 'Inicio de campaña SEO: '.$c->nombre,
                    tipo: TipoEventoCalendario::Campana,
                    fecha: $c->fecha_inicio->toDateString(),
                    hora: null,
                    cliente: $c->cliente?->nombre_comercial,
                    clienteId: $c->cliente_id,
                    origen: 'seo',
                    url: route('admin.seo.show', $c->id),
                )));
        }

        return $eventos;
    }

    /** @return array<string, mixed> */
    private function evento(string $id, string $titulo, TipoEventoCalendario $tipo, string $fecha, ?string $hora, ?string $cliente, string $origen, string $url, ?int $clienteId = null): array
    {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'tipo' => $tipo->value,
            'fecha' => $fecha,
            'hora' => $hora,
            'cliente' => $cliente,
            // Permite al Calendario acotar por cliente activo; null = evento de la agencia (siempre visible).
            'cliente_id' => $clienteId,
            'origen' => $origen,
            'editable' => false,
            'url' => $url,
        ];
    }
}
