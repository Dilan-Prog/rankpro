<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoriaPlantillaCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Http\Controllers\Controller;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Support\Correo\Bloques;
use App\Support\Correo\RenderizadorCorreo;
use App\Support\Correo\Variables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Plantillas de correo: listado con métricas derivadas de los envíos y un
 * editor de bloques con vista previa en vivo. La forma de `bloques`/`marca`
 * y su validación viven en App\Support\Correo\Bloques; el HTML lo produce
 * App\Support\Correo\RenderizadorCorreo.
 */
class CorreoPlantillasController extends Controller
{
    public const ESTADOS = ['activa', 'archivada'];

    public function index(Request $request): View
    {
        $query = CorreoPlantilla::with(['creador', 'envios.destinatarios'])->orderByDesc('updated_at');

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->string('categoria'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('search')) {
            $buscar = trim((string) $request->string('search'));
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")->orWhere('asunto', 'like', "%{$buscar}%");
            });
        }

        $plantillas = $query->get();

        // Las KPIs son globales (no cambian con los filtros): describen el módulo,
        // no el subconjunto que se está viendo.
        $destinatariosEnviados = CorreoDestinatario::whereHas('envio', fn ($q) => $q->where('estado', EstadoEnvioCorreo::Enviado));
        $totalDestinatarios = (clone $destinatariosEnviados)->count();
        $abiertos = (clone $destinatariosEnviados)->where('aperturas', '>', 0)->count();

        return view('admin.correo.plantillas.index', [
            'pageTitle' => 'Plantillas de correo',
            'plantillas' => $plantillas->map(fn (CorreoPlantilla $p) => $p->toRow())->values(),
            'categorias' => CategoriaPlantillaCorreo::cases(),
            'estados' => self::ESTADOS,
            'filtros' => [
                'categoria' => (string) $request->string('categoria'),
                'estado' => (string) $request->string('estado'),
                'search' => trim((string) $request->string('search')),
            ],
            'kpis' => [
                'activas' => CorreoPlantilla::where('estado', 'activa')->count(),
                'total' => CorreoPlantilla::count(),
                'envios_mes' => CorreoEnvio::whereNotNull('enviado_en')
                    ->whereBetween('enviado_en', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'apertura' => $totalDestinatarios > 0 ? (int) round($abiertos / $totalDestinatarios * 100) : null,
                'abiertos' => $abiertos,
                'destinatarios' => $totalDestinatarios,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(array_column(CategoriaPlantillaCorreo::cases(), 'value'))],
            'asunto' => ['nullable', 'string', 'max:255'],
        ]);

        $plantilla = CorreoPlantilla::create([
            'nombre' => $data['nombre'],
            'categoria' => $data['categoria'],
            'estado' => 'activa',
            // Sin asunto explícito, el nombre sirve: el editor lo deja cambiar después.
            'asunto' => trim((string) ($data['asunto'] ?? '')) !== '' ? $data['asunto'] : $data['nombre'],
            'bloques' => Bloques::porDefecto(),
            'marca' => Bloques::marcaPorDefecto(),
            'creado_por' => Auth::id(),
        ]);

        $plantilla = $plantilla->fresh(['creador', 'envios.destinatarios']);

        return response()->json([
            'ok' => true,
            'row' => $plantilla->toRow(),
            'show_url' => route('admin.correo.plantillas.show', $plantilla),
        ], 201);
    }

    public function show(CorreoPlantilla $plantilla): View
    {
        $plantilla->load(['creador', 'envios.destinatarios']);

        return view('admin.correo.plantillas.show', [
            'pageTitle' => $plantilla->nombre,
            'plantilla' => $plantilla,
            // Estado inicial del editor: todo lo que el JS necesita viaja en un
            // solo JSON (misma idea que el banco de keywords de seo/show).
            'editorData' => [
                'plantilla' => [
                    'id' => $plantilla->id,
                    'nombre' => $plantilla->nombre,
                    'categoria' => $plantilla->categoria->value,
                    'estado' => $plantilla->estado,
                    'asunto' => $plantilla->asunto,
                    'bloques' => $plantilla->bloques ?? [],
                    'marca' => $plantilla->marcaCompleta(),
                    'html_personalizado' => (string) $plantilla->html_personalizado,
                ],
                'catalogoBloques' => Bloques::catalogo(),
                'tiposBloque' => Bloques::TIPOS,
                'variables' => Variables::catalogo(),
                'colores' => Bloques::COLORES,
                'logos' => Bloques::LOGOS,
                'categorias' => collect(CategoriaPlantillaCorreo::cases())
                    ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
                    ->values(),
                'estados' => self::ESTADOS,
                'urls' => [
                    'update' => route('admin.correo.plantillas.update', $plantilla),
                    'preview' => route('admin.correo.plantillas.preview'),
                    'duplicar' => route('admin.correo.plantillas.duplicar', $plantilla),
                    'index' => route('admin.correo.plantillas.index'),
                ],
            ],
            'categorias' => CategoriaPlantillaCorreo::cases(),
            'estados' => self::ESTADOS,
        ]);
    }

    /** Autosave del editor: llega el estado completo en cada guardado. */
    public function update(Request $request, CorreoPlantilla $plantilla): JsonResponse
    {
        $data = $this->validated($request);

        $plantilla->update($data);

        return response()->json([
            'ok' => true,
            'row' => $plantilla->fresh(['creador', 'envios.destinatarios'])->toRow(),
        ]);
    }

    public function destroy(CorreoPlantilla $plantilla): JsonResponse
    {
        $plantilla->delete();

        return response()->json(['ok' => true, 'deleted' => true]);
    }

    public function duplicar(CorreoPlantilla $plantilla): JsonResponse
    {
        $copia = $plantilla->replicate(['creado_por']);
        $copia->nombre = $plantilla->nombre.' (copia)';
        $copia->estado = 'activa';
        $copia->creado_por = Auth::id();
        $copia->save();

        $copia = $copia->fresh(['creador', 'envios.destinatarios']);

        return response()->json([
            'ok' => true,
            'row' => $copia->toRow(),
            'show_url' => route('admin.correo.plantillas.show', $copia),
        ], 201);
    }

    /**
     * Vista previa de lo que hay en el editor (sin guardar). Se renderiza lo
     * recibido, nunca lo guardado: la previa tiene que reflejar el tecleo.
     * Por eso la validación es laxa: un color a medio escribir no puede
     * tumbar la previa, se sustituye por el de la marca por defecto.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bloques' => ['nullable', 'array', 'max:40'],
            'marca' => ['nullable', 'array'],
            'html_personalizado' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        $bloques = array_values(array_filter(
            $data['bloques'] ?? [],
            fn ($b) => is_array($b) && in_array($b['tipo'] ?? null, Bloques::TIPOS, true)
        ));

        $marca = array_replace(
            Bloques::marcaPorDefecto(),
            array_filter($data['marca'] ?? [], fn ($v) => $v !== null && $v !== '')
        );

        if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $marca['color'])) {
            $marca['color'] = Bloques::marcaPorDefecto()['color'];
        }

        if (! in_array($marca['logo'], Bloques::LOGOS, true)) {
            $marca['logo'] = Bloques::marcaPorDefecto()['logo'];
        }

        $marca['redes'] = array_values(array_filter(
            is_array($marca['redes'] ?? null) ? $marca['redes'] : [],
            fn ($r) => is_array($r) && trim((string) ($r['nombre'] ?? '')) !== '' && trim((string) ($r['url'] ?? '')) !== ''
        ));

        // Solo claves del catálogo y solo escalares: lo demás se ignora.
        $recibidas = array_filter(
            array_intersect_key($data['variables'] ?? [], Variables::catalogo()),
            fn ($v) => is_scalar($v)
        );
        $variables = array_replace(RenderizadorCorreo::variablesEjemplo(), array_map('strval', $recibidas));

        $htmlLibre = trim((string) ($data['html_personalizado'] ?? ''));

        $html = RenderizadorCorreo::render($bloques, $marca, $variables, [
            'html_libre' => $htmlLibre !== '' ? $htmlLibre : null,
        ]);

        return response()->json(['html' => $html]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(array_column(CategoriaPlantillaCorreo::cases(), 'value'))],
            'estado' => ['required', Rule::in(self::ESTADOS)],
            'asunto' => ['required', 'string', 'max:255'],
            'html_personalizado' => ['nullable', 'string', 'max:200000'],
        ] + Bloques::reglas();

        $data = $request->validate($reglas, [
            'nombre.required' => 'La plantilla necesita un nombre.',
            'asunto.required' => 'El asunto no puede quedar vacío.',
            'bloques.max' => 'Una plantilla admite como máximo 40 bloques.',
            'marca.color.regex' => 'El color de marca debe ser hexadecimal (#RRGGBB).',
        ]);

        // Un HTML propio en blanco significa "modo bloques": se guarda null para
        // que esHtmlLibre() no dependa de espacios sueltos.
        $data['html_personalizado'] = trim((string) ($data['html_personalizado'] ?? '')) !== ''
            ? $data['html_personalizado']
            : null;

        $data['bloques'] = array_values($data['bloques'] ?? []);
        $data['marca'] = $data['marca'] ?? Bloques::marcaPorDefecto();

        return $data;
    }
}
