<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\ElementoContenido;
use App\Support\Contenido\ArbolContenido;
use App\Support\Contenido\PizarraContenido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/** Pantalla y guardado de las pizarras (lienzo libre) del módulo «Contenido». */
class ContenidoPizarraController extends ContenidoBaseController
{
    /** Tope del cuerpo recibido, antes de decodificarlo: un poco por encima del JSON normalizado permitido. */
    private const MAX_CUERPO = 2500000;

    public function editar(ElementoContenido $elemento): View
    {
        $this->pizarra($elemento);

        $carpetas = ElementoContenido::query()
            ->where('cliente_id', $elemento->cliente_id)
            ->where('tipo', TipoElementoContenido::Carpeta->value)
            ->get(['id', 'cliente_id', 'parent_id', 'tipo', 'nombre'])
            ->keyBy('id');

        $padre = $elemento->parent_id ? $carpetas->get($elemento->parent_id) : null;

        return view('admin.contenido.pizarra', [
            'pageTitle' => $elemento->nombre,
            'elemento' => $elemento,
            'migas' => ArbolContenido::cadena($padre, $carpetas),
            'datos' => PizarraContenido::decodificar($elemento->contenido),
            'urlGuardar' => route('admin.contenido.pizarra.guardar', $elemento),
            'urlRenombrar' => route('admin.contenido.actualizar', $elemento),
            'urlVolver' => route('admin.contenido.index', array_filter(['carpeta' => $elemento->parent_id])),
        ]);
    }

    /** Guarda la pizarra COMPLETA (sin parches por elemento: así deshacer/rehacer en el navegador no desincroniza nada). */
    public function guardar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->pizarra($elemento);

        if (strlen($request->getContent()) > self::MAX_CUERPO) {
            return response()->json(['message' => 'La pizarra es demasiado grande para guardarse.'], 413);
        }

        try {
            $datos = PizarraContenido::normalizar($request->only(['elementos', 'vista']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $elemento->contenido = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $elemento->save();

        return response()->json(['guardado' => $elemento->updated_at->format('H:i:s'), 'datos' => $datos]);
    }

    private function pizarra(ElementoContenido $elemento): ElementoContenido
    {
        $this->delClienteActivo($elemento);
        abort_unless($elemento->tipo === TipoElementoContenido::Pizarra, 404);

        return $elemento;
    }
}
