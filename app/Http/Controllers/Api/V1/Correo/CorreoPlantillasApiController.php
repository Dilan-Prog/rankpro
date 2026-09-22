<?php

namespace App\Http\Controllers\Api\V1\Correo;

use App\Enums\CategoriaPlantillaCorreo;
use App\Http\Controllers\Admin\CorreoPlantillasController;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\CorreoPlantilla;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Correo\Bloques;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD de plantillas de correo. `preview` se delega en
 * Admin\CorreoPlantillasController::preview (misma validación laxa contra lo
 * tecleado, mismo RenderizadorCorreo) para no duplicar esa normalización.
 */
class CorreoPlantillasApiController extends ControladorApi
{
    public function __construct(private CorreoPlantillasController $delegadoPreview)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->listar(CorreoPlantilla::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre', 'asunto'],
            filtrosExactos: ['categoria', 'estado'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: ['creador', 'envios'],
        ));
    }

    public function show(Request $request, CorreoPlantilla $plantilla): JsonResponse
    {
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            ['creador', 'envios']
        ));

        if ($incluir !== []) {
            $plantilla->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($plantilla, $incluir));
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
            'asunto' => trim((string) ($data['asunto'] ?? '')) !== '' ? $data['asunto'] : $data['nombre'],
            'bloques' => Bloques::porDefecto(),
            'marca' => Bloques::marcaPorDefecto(),
            'creado_por' => Auth::id(),
        ]);

        return Respuesta::recurso(Serializador::modelo($plantilla->fresh(['creador'])), 201);
    }

    public function update(Request $request, CorreoPlantilla $plantilla): JsonResponse
    {
        $data = $this->validated($request);

        $plantilla->update($data);

        return Respuesta::recurso(Serializador::modelo($plantilla->fresh(['creador'])));
    }

    public function destroy(CorreoPlantilla $plantilla): JsonResponse
    {
        $plantilla->delete();

        return Respuesta::eliminado();
    }

    public function duplicar(CorreoPlantilla $plantilla): JsonResponse
    {
        $copia = $plantilla->replicate(['creado_por']);
        $copia->nombre = $plantilla->nombre.' (copia)';
        $copia->estado = 'activa';
        $copia->creado_por = Auth::id();
        $copia->save();

        return Respuesta::recurso(Serializador::modelo($copia->fresh(['creador'])), 201);
    }

    public function preview(Request $request): JsonResponse
    {
        $respuesta = $this->delegadoPreview->preview($request);

        return Respuesta::recurso($respuesta->getData(true));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(array_column(CategoriaPlantillaCorreo::cases(), 'value'))],
            'estado' => ['required', Rule::in(CorreoPlantillasController::ESTADOS)],
            'asunto' => ['required', 'string', 'max:255'],
            // Mismo límite que el controlador web (ver su comentario): la columna
            // es LONGTEXT, así que 2,000,000 de caracteres no truncan nada.
            'html_personalizado' => ['nullable', 'string', 'max:2000000'],
        ] + Bloques::reglas();

        $data = $request->validate($reglas);

        $data['html_personalizado'] = trim((string) ($data['html_personalizado'] ?? '')) !== ''
            ? $data['html_personalizado']
            : null;

        $data['bloques'] = array_values($data['bloques'] ?? []);
        $data['marca'] = $data['marca'] ?? Bloques::marcaPorDefecto();

        return $data;
    }
}
