<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\ElementoContenido;
use App\Support\Contenido\FuentesTabla;
use App\Support\Contenido\TablaContenido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Guardado, importación y exportación CSV de las tablas editables del módulo «Contenido». */
class ContenidoTablaController extends ContenidoBaseController
{
    /** Tope del cuerpo recibido, antes de decodificarlo: un poco por encima del JSON normalizado permitido. */
    private const MAX_CUERPO = 3000000;

    /** Claves de la tabla que acepta el servidor; cualquier otra se ignora. */
    private const CAMPOS_TABLA = ['columnas', 'filas', 'vista', 'columna_kanban', 'columna_fecha', 'columna_titulo', 'columnas_visibles', 'origen'];

    /** Guarda la tabla COMPLETA (el cliente manda el JSON entero; no hay parches por celda). */
    public function guardar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->tabla($elemento);

        if (strlen($request->getContent()) > self::MAX_CUERPO) {
            return response()->json(['message' => 'La tabla es demasiado grande para guardarse.'], 413);
        }

        try {
            $tabla = TablaContenido::normalizar($request->only(self::CAMPOS_TABLA));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $elemento->contenido = json_encode($tabla, JSON_UNESCAPED_UNICODE);
        $elemento->save();

        return response()->json(['guardado' => $elemento->updated_at->format('H:i:s'), 'datos' => $tabla]);
    }

    public function importar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->tabla($elemento);

        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
            'modo' => ['required', 'in:reemplazar,agregar'],
        ]);

        try {
            $resultado = TablaContenido::desdeCsv(
                (string) file_get_contents($request->file('archivo')->getRealPath()),
                $data['modo'] === 'agregar' ? TablaContenido::decodificar($elemento->contenido) : null
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $elemento->contenido = json_encode($resultado['tabla'], JSON_UNESCAPED_UNICODE);
        $elemento->save();

        return response()->json([
            'datos' => $resultado['tabla'],
            'importadas' => $resultado['importadas'],
            'ignoradas' => $resultado['ignoradas'],
        ]);
    }

    /**
     * Cambia el tipo de una columna. Recibe el estado actual de la tabla (puede
     * tener cambios sin guardar) y devuelve la tabla convertida SIN persistirla:
     * el cliente la adopta y queda como cambio pendiente. Si hay celdas que no
     * encajan en el tipo nuevo responde 422 con el motivo y no cambia nada.
     */
    public function convertirColumna(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->tabla($elemento);

        if (strlen($request->getContent()) > self::MAX_CUERPO) {
            return response()->json(['message' => 'La tabla es demasiado grande para procesarse.'], 413);
        }

        $data = $request->validate([
            'columna' => ['required', 'string', 'max:40'],
            'tipo' => ['required', 'string', 'max:20'],
        ]);

        try {
            $tabla = TablaContenido::cambiarTipoColumna(
                $request->only(self::CAMPOS_TABLA),
                $data['columna'],
                $data['tipo']
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['datos' => $tabla]);
    }

    /**
     * «Llenar desde...»: copia una foto de datos reales del CLIENTE DE LA TABLA
     * (no del que mande el navegador) y la guarda. Exige el permiso del módulo de
     * la fuente: sin él, 403, aunque el usuario pueda editar la tabla.
     */
    public function llenar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->tabla($elemento);

        $data = $request->validate([
            'fuente' => ['required', 'string', Rule::in(FuentesTabla::claves())],
            'modo' => ['required', 'in:reemplazar,agregar'],
        ]);

        abort_unless(FuentesTabla::permite($request->user(), $data['fuente']), 403, 'Tu rol no tiene acceso a esta fuente de datos.');

        try {
            $resultado = TablaContenido::llenarDesdeFoto(
                TablaContenido::decodificar($elemento->contenido),
                FuentesTabla::foto($data['fuente'], $elemento->cliente_id),
                $data['modo'],
                $data['fuente'],
                now()->format('Y-m-d H:i')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $elemento->contenido = json_encode($resultado['tabla'], JSON_UNESCAPED_UNICODE);
        $elemento->save();

        return response()->json([
            'datos' => $resultado['tabla'],
            'importadas' => $resultado['importadas'],
            'omitidas' => $resultado['omitidas'],
        ]);
    }

    /** Descarga la tabla guardada como CSV (UTF-8 con BOM para que Excel respete los acentos). */
    public function exportar(ElementoContenido $elemento): StreamedResponse
    {
        $this->tabla($elemento);

        $filas = TablaContenido::filasCsv(TablaContenido::decodificar($elemento->contenido));
        $nombre = (Str::slug($elemento->nombre) ?: 'tabla').'.csv';

        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            foreach ($filas as $fila) {
                fputcsv($salida, $fila, ',', '"', '');
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function tabla(ElementoContenido $elemento): void
    {
        $this->delClienteActivo($elemento);
        abort_unless($elemento->tipo === TipoElementoContenido::Tabla, 404);
    }
}
