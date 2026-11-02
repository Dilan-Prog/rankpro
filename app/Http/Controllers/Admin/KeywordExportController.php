<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Support\ClienteActivo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta a CSV las keywords de una lista o de varias listas seleccionadas.
 *
 * Es solo lectura y vive aparte de KeywordsController para no tocar el CRUD. La unidad es la lista (la misma que
 * se selecciona con las casillas del índice), no la keyword suelta: el índice no tiene selección por keyword.
 */
class KeywordExportController extends Controller
{
    /** Tope de listas por descarga: la URL viaja por GET y no tiene sentido pedir cientos de golpe. */
    private const MAX_LISTAS = 200;

    private const ENCABEZADOS = [
        'Lista', 'Cliente', 'Keyword', 'Tipo', 'Volumen', 'KD', 'CPC estimado', 'Intención',
        'URL asignada', 'Posición actual', 'Posición anterior', 'Estado', 'Fuente', 'Fecha de incorporación',
    ];

    public function lista(KeywordLista $lista): StreamedResponse
    {
        return $this->descargar(collect([$lista]), 'keywords-'.Str::slug($lista->nombre ?: 'lista').'-'.now()->format('Y-m-d').'.csv');
    }

    public function seleccion(Request $request): StreamedResponse
    {
        $data = $request->validate(['listas' => ['required', 'string', 'max:2000']]);

        $ids = collect(explode(',', $data['listas']))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(self::MAX_LISTAS)
            ->values();

        abort_if($ids->isEmpty(), 422, 'No se indicó ninguna lista válida.');

        // whereIn sobre el modelo respeta el borrado lógico: una lista eliminada no se exporta.
        // Con cliente activo, la selección se limita a sus listas: es lo único que el banco le muestra.
        $listas = KeywordLista::whereIn('id', $ids)
            ->when(ClienteActivo::id(), fn ($q, $clienteId) => $q->where('cliente_id', $clienteId))
            ->orderBy('nombre')->get();

        return $this->descargar($listas, 'keywords-seleccion-'.now()->format('Y-m-d').'.csv');
    }

    private function descargar($listas, string $nombreArchivo): StreamedResponse
    {
        return response()->streamDownload(function () use ($listas) {
            $salida = fopen('php://output', 'w');
            // BOM UTF-8: sin él Excel abre el CSV como ANSI y rompe acentos y eñes.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, self::ENCABEZADOS);

            foreach ($listas as $lista) {
                Keyword::with('cliente')
                    ->where('lista_id', $lista->id)
                    ->orderBy('keyword')
                    ->chunk(500, function ($keywords) use ($salida, $lista) {
                        foreach ($keywords as $k) {
                            fputcsv($salida, array_map([self::class, 'protegerFormula'], [
                                $lista->nombre,
                                $k->cliente?->nombre,
                                $k->keyword,
                                $k->tipo,
                                $k->volumen_busqueda,
                                $k->dificultad,
                                $k->cpc_estimado,
                                $k->intencion,
                                $k->url_asignada,
                                $k->posicion_actual,
                                $k->posicion_anterior,
                                $k->estado->value,
                                $k->herramienta_origen,
                                $k->fecha_incorporacion?->format('Y-m-d'),
                            ]));
                        }
                    });
            }

            fclose($salida);
        }, $nombreArchivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Evita la inyección de fórmulas (CSV injection): una keyword o URL que empiece por = + - @ (o tabulador/retorno)
     * se ejecutaría como fórmula al abrirla en Excel. Se antepone apóstrofo, que Excel muestra como texto literal.
     * Solo se toca el texto: los números (volumen, posiciones) pasan intactos.
     */
    public static function protegerFormula(mixed $valor): mixed
    {
        if (! is_string($valor) || $valor === '') {
            return $valor;
        }

        return in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$valor : $valor;
    }
}
