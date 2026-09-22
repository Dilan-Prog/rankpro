<?php

namespace App\Http\Controllers\Api\V1\Blog;

use App\Enums\EstadoArticulo;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Articulo;
use App\Models\ArticuloServicio;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Clusters;
use App\Support\Contenido\RenderizadorMarkdown;
use App\Support\Servicios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD de artículos del blog. Admin\BlogController::store/update responden
 * con un redirect (flujo de formulario clásico, no AJAX), así que aquí se
 * reimplementa la validación y el guardado en vez de delegar — son las
 * mismas reglas que ese controlador (ver su docblock de clase: el Markdown
 * se renderiza al guardar, nunca en la vista pública).
 */
class BlogApiController extends ControladorApi
{
    private const PALABRAS_MINIMAS = 1500;

    public function index(Request $request): JsonResponse
    {
        return $this->listar(Articulo::query(), $request, new ConsultaOpciones(
            buscarEn: ['titulo', 'slug'],
            filtrosExactos: ['estado', 'cluster', 'autor_id'],
            ordenables: ['id', 'titulo', 'fecha_publicacion', 'created_at', 'updated_at'],
            incluibles: ['autor', 'relacionados'],
        ));
    }

    public function show(Request $request, Articulo $articulo): JsonResponse
    {
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            ['autor', 'relacionados']
        ));

        if ($incluir !== []) {
            $articulo->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($articulo, $incluir));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $articulo = DB::transaction(function () use ($data) {
            $servicios = $data['servicios'] ?? [];
            $relacionados = $data['relacionados'] ?? [];
            unset($data['servicios'], $data['relacionados']);

            $data = $this->conRender($data);

            if ($data['estado'] === EstadoArticulo::Publicado->value && empty($data['fecha_publicacion'])) {
                $data['fecha_publicacion'] = now()->toDateString();
            }

            $articulo = Articulo::create($data);
            $this->sincronizarServicios($articulo, $servicios);
            $this->sincronizarRelacionados($articulo, $relacionados);

            return $articulo;
        });

        return Respuesta::recurso(Serializador::modelo($articulo->fresh('autor')), 201);
    }

    public function update(Request $request, Articulo $articulo): JsonResponse
    {
        $data = $this->validated($request, $articulo);

        DB::transaction(function () use ($data, $articulo) {
            $servicios = $data['servicios'] ?? [];
            $relacionados = $data['relacionados'] ?? [];
            unset($data['servicios'], $data['relacionados']);

            $data = $this->conRender($data);

            // Un articulo ya publicado conserva su fecha de publicacion original.
            if ($articulo->fecha_publicacion) {
                unset($data['fecha_publicacion']);
            } elseif ($data['estado'] === EstadoArticulo::Publicado->value && empty($data['fecha_publicacion'])) {
                $data['fecha_publicacion'] = now()->toDateString();
            }

            $articulo->update($data);
            $this->sincronizarServicios($articulo, $servicios);
            $this->sincronizarRelacionados($articulo, $relacionados);
        });

        return Respuesta::recurso(Serializador::modelo($articulo->fresh('autor')));
    }

    public function destroy(Articulo $articulo): JsonResponse
    {
        $articulo->delete();

        return Respuesta::eliminado();
    }

    /** Atajo del contrato de la API: pone estado=publicado (con fecha_publicacion si no tenía). */
    public function publicar(Articulo $articulo): JsonResponse
    {
        $articulo->update([
            'estado' => EstadoArticulo::Publicado->value,
            'fecha_publicacion' => $articulo->fecha_publicacion ?? now()->toDateString(),
        ]);

        return Respuesta::recurso(Serializador::modelo($articulo->fresh('autor')));
    }

    public function previsualizar(Request $request, RenderizadorMarkdown $renderizador): JsonResponse
    {
        $data = $request->validate([
            'contenido' => ['nullable', 'string', 'max:200000'],
        ]);

        $render = $renderizador->procesar($data['contenido'] ?? '');

        return Respuesta::recurso([
            'html' => $render['html'],
            'toc' => $render['toc'],
            'palabras' => $render['palabras'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Articulo $articulo = null): array
    {
        return $request->validate([
            'slug' => [
                'required', 'string', 'alpha_dash', 'lowercase', 'max:120',
                Rule::unique('articulos', 'slug')->ignore($articulo?->id),
            ],
            'titulo' => ['required', 'string', 'max:255'],
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

    private function conRender(array $data): array
    {
        $render = app(RenderizadorMarkdown::class)->procesar($data['contenido']);

        $data['contenido_html'] = $render['html'];
        $data['toc'] = $render['toc'];
        $data['palabras'] = $render['palabras'];

        return $data;
    }

    /** @param  list<string>  $slugs */
    private function sincronizarServicios(Articulo $articulo, array $slugs): void
    {
        ArticuloServicio::where('articulo_id', $articulo->id)->delete();

        foreach (array_unique($slugs) as $slug) {
            ArticuloServicio::create(['articulo_id' => $articulo->id, 'servicio_slug' => $slug]);
        }
    }

    /** @param  list<int|string>  $ids */
    private function sincronizarRelacionados(Articulo $articulo, array $ids): void
    {
        $payload = [];
        $orden = 1;

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id === $articulo->id) {
                continue;
            }
            $payload[$id] = ['orden' => $orden++];
        }

        $articulo->relacionados()->sync($payload);
    }
}
