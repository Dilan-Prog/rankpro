<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoEventoCalendario;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EventoCalendario;
use App\Models\User;
use App\Support\Calendario\AgregadorEventos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Calendario de la agencia: eventos manuales propios (`eventos_calendario`) más
 * lo que agrega AgregadorEventos de Agenda, Desarrollo, Finanzas, Ads y SEO. Agenda sigue
 * siendo el motor de reservas; aquí solo se lee.
 */
class CalendarioController extends Controller
{
    /** Techo del rango pedido: la cuadrícula mensual necesita 42 días; el resto sería abuso del endpoint. */
    private const MAX_DIAS = 62;

    public function index(Request $request): View
    {
        return view('admin.calendario.index', ['pageTitle' => 'Calendario'] + $this->datosDeVista($request));
    }

    /**
     * Variables de la cuadrícula (parcial `_calendario`). Es pública porque la pestaña
     * «Calendario» de Inicio incrusta el mismo parcial y necesita los mismos datos.
     *
     * @return array<string, mixed>
     */
    public function datosDeVista(Request $request): array
    {
        $mes = $this->resolverMes($request->query('mes'));
        [$desde, $hasta] = $this->rangoDeCuadricula($mes);

        return [
            'mes' => $mes->format('Y-m'),
            'hoy' => now()->toDateString(),
            'eventos' => $this->eventosEntre($desde, $hasta, $request->user()),
            'tipos' => collect(TipoEventoCalendario::cases())->map(fn ($t) => ['valor' => $t->value, 'etiqueta' => $t->label()])->values(),
            // «Campaña» se alimenta sola desde Ads y SEO: no se ofrece al crear a mano.
            'tiposManuales' => collect(self::tiposManuales())->map(fn ($t) => ['valor' => $t->value, 'etiqueta' => $t->label()])->values(),
            'clientes' => Cliente::query()->orderBy('nombre')->get(['id', 'nombre']),
        ];
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

        return response()->json(['eventos' => $this->eventosEntre($desde, $hasta, $request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $evento = EventoCalendario::create($datos + ['user_id' => $request->user()->id]);

        return response()->json(self::serializar($evento->load('cliente:id,nombre'), $request->user()), 201);
    }

    /**
     * Edita un evento manual. Solo el creador o un administrador: los eventos
     * viven en una tabla compartida y sin esta regla cualquiera pisaría los de otro.
     */
    public function update(Request $request, EventoCalendario $evento): JsonResponse
    {
        abort_unless(self::puedeEditar($evento, $request->user()), 403, 'Solo quien creó el evento o un administrador puede editarlo.');

        // Un evento heredado de tipo «campana» puede conservarlo, pero no se puede asignar a otro.
        $evento->update($this->validar($request, $evento->tipo === TipoEventoCalendario::Campana));

        return response()->json(self::serializar($evento->fresh('cliente:id,nombre'), $request->user()));
    }

    /** Solo se borran eventos manuales: los agregados no existen en esta tabla. Misma regla de autoría que editar. */
    public function destroy(Request $request, EventoCalendario $evento): JsonResponse
    {
        abort_unless(self::puedeEditar($evento, $request->user()), 403, 'Solo quien creó el evento o un administrador puede eliminarlo.');

        $evento->delete();

        return response()->json(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $permitirCampana = false): array
    {
        $permitidos = array_map(fn (TipoEventoCalendario $t) => $t->value, self::tiposManuales($permitirCampana));

        return $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in($permitidos)],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['nullable', 'date_format:H:i'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ]);
    }

    /** @return array<int, TipoEventoCalendario> */
    private static function tiposManuales(bool $conCampana = false): array
    {
        return array_values(array_filter(
            TipoEventoCalendario::cases(),
            fn (TipoEventoCalendario $t) => $conCampana || $t !== TipoEventoCalendario::Campana
        ));
    }

    /** Creador o administrador. Un evento sin autor (anterior a `user_id`) lo edita cualquiera con acceso al módulo. */
    public static function puedeEditar(EventoCalendario $evento, User $usuario): bool
    {
        return $evento->user_id === null
            || $evento->user_id === $usuario->id
            || $usuario->role?->name === config('permisos.rol_admin');
    }

    /** @return array<int, array<string, mixed>> */
    private function eventosEntre(Carbon $desde, Carbon $hasta, User $usuario): array
    {
        $manuales = EventoCalendario::query()
            ->with('cliente:id,nombre')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->map(fn (EventoCalendario $e) => self::serializar($e, $usuario))
            // Una colección Eloquent vacía sigue siendo Eloquent tras map() y su merge() exige modelos.
            ->toBase();

        return $manuales
            ->merge(app(AgregadorEventos::class)->entre($desde, $hasta, $usuario->id))
            // Día, sin hora primero (todo el día), luego por hora.
            ->sortBy(fn (array $e) => $e['fecha'].' '.($e['hora'] ?? '00:00'))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function serializar(EventoCalendario $e, ?User $usuario = null): array
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
            'cliente_id' => $e->cliente_id,
            'origen' => 'manual',
            // Sin usuario (uso interno) se asume editable; con usuario, según autoría.
            'editable' => $usuario === null || self::puedeEditar($e, $usuario),
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
