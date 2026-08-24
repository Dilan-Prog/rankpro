<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoArticulo;
use App\Http\Controllers\Controller;
use App\Models\Articulo;
use App\Models\ArticuloServicio;
use App\Models\User;
use App\Support\Clusters;
use App\Support\Contenido\RenderizadorMarkdown;
use App\Support\Servicios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD de articulos del blog.
 *
 * El Markdown se renderiza AQUI, al guardar, y se persiste en contenido_html /
 * toc / palabras. La vista publica nunca vuelve a pasar por CommonMark.
 */
class BlogController extends Controller
{
    /** Estandar editorial del plan: por debajo de esto el articulo no compite. */
    private const PALABRAS_MINIMAS = 1500;

    public function index(Request $request): View
    {
        $articulos = Articulo::with('autor')
            ->orderByRaw('fecha_publicacion IS NULL DESC')
            ->orderByDesc('fecha_publicacion')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $filas = collect($articulos->items())->map(function (Articulo $a) {
            $cluster = $a->clusterInfo();

            return [
                'id' => $a->id,
                'slug' => $a->slug,
                'titulo' => $a->titulo,
                'cluster' => $a->cluster,
                'cluster_nombre' => $cluster['nombre_corto'] ?? $a->cluster,
                'estado' => $a->estado->value,
                'autor' => $a->autor?->name ?? '—',
                'fecha_publicacion' => $a->fecha_publicacion?->format('d/m/Y'),
                'palabras' => (int) $a->palabras,
                'avisos' => $this->avisosSeo($a),
            ];
        });

        return view('admin.blog.index', [
            'pageTitle' => 'Blog',
            'articulos' => $articulos,
            'filas' => $filas,
            'clusters' => Clusters::navegacion(),
            'estados' => EstadoArticulo::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.create', [
            'pageTitle' => 'Nuevo Artículo',
            'articulo' => null,
        ] + $this->datosFormulario());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $articulo = DB::transaction(function () use ($data) {
            $servicios = $data['servicios'] ?? [];
            $relacionados = $data['relacionados'] ?? [];
            unset($data['servicios'], $data['relacionados']);

            $data = $this->conRender($data);

            // Regla de negocio: publicar sin fecha equivale a publicar hoy.
            if ($data['estado'] === EstadoArticulo::Publicado->value && empty($data['fecha_publicacion'])) {
                $data['fecha_publicacion'] = now()->toDateString();
            }

            $articulo = Articulo::create($data);
            $this->sincronizarServicios($articulo, $servicios);
            $this->sincronizarRelacionados($articulo, $relacionados);

            return $articulo;
        });

        return redirect()->route('admin.blog.index')
            ->with('status', "Artículo \"{$articulo->titulo}\" creado correctamente.");
    }

    public function edit(Articulo $articulo): View
    {
        $articulo->load('relacionados');

        return view('admin.blog.edit', [
            'pageTitle' => 'Editar Artículo',
            'articulo' => $articulo,
        ] + $this->datosFormulario($articulo));
    }

    public function update(Request $request, Articulo $articulo): RedirectResponse
    {
        $data = $this->validated($request, $articulo);

        DB::transaction(function () use ($data, $articulo) {
            $servicios = $data['servicios'] ?? [];
            $relacionados = $data['relacionados'] ?? [];
            unset($data['servicios'], $data['relacionados']);

            $data = $this->conRender($data);

            // Un articulo ya publicado conserva su fecha de publicacion original:
            // reescribirla al editar falsea la antiguedad del contenido.
            if ($articulo->fecha_publicacion) {
                unset($data['fecha_publicacion']);
            } elseif ($data['estado'] === EstadoArticulo::Publicado->value && empty($data['fecha_publicacion'])) {
                $data['fecha_publicacion'] = now()->toDateString();
            }

            // fecha_actualizacion NO se toca automaticamente: solo cambia si el
            // editor la cambia en el formulario (ver comentario de la migracion).
            $articulo->update($data);
            $this->sincronizarServicios($articulo, $servicios);
            $this->sincronizarRelacionados($articulo, $relacionados);
        });

        return redirect()->route('admin.blog.index')
            ->with('status', "Artículo \"{$articulo->titulo}\" actualizado correctamente.");
    }

    public function destroy(Articulo $articulo): RedirectResponse
    {
        $estabaPublicado = $articulo->estado->esPublico();
        $titulo = $articulo->titulo;

        $articulo->delete();

        $mensaje = "Artículo \"{$titulo}\" eliminado.";
        if ($estabaPublicado) {
            $mensaje .= ' Estaba publicado: su URL deja de servirse y sale del sitemap.';
        }

        return redirect()->route('admin.blog.index')->with('status', $mensaje);
    }

    /**
     * Vista previa en vivo del Markdown del formulario.
     *
     * Devuelve JSON (nunca una vista con HTML crudo) y solo es alcanzable con
     * sesion iniciada: la ruta vive dentro del grupo admin con middleware auth.
     */
    public function previsualizar(Request $request, RenderizadorMarkdown $renderizador): JsonResponse
    {
        $data = $request->validate([
            'contenido' => ['nullable', 'string', 'max:200000'],
        ]);

        $render = $renderizador->procesar($data['contenido'] ?? '');

        return response()->json([
            'html' => $render['html'],
            'toc' => $render['toc'],
            'palabras' => $render['palabras'],
        ]);
    }

    // ------------------------------------------------------------------
    // Interno
    // ------------------------------------------------------------------

    private function validated(Request $request, ?Articulo $articulo = null): array
    {
        return $request->validate([
            'slug' => [
                'required', 'string', 'alpha_dash', 'lowercase', 'max:120',
                Rule::unique('articulos', 'slug')->ignore($articulo?->id),
            ],
            'titulo' => ['required', 'string', 'max:255'],
            // 60 / 70-160 no son cifras arbitrarias: es lo que Google muestra
            // sin truncar en la SERP, y tests/Feature/SeoTest.php lo verifica.
            'meta_title' => ['required', 'string', 'max:60'],
            'meta_description' => ['required', 'string', 'min:70', 'max:160'],
            'resumen' => ['required', 'string', 'max:500'],
            'contenido' => ['required', 'string', 'min:500'],
            'cluster' => ['required', Rule::in(Clusters::slugs())],
            'estado' => ['required', Rule::enum(EstadoArticulo::class)],
            'autor_id' => ['required', 'integer', 'exists:users,id'],
            'fecha_publicacion' => ['nullable', 'date'],
            'fecha_actualizacion' => ['nullable', 'date', 'after_or_equal:fecha_publicacion'],
            'servicios' => ['nullable', 'array'],
            'servicios.*' => [Rule::in(array_keys(Servicios::todos()))],
            'relacionados' => ['nullable', 'array'],
            'relacionados.*' => ['integer', 'exists:articulos,id'],
            'imagen_destacada' => ['nullable', 'string', 'max:255'],
            'imagen_alt' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255'],
        ], [
            'meta_title.max' => 'El meta title no puede pasar de 60 caracteres: Google lo truncaría en la SERP.',
            'meta_description.min' => 'La meta description necesita al menos 70 caracteres.',
            'meta_description.max' => 'La meta description no puede pasar de 160 caracteres: Google la truncaría.',
            'contenido.min' => 'El contenido es demasiado corto para publicarse.',
        ]);
    }

    /** Renderiza el Markdown y añade contenido_html, toc y palabras. */
    private function conRender(array $data): array
    {
        $render = app(RenderizadorMarkdown::class)->procesar($data['contenido']);

        $data['contenido_html'] = $render['html'];
        $data['toc'] = $render['toc'];
        $data['palabras'] = $render['palabras'];

        return $data;
    }

    /** @param list<string> $slugs */
    private function sincronizarServicios(Articulo $articulo, array $slugs): void
    {
        // Borrar y reinsertar: la tabla no tiene mas columnas que el par
        // (articulo_id, servicio_slug), asi que un diff no aporta nada.
        ArticuloServicio::where('articulo_id', $articulo->id)->delete();

        foreach (array_unique($slugs) as $slug) {
            ArticuloServicio::create([
                'articulo_id' => $articulo->id,
                'servicio_slug' => $slug,
            ]);
        }
    }

    /** @param list<int|string> $ids */
    private function sincronizarRelacionados(Articulo $articulo, array $ids): void
    {
        $payload = [];
        $orden = 1;

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id === $articulo->id) {
                continue; // un articulo no se relaciona consigo mismo
            }
            $payload[$id] = ['orden' => $orden++];
        }

        $articulo->relacionados()->sync($payload);
    }

    /**
     * Avisos SEO por fila del indice: lo que evita publicar 30 articulos con
     * metadatos que Google va a truncar.
     *
     * @return list<string>
     */
    private function avisosSeo(Articulo $articulo): array
    {
        $avisos = [];

        $lenTitle = mb_strlen((string) $articulo->meta_title);
        if ($lenTitle > 60) {
            $avisos[] = "Meta title de {$lenTitle} caracteres (máx. 60).";
        }

        $lenDesc = mb_strlen((string) $articulo->meta_description);
        if ($lenDesc < 70 || $lenDesc > 160) {
            $avisos[] = "Meta description de {$lenDesc} caracteres (rango 70-160).";
        }

        if ((int) $articulo->palabras < self::PALABRAS_MINIMAS) {
            $avisos[] = "Solo {$articulo->palabras} palabras (mínimo editorial ".self::PALABRAS_MINIMAS.').';
        }

        return $avisos;
    }

    /** Datos compartidos por create() y edit(). */
    private function datosFormulario(?Articulo $articulo = null): array
    {
        return [
            'clusters' => Clusters::todos(),
            'servicios' => Servicios::navegacion(),
            'estados' => EstadoArticulo::cases(),
            'autores' => User::orderBy('name')->pluck('name', 'id'),
            'serviciosSeleccionados' => $articulo?->slugsServicios() ?? [],
            'relacionadosSeleccionados' => $articulo
                ? $articulo->relacionados->pluck('id')->all()
                : [],
            'candidatosRelacionados' => Articulo::query()
                ->when($articulo, fn ($q) => $q->whereKeyNot($articulo->id))
                ->orderBy('titulo')
                ->get(['id', 'titulo', 'cluster']),
        ];
    }
}
