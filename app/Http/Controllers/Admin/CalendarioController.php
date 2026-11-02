<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoEventoCalendario;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EventoCalendario;
use App\Support\Calendario\AgregadorEventos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Calendario de la agencia: eventos manuales propios (`eventos_calendario`) más
 * lo que agrega AgregadorEventos de Agenda, Desarrollo y Finanzas. Agenda sigue
 * siendo el motor de reservas; aquí solo se lee.
 */
class CalendarioController extends Controller
{
    /** Techo del rango pedido: la cuadrícula mensual necesita 42 días; el resto sería abuso del endpoint. */
    private const MAX_DIAS = 62;

    public function index(Request $request): View
    {
        $mes = $this->resolverMes($request->query('mes'));
        [$desde, $hasta] = $this->rangoDeCuadricula($mes);

        return view('admin.calendario.index', [
            'pageTitle' => 'Calendario',
            'mes' => $mes->format('Y-m'),
            'hoy' => now()->toDateString(),
            'eventos' => $this->eventosEntre($desde, $hasta, $request->user()->id),
            'tipos' => collect(TipoEventoCalendario::cases())->map(fn ($t) => ['valor' => $t->value, 'etiqueta' => $t->label()])->values(),
            'clientes' => Cliente::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /** JSON para navegar entre meses sin recargar. */
    public function eventos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $desde = Carbon::createFromFormat('!Y-m-d', $datos['desde']);
        $hasta = Carbon::createFromFormat('!Y-m-d', $datos['hasta']);

        if ($desde->diffInDays($hasta) > self::MAX_DIAS) {
            return response()->json(['message' => 'El rango es demasiado grande.'], 422);
        }

        return response()->json(['eventos' => $this->eventosEntre($desde, $hasta, $request->user()->id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoEventoCalendario::class)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['nullable', 'date_format:H:i'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ]);

        $evento = EventoCalendario::create($datos + ['user_id' => $request->user()->id]);

        return response()->json(self::serializar($evento->load('cliente:id,nombre')), 201);
    }

    /** Solo se borran eventos manuales: los agregados no existen en esta tabla. */
    public function destroy(EventoCalendario $evento): JsonResponse
    {
        $evento->delete();

        return response()->json(['deleted' => true]);
    }

    /** @return array<int, array<string, mixed>> */
    private function eventosEntre(Carbon $desde, Carbon $hasta, int $userId): array
    {
        $manuales = EventoCalendario::query()
            ->with('cliente:id,nombre')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (EventoCalendario $e) => self::serializar($e));

        return $manuales
            ->merge(app(AgregadorEventos::class)->entre($desde, $hasta, $userId))
            // Día, sin hora primero (todo el día), luego por hora.
            ->sortBy(fn (array $e) => $e['fecha'].' '.($e['hora'] ?? '00:00'))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function serializar(EventoCalendario $e): array
    {
        return [
            'id' => 'manual-'.$e->id,
            'evento_id' => $e->id,
            'titulo' => $e->titulo,
            'tipo' => $e->tipo->value,
            'fecha' => $e->fecha->toDateString(),
            // La columna `time` llega como HH:MM:SS; la pantalla trabaja con HH:MM.
            'hora' => $e->hora ? substr((string) $e->hora, 0, 5) : null,
            'cliente' => $e->cliente?->nombre,
            'origen' => 'manual',
            'editable' => true,
            'url' => null,
        ];
    }

    private function resolverMes(mixed $valor): Carbon
    {
        if (is_string($valor) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return Carbon::createFromFormat('!Y-m', $valor);
        }

        return now()->startOfMonth();
    }

    /**
     * Cuadrícula de 6 semanas que empieza en lunes: incluye los días de los
     * meses vecinos que se ven en pantalla, para que también muestren eventos.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangoDeCuadricula(Carbon $mes): array
    {
        $inicio = $mes->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);

        return [$inicio, $inicio->copy()->addDays(41)];
    }
}
