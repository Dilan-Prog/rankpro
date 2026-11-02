<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoElementoContenido;
use App\Models\ElementoContenido;
use App\Support\Contenido\TablaContenido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Guardado, importación y exportación CSV de las tablas editables del módulo «Contenido». */
class ContenidoTablaController extends ContenidoBaseController
{
    /** Tope del cuerpo recibido, antes de decodificarlo: un poco por encima del JSON normalizado permitido. */
    private const MAX_CUERPO = 3000000;

    /** Guarda la tabla COMPLETA (el cliente manda el JSON entero; no hay parches por celda). */
    public function guardar(Request $request, ElementoContenido $elemento): JsonResponse
    {
        $this->tabla($elemento);

        if (strlen($request->getContent()) > self::MAX_CUERPO) {
            return response()->json(['message' => 'La tabla es demasiado grande para guardarse.'], 413);
        }

        try {
            $tabla = TablaContenido::normalizar($request->only(['columnas', 'filas', 'vista', 'columna_kanban']));
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
