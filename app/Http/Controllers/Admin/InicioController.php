<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoTarea;
use App\Http\Controllers\Controller;
use App\Models\EventoCalendario;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Calendario\AgregadorEventos;
use App\Support\Inicio\AccesosRapidos;
use App\Support\Inicio\ActividadReciente;
use App\Support\ResumenAgencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * «Inicio»: resumen del día del usuario autenticado. No calcula nada propio de otros
 * módulos: reutiliza ResumenAgencia (KPIs y alertas, compartidos con el Dashboard),
 * AgregadorEventos (Calendario) y las tareas personales de «Mi espacio».
 *
 * Cada bloque se oculta si el usuario no tiene el permiso del módulo del que sale.
 */
class InicioController extends Controller
{
    /** Tareas pendientes que caben en la tarjeta; el resto se ve en Mi espacio. */
    private const TAREAS_VISIBLES = 8;

    public function index(Request $request): View
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $ahora = now();

        $verTareas = $usuario->tienePermiso('mi-espacio');

        return view('admin.inicio.index', [
            'pageTitle' => 'Inicio',
            'saludo' => self::saludoPara($ahora).', '.$this->primerNombre($usuario),
            'fechaLarga' => ucfirst($ahora->clone()->locale('es')->translatedFormat('l, j \d\e F \d\e Y')),
            'kpis' => $usuario->tienePermiso('finanzas') ? $this->kpisVisibles($usuario, $ahora) : [],
            'accesos' => AccesosRapidos::para($usuario),
            'alertas' => ResumenAgencia::alertas($ahora, $usuario),
            'agenda' => $this->agendaDeHoy($usuario, $ahora),
            'verTareas' => $verTareas,
            'tareas' => $verTareas ? $this->tareasPendientes($usuario) : collect(),
            'progreso' => $verTareas ? $this->progreso($usuario) : ['hechas' => 0, 'total' => 0, 'porcentaje' => 0],
            'actividad' => (new ActividadReciente)->ultimos($usuario),
        ]);
    }

    /**
     * Marca (o desmarca) una tarea personal. Mismo criterio de propiedad que Mi espacio:
     * una tarea ajena responde 404, no 403, para no revelar que existe.
     */
    public function completarTarea(Request $request, int $tarea): JsonResponse
    {
        $datos = $request->validate(['hecha' => ['required', 'boolean']]);

        $registro = Tarea::query()->where('user_id', $request->user()->id)->findOrFail($tarea);
        $registro->update(['estado' => $datos['hecha'] ? EstadoTarea::Completada : EstadoTarea::Pendiente]);

        return response()->json([
            'tarea' => MiEspacioController::serializar($registro->fresh('cliente:id,nombre')),
            'progreso' => $this->progreso($request->user()),
        ]);
    }

    /** Saludo por hora en la zona de la app: la madrugada comparte «Buenas noches». */
    public static function saludoPara(Carbon $ahora): string
    {
        return match (true) {
            $ahora->hour >= 5 && $ahora->hour < 12 => 'Buenos días',
            $ahora->hour >= 12 && $ahora->hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }

    private function primerNombre(User $usuario): string
    {
        $primero = explode(' ', trim((string) $usuario->name))[0];

        return $primero !== '' ? $primero : 'equipo';
    }

    /**
     * Los mismos cuatro KPIs del Dashboard. Un KPI que enlaza a un módulo sin permiso
     * se queda como tarjeta sin enlace (la cifra sigue siendo de finanzas).
     *
     * @return list<array<string, mixed>>
     */
    private function kpisVisibles(User $usuario, Carbon $ahora): array
    {
        $modulo = ['admin.finanzas.index' => 'finanzas', 'admin.clientes.index' => 'clientes', 'admin.ads.index' => 'ads'];

        return array_map(function (array $kpi) use ($usuario, $modulo) {
            foreach ($modulo as $ruta => $slug) {
                if ($kpi['href'] === route($ruta) && ! $usuario->tienePermiso($slug)) {
                    $kpi['href'] = null;
                }
            }

            return $kpi;
        }, ResumenAgencia::kpis($ahora));
    }

    /**
     * Eventos de hoy: manuales del Calendario más los que agrega AgregadorEventos
     * (reuniones, vencimientos de tareas, entregas y cobros), sin hora primero y luego por hora.
     * Cada uno se muestra solo si el usuario tiene el permiso del módulo de origen.
     *
     * @return list<array<string, mixed>>
     */
    private function agendaDeHoy(User $usuario, Carbon $ahora): array
    {
        $hoy = $ahora->clone()->startOfDay();

        $manuales = EventoCalendario::query()
            ->with('cliente:id,nombre')
            ->whereDate('fecha', $hoy->toDateString())
            ->get()
            ->map(function (EventoCalendario $e) {
                $evento = CalendarioController::serializar($e);
                // Un evento manual no tiene otro origen al que ir: su lugar es el Calendario.
                $evento['url'] = Route::has('admin.calendario.index') ? route('admin.calendario.index') : null;

                return $evento;
            });

        return $manuales
            ->merge(app(AgregadorEventos::class)->entre($hoy, $hoy, $usuario->id))
            ->filter(fn (array $e) => $usuario->tienePermiso($this->moduloDeEvento($e)))
            ->sortBy(fn (array $e) => ($e['hora'] ?? '00:00').'|'.$e['titulo'])
            ->values()
            ->all();
    }

    /** Módulo (slug de permiso) al que pertenece un evento según su origen. */
    private function moduloDeEvento(array $evento): string
    {
        return match ($evento['origen']) {
            'agenda' => 'agenda',
            'proyecto' => 'desarrollo',
            'finanza' => 'finanzas',
            'tarea' => ($evento['url'] ?? null) === route('admin.mi-espacio.index') ? 'mi-espacio' : 'desarrollo',
            default => 'calendario',
        };
    }

    /** @return Collection<int, Tarea> */
    private function tareasPendientes(User $usuario): Collection
    {
        return Tarea::query()
            ->where('user_id', $usuario->id)
            ->where('estado', '!=', EstadoTarea::Completada->value)
            ->with('cliente:id,nombre')
            // Lo que vence antes arriba; sin fecha al final, en el orden que el usuario le dio.
            ->orderByRaw('fecha_limite IS NULL')
            ->orderBy('fecha_limite')
            ->orderBy('orden')
            ->orderBy('id')
            ->limit(self::TAREAS_VISIBLES)
            ->get();
    }

    /** @return array{hechas: int, total: int, porcentaje: int} */
    private function progreso(User $usuario): array
    {
        $total = Tarea::query()->where('user_id', $usuario->id)->count();
        $hechas = Tarea::query()->where('user_id', $usuario->id)
            ->where('estado', EstadoTarea::Completada->value)->count();

        return [
            'hechas' => $hechas,
            'total' => $total,
            // División segura: sin tareas no hay porcentaje que inventar.
            'porcentaje' => $total > 0 ? (int) round($hechas / $total * 100) : 0,
        ];
    }
}
