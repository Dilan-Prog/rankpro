<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoSeccion;
use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Support\Reportes\EsquemaSeccion;
use App\Support\Reportes\NormalizadorCelda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReporteSeccionController extends Controller
{
    public function store(Request $request, Reporte $reporte): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', 'in:'.implode(',', array_column(TipoSeccion::cases(), 'value'))],
            'titulo' => ['required', 'string', 'max:255'],
        ]);

        $tipo = TipoSeccion::from($data['tipo']);

        $seccion = $reporte->secciones()->create([
            'tipo' => $tipo->value,
            'titulo' => $data['titulo'],
            'orden' => (int) $reporte->secciones()->max('orden') + 1,
            'contenido' => EsquemaSeccion::vacio($tipo),
            'visible' => true,
        ]);

        return response()->json($seccion->toRow(), 201);
    }

    /**
     * Autosave del editor de una sección. Las reglas del `contenido` dependen
     * del tipo y viven en EsquemaSeccion, ya prefijadas con `contenido.`.
     *
     * El `{reporte}` de la ruta no se usa aquí: está para que el scoped binding
     * de Laravel compruebe que la sección le pertenece y devuelva 404 si no.
     */
    public function update(Request $request, Reporte $reporte, ReporteSeccion $seccion): JsonResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'rotulo' => ['nullable', 'string', 'max:40'],
            'visible' => ['boolean'],
            'contenido' => ['present', 'array'],
            // El aviso es el bloque "DATO DESACTUALIZADO" del diseño. La fecha
            // se valida como cadena y no como `date` a propósito: el documento
            // imprime lo que se escriba ("18 ago 2026"), y obligar a un formato
            // de fecha ISO impediría anotar un periodo o una fuente.
            'aviso' => ['nullable', 'array'],
            'aviso.texto' => ['nullable', 'string', 'max:1000'],
            'aviso.fecha' => ['nullable', 'string', 'max:40'],
        ] + EsquemaSeccion::reglas($seccion->tipo));

        $seccion->update([
            'titulo' => $data['titulo'],
            'rotulo' => $data['rotulo'] ?? null,
            // Un aviso sin texto no es un aviso: se guarda null para que el
            // renderizador no tenga que distinguir entre ausente y vacío.
            'aviso' => filled($data['aviso']['texto'] ?? null) ? $data['aviso'] : null,
            // `visible` solo cambia si el editor lo manda: un autosave de
            // contenido no debe poder ocultar la sección del entregable.
            'visible' => $request->has('visible') ? $request->boolean('visible') : $seccion->visible,
            // Lo tecleado se normaliza igual que lo pegado; si no, el mismo
            // "1,5" acaba almacenado distinto según por dónde entró.
            'contenido' => $this->normalizarContenido($seccion->tipo, $data['contenido']),
        ]);

        return response()->json($seccion->fresh()->toRow());
    }

    public function destroy(Reporte $reporte, ReporteSeccion $seccion): JsonResponse
    {
        $seccion->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Reescribe la columna `orden` (1..n) a partir de la lista de IDs que manda
     * el drag & drop del editor. Solo acepta secciones del propio reporte, para
     * que un ID ajeno no pueda colarse y quedar reasignado.
     */
    public function reordenar(Request $request, Reporte $reporte): JsonResponse
    {
        $data = $request->validate([
            'orden' => ['required', 'array', 'min:1'],
            'orden.*' => ['required', 'integer'],
        ]);

        $ids = array_map('intval', $data['orden']);
        $propias = $reporte->secciones()->pluck('id')->all();

        if (array_diff($ids, $propias) !== []) {
            return response()->json(['message' => 'Alguna sección no pertenece a este reporte.'], 422);
        }

        DB::transaction(function () use ($ids, $reporte) {
            foreach ($ids as $i => $id) {
                $reporte->secciones()->whereKey($id)->update(['orden' => $i + 1]);
            }
        });

        return response()->json(['orden' => $ids]);
    }

    /**
     * Pegado masivo desde una hoja de cálculo. Mismo patrón que
     * KeywordImportController::store(): una fila por línea, mapeo posicional
     * contra las columnas ya configuradas en la sección, y las filas malas se
     * reportan una a una en vez de abortar el lote.
     *
     * El `{reporte}` de la ruta no se usa aquí: está para que el scoped binding
     * de Laravel compruebe que la sección le pertenece y devuelva 404 si no.
     */
    public function pegar(Request $request, Reporte $reporte, ReporteSeccion $seccion): JsonResponse
    {
        if (! $seccion->tipo->admitePegado()) {
            return response()->json([
                'message' => 'Las secciones de tipo '.$seccion->tipo->label().' no admiten pegado masivo.',
            ], 422);
        }

        $request->validate(['texto' => ['required', 'string']]);

        $contenido = $seccion->contenido ?? EsquemaSeccion::vacio($seccion->tipo);
        $columnas = EsquemaSeccion::columnasPegado($seccion->tipo, $contenido);

        // `columnasPegado` devuelve [] cuando la sección todavía no tiene
        // configuradas sus columnas (tabla), sus series (serie) o sus campos
        // (ficha): sin ellas el mapeo posicional descartaría todo en silencio.
        if ($columnas === []) {
            return response()->json([
                'message' => 'Configura primero las columnas de esta sección (las series de una gráfica, los campos de una ficha): sin ellas no hay forma de saber qué es cada valor pegado.',
            ], 422);
        }

        $destino = EsquemaSeccion::destinoPegado($seccion->tipo);
        $tipos = EsquemaSeccion::tiposColumna($seccion->tipo, $contenido);
        $reglasFila = $this->reglasFila($seccion->tipo, $destino['destino']);

        $lineas = preg_split('/\r\n|\r|\n/', trim((string) $request->input('texto', '')));
        $lineas = array_values(array_filter($lineas ?: [], fn ($l) => trim((string) $l) !== ''));

        if (empty($lineas)) {
            return response()->json(['message' => 'No se encontraron filas para pegar.'], 422);
        }

        $creadas = [];
        $errores = [];

        foreach ($lineas as $i => $linea) {
            // Excel pega con tabuladores; un CSV llega con comas. Se elige por
            // línea para que un pegado mixto no se rompa entero.
            $cols = str_contains($linea, "\t")
                ? str_getcsv($linea, "\t")
                : str_getcsv(trim($linea));
            $cols = array_map(fn ($c) => trim((string) $c), $cols);

            $registro = [];
            $valores = [];

            foreach ($columnas as $pos => $clave) {
                $valor = NormalizadorCelda::celda($cols[$pos] ?? '', $tipos[$clave] ?? 'texto');

                if ($destino['anidado'] === null || in_array($clave, $destino['planas'], true)) {
                    $registro[$clave] = $valor;
                } else {
                    // Los valores de una ficha llevan su propio estado de
                    // semáforo, así que son un mapa y no un escalar. Pegar
                    // aporta el dato; el estado lo asigna después el equipo.
                    $valores[$clave] = $seccion->tipo === TipoSeccion::Ficha
                        ? ['valor' => $valor, 'estado' => null]
                        : $valor;
                }
            }

            if ($destino['anidado'] !== null) {
                $registro[$destino['anidado']] = $valores;
            }

            $validator = Validator::make($registro, $reglasFila);

            if ($validator->fails()) {
                $errores[] = [
                    'fila' => $i + 1,
                    'texto' => $linea,
                    'errores' => $validator->errors()->all(),
                ];

                continue;
            }

            $creadas[] = $registro;
        }

        if ($creadas !== []) {
            // Se añaden al final: pegar es "sumar filas", nunca reemplazar lo
            // que el equipo ya había capturado a mano en esa sección.
            $contenido[$destino['destino']] = array_merge($contenido[$destino['destino']] ?? [], $creadas);
            $seccion->update(['contenido' => $contenido]);
        }

        return response()->json([
            'creadas' => count($creadas),
            'errores' => $errores,
            'seccion' => $seccion->fresh()->toRow(),
        ], count($creadas) ? 201 : 422);
    }

    /**
     * Reglas de validación de UNA fila pegada, derivadas de las mismas reglas
     * que usa el autosave: se toman las claves `contenido.<destino>.*.<campo>`
     * y se les quita el prefijo. Así el pegado y el editor validan idéntico sin
     * duplicar el contrato.
     *
     * Para `tabla` esto queda vacío a propósito: sus filas son mapas libres
     * (`contenido.filas.*` => array) cuya forma la fijan las columnas, no el
     * esquema.
     *
     * @return array<string, mixed>
     */
    private function reglasFila(TipoSeccion $tipo, string $destino): array
    {
        $prefijo = "contenido.{$destino}.*.";
        $reglas = [];

        foreach (EsquemaSeccion::reglas($tipo) as $clave => $regla) {
            if (str_starts_with($clave, $prefijo)) {
                $campo = substr($clave, strlen($prefijo));

                if ($campo !== '') {
                    $reglas[$campo] = $regla;
                }
            }
        }

        return $reglas;
    }

    /**
     * Normaliza los valores capturados a mano contra el tipo declarado de su
     * columna, para que el autosave guarde exactamente lo mismo que el pegado.
     *
     * Solo se recorren `filas` y `filas_excluidas` (tabla y serie): son las
     * únicas estructuras con celdas numéricas por columna. Los campos de una
     * ficha son texto libre y los números de un plan ya vienen validados como
     * enteros por el esquema, así que ahí no hay nada que normalizar.
     *
     * @param  array<string, mixed>  $contenido
     * @return array<string, mixed>
     */
    private function normalizarContenido(TipoSeccion $tipo, array $contenido): array
    {
        $tipos = EsquemaSeccion::tiposColumna($tipo, $contenido);

        if ($tipos === []) {
            return $contenido;
        }

        foreach (['filas', 'filas_excluidas'] as $lista) {
            if (! is_array($contenido[$lista] ?? null)) {
                continue;
            }

            foreach ($contenido[$lista] as $i => $fila) {
                if (! is_array($fila) || ! is_array($fila['valores'] ?? null)) {
                    continue;
                }

                foreach ($fila['valores'] as $clave => $valor) {
                    $tipoColumna = $tipos[$clave] ?? 'texto';

                    // `texto` y `nivel` se dejan intactos: son cadenas libres
                    // y el usuario tiene derecho a escribir "1,5 s" ahí.
                    if (in_array($tipoColumna, ['texto', 'nivel'], true)) {
                        continue;
                    }

                    $contenido[$lista][$i]['valores'][$clave] = NormalizadorCelda::celda($valor, $tipoColumna);
                }
            }
        }

        return $contenido;
    }
}
