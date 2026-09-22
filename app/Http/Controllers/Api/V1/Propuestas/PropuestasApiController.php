<?php

namespace App\Http\Controllers\Api\V1\Propuestas;

use App\Enums\EstadoPropuesta;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\Propuesta;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Propuestas\Plantilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD de /api/v1/propuestas. Mismas reglas que Admin\PropuestaController
 * (cliente_id/seo_campana_id inmutables tras crear — ver su docblock).
 */
class PropuestasApiController extends ControladorApi
{
    public function index(Request $request): JsonResponse
    {
        return $this->listar(Propuesta::query(), $request, new ConsultaOpciones(
            buscarEn: ['folio', 'titulo'],
            filtrosExactos: ['cliente_id', 'estado', 'seo_campana_id'],
            ordenables: ['id', 'folio', 'titulo', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'seoCampana'],
        ));
    }

    public function show(Request $request, Propuesta $propuesta): JsonResponse
    {
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            ['cliente', 'seoCampana']
        ));

        if ($incluir !== []) {
            $propuesta->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($propuesta, $incluir));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'seo_campana_id' => [
                'nullable', 'integer',
                Rule::exists('seo_campanas', 'id')->where('cliente_id', $request->integer('cliente_id')),
            ],
            'titulo' => ['nullable', 'string', 'max:255'],
        ]);

        $propuesta = Propuesta::create(Plantilla::vacio() + [
            'cliente_id' => $data['cliente_id'],
            'seo_campana_id' => $data['seo_campana_id'] ?? null,
            'titulo' => $data['titulo'] ?? 'Propuesta de Continuidad SEO',
            'estado' => EstadoPropuesta::Borrador->value,
            'creado_por' => Auth::id(),
        ]);

        return Respuesta::recurso(Serializador::modelo($propuesta->fresh('cliente')), 201);
    }

    /** Solo `estado`: cliente_id/seo_campana_id son inmutables tras crear (ver Admin\PropuestaController). */
    public function update(Request $request, Propuesta $propuesta): JsonResponse
    {
        return $this->cambiarEstado($request, $propuesta);
    }

    /** Mismo efecto que update(), como acción explícita del contrato de la API. */
    public function estado(Request $request, Propuesta $propuesta): JsonResponse
    {
        return $this->cambiarEstado($request, $propuesta);
    }

    public function destroy(Propuesta $propuesta): JsonResponse
    {
        $propuesta->delete();

        return Respuesta::eliminado();
    }

    private function cambiarEstado(Request $request, Propuesta $propuesta): JsonResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::enum(EstadoPropuesta::class)],
        ]);

        $propuesta->update($data);

        return Respuesta::recurso(Serializador::modelo($propuesta->fresh('cliente')));
    }
}
