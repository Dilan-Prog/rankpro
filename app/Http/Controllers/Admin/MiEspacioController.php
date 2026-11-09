<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoTarea;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Tarea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «Mi espacio»: las tareas personales del usuario autenticado, en cinco vistas
 * intercambiables. Reutiliza la tabla `tareas` (las de Desarrollo cuelgan de un
 * proyecto; estas llevan `user_id` y no tienen proyecto).
 *
 * Toda consulta se acota a `user_id = auth()->id()`: una tarea ajena responde
 * 404 (no 403) para no revelar que existe.
 */
class MiEspacioController extends Controller
{
    public const VISTAS = ['tarjetas', 'kanban', 'tabla', 'calendario', 'timeline'];

    public function index(Request $request): View
    {
        $vista = $request->query('vista');

        $tareas = Tarea::query()
            ->where('user_id', $request->user()->id)
            ->with('cliente:id,nombre,empresa')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (Tarea $t) => self::serializar($t))
            ->values();

        return view('admin.mi-espacio.index', [
            'pageTitle' => 'Mi espacio',
            'vista' => in_array($vista, self::VISTAS, true) ? $vista : 'tarjetas',
            'tareas' => $tareas,
            'clientes' => Cliente::query()->ordenadoPorNombreComercial()->get(['id', 'nombre', 'empresa']),
            // La fecha «de hoy» la fija el servidor (zona de la app), no el reloj del navegador.
            'hoy' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        // Al final de su columna: el kanban ordena por `orden` dentro de cada estado.
        $datos['orden'] = (int) Tarea::query()
            ->where('user_id', $request->user()->id)
            ->where('estado', $datos['estado'])
            ->max('orden') + 1;

        $tarea = Tarea::create($datos + ['user_id' => $request->user()->id]);

        return response()->json(self::serializar($tarea->load('cliente:id,nombre,empresa')), 201);
    }

    public function update(Request $request, int $tarea): JsonResponse
    {
        $tarea = $this->propia($request, $tarea);
        $tarea->update($this->validar($request, parcial: true));

        return response()->json(self::serializar($tarea->fresh('cliente:id,nombre,empresa')));
    }

    /** Borrado suave: el toast «Deshacer» del front llama a `restaurar` durante unos segundos. */
    public function destroy(Request $request, int $tarea): JsonResponse
    {
        $this->propia($request, $tarea)->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Deshace un borrado. Solo tareas propias: una ajena (o inexistente) es 404,
     * igual que en el resto de acciones. Es idempotente si la tarea no estaba borrada.
     */
    public function restaurar(Request $request, int $tarea): JsonResponse
    {
        $registro = Tarea::withTrashed()->where('user_id', $request->user()->id)->findOrFail($tarea);
        $registro->restore();

        return response()->json(self::serializar($registro->fresh('cliente:id,nombre,empresa')));
    }

    /**
     * Arrastrar y soltar del kanban: cambia el estado y coloca la tarjeta en la
     * posición `orden` (0 = primera) de la columna destino, renumerando esa
     * columna para que el orden persista sin huecos ni empates.
     */
    public function mover(Request $request, int $tarea): JsonResponse
    {
        $tarea = $this->propia($request, $tarea);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(self::estados())],
            'orden' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        DB::transaction(function () use ($tarea, $datos, $request) {
            $ids = Tarea::query()
                ->where('user_id', $request->user()->id)
                ->where('estado', $datos['estado'])
                ->where('id', '!=', $tarea->id)
                ->orderBy('orden')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            array_splice($ids, min($datos['orden'], count($ids)), 0, [$tarea->id]);

            $tarea->update(['estado' => $datos['estado']]);

            foreach ($ids as $posicion => $id) {
                Tarea::query()->where('id', $id)->update(['orden' => $posicion]);
            }
        });

        return response()->json(self::serializar($tarea->fresh('cliente:id,nombre,empresa')));
    }

    /** Tarea del usuario autenticado o 404. */
    private function propia(Request $request, int $id): Tarea
    {
        return Tarea::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    /** @return array<int, string> */
    private static function estados(): array
    {
        return array_map(fn (EstadoTarea $e) => $e->value, EstadoTarea::cases());
    }

    /**
     * En la edición en línea de la tabla solo llega el campo que cambió, por eso
     * `parcial` usa `sometimes` en lugar de exigir todo.
     */
    private function validar(Request $request, bool $parcial = false): array
    {
        $req = $parcial ? 'sometimes' : 'required';
        // Comparar contra un campo ausente (edición en línea de solo el vencimiento) haría fallar la regla.
        $despuesDelInicio = $request->filled('fecha_inicio') ? ['after_or_equal:fecha_inicio'] : [];

        return $request->validate([
            'titulo' => [$req, 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'prioridad' => [$req, Rule::in(['alta', 'media', 'baja'])],
            'estado' => [$req, Rule::in(self::estados())],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_limite' => array_merge(['nullable', 'date'], $despuesDelInicio),
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
        ]);
    }

    public static function serializar(Tarea $t): array
    {
        return [
            'id' => $t->id,
            'titulo' => $t->titulo,
            'descripcion' => $t->descripcion,
            'prioridad' => $t->prioridad,
            'estado' => $t->estado->value,
            'fecha_inicio' => $t->fecha_inicio?->toDateString(),
            'fecha_limite' => $t->fecha_limite?->toDateString(),
            'cliente_id' => $t->cliente_id,
            'cliente' => $t->cliente?->nombre_comercial,
            'orden' => (int) $t->orden,
        ];
    }
}
