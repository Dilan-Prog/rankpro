<?php

namespace App\Support\Reportes;

use App\Enums\TipoSeccion;

/**
 * Contrato del JSON de cada tipo de sección: forma inicial, reglas de validación
 * y orden de columnas para el pegado masivo desde hoja de cálculo.
 *
 * Fuente única de verdad: el editor, el validador del controlador, el Armador y
 * los dos renderizadores leen de aquí. Cambiar la forma de un tipo se hace en
 * este archivo y en ningún otro sitio.
 *
 * Formas (el `contenido` de reporte_secciones):
 *
 *   kpis      {items:[{label, valor, formato, comparativo, direccion, detalle,
 *                      destacado, desactualizado}]}
 *   hallazgos {items:[{titulo, severidad, evidencia}]}
 *   serie     {etiqueta_x, series:[{clave, titulo, eje, color}],
 *              filas:[{x, valores:{clave:num}}], lectura}
 *   tabla     {columnas:[{clave, titulo, tipo, alineacion}],
 *              filas:[{valores:{clave:val}, destacada, motivo}],
 *              filas_excluidas:[igual que filas], nota_excluidas,
 *              totales:{clave:modo}, nota}
 *   ficha     {dimensiones:[{clave, titulo}], campos:[{clave, titulo, dimension}],
 *              registros:[{titulo, valores:{clave:{valor, estado}}, veredicto, estado}], nota}
 *   plan      {acciones:[{prioridad, accion, evidencia, area, impacto, esfuerzo, kpi}],
 *              leyenda, leyenda_prioridades}
 *   texto     {formato, bloques:[{titulo, cuerpo}]}
 *
 * Las formas de `tabla`, `serie` y `ficha` anidan los valores de columna bajo una
 * clave (`valores`) y dejan en la raíz del registro lo que es metadato de la fila
 * (destacada, motivo, veredicto). Así el pegado masivo, el renderizado y el
 * marcado de filas destacadas o excluidas comparten una sola estructura.
 */
class EsquemaSeccion
{
    /**
     * Formatos de celda que entienden los renderizadores. `nivel` es el de las
     * escalas cualitativas (ALTA / MEDIA / BAJA): el diseño las pinta con peso y
     * color, nunca como badge, porque 64 cápsulas de color en una página son ruido.
     */
    public const FORMATOS = ['texto', 'numero', 'decimal', 'porcentaje', 'moneda', 'fecha', 'nivel'];

    /** Modos de total por columna que resuelve el Armador. */
    public const TOTALES = ['ninguno', 'suma', 'promedio', 'ponderado', 'ctr'];

    public const ALINEACIONES = ['izquierda', 'centro', 'derecha'];

    /** Semáforo de la auditoría: cumple / revisar / incumple / sin dato. */
    public const ESTADOS_FICHA = ['ok', 'revisar', 'critico', 'nd'];

    /** Ejes de una serie: el diseño admite doble eje cuando las escalas no son comparables. */
    public const EJES = ['izq', 'der'];

    /**
     * Contenido inicial de una sección recién creada.
     *
     * @return array<string, mixed>
     */
    public static function vacio(TipoSeccion $tipo): array
    {
        return match ($tipo) {
            TipoSeccion::Kpis => ['items' => []],
            TipoSeccion::Hallazgos => ['items' => []],
            TipoSeccion::Serie => [
                'etiqueta_x' => 'Fecha',
                'series' => [],
                'filas' => [],
                'lectura' => '',
            ],
            TipoSeccion::Tabla => [
                'columnas' => [],
                'filas' => [],
                'filas_excluidas' => [],
                'nota_excluidas' => '',
                'totales' => [],
                'nota' => '',
            ],
            TipoSeccion::Ficha => [
                'dimensiones' => [],
                'campos' => [],
                'registros' => [],
                'nota' => '',
            ],
            TipoSeccion::Plan => [
                'acciones' => [],
                'leyenda' => 'Impacto y esfuerzo se puntúan de 1 a 5. Score = impacto / esfuerzo.',
                'leyenda_prioridades' => 'P0 = esta semana · P1 = este mes · P2 = este trimestre · P3 = cuando haya holgura.',
            ],
            TipoSeccion::Texto => [
                'formato' => 'pares',
                'bloques' => [],
            ],
        };
    }

    /**
     * Reglas de validación del contenido, ya prefijadas con `contenido.` para
     * mezclarlas con las reglas del propio registro de sección.
     *
     * @return array<string, mixed>
     */
    public static function reglas(TipoSeccion $tipo): array
    {
        $formatos = 'in:'.implode(',', self::FORMATOS);
        $alineaciones = 'in:'.implode(',', self::ALINEACIONES);
        $estados = 'in:'.implode(',', self::ESTADOS_FICHA);

        return match ($tipo) {
            TipoSeccion::Kpis => [
                'contenido.items' => ['present', 'array'],
                'contenido.items.*.label' => ['required', 'string', 'max:120'],
                'contenido.items.*.valor' => ['nullable', 'string', 'max:60'],
                'contenido.items.*.formato' => ['nullable', $formatos],
                'contenido.items.*.comparativo' => ['nullable', 'string', 'max:60'],
                'contenido.items.*.direccion' => ['nullable', 'in:positiva,negativa,neutral'],
                'contenido.items.*.detalle' => ['nullable', 'string', 'max:255'],
                'contenido.items.*.destacado' => ['nullable', 'boolean'],
                'contenido.items.*.desactualizado' => ['nullable', 'string', 'max:40'],
            ],
            TipoSeccion::Hallazgos => [
                'contenido.items' => ['present', 'array'],
                'contenido.items.*.titulo' => ['required', 'string', 'max:500'],
                'contenido.items.*.severidad' => ['required', 'in:critico,alto,medio,informativo'],
                'contenido.items.*.evidencia' => ['nullable', 'string', 'max:500'],
            ],
            TipoSeccion::Serie => [
                'contenido.etiqueta_x' => ['nullable', 'string', 'max:60'],
                'contenido.series' => ['present', 'array'],
                'contenido.series.*.clave' => ['required', 'string', 'max:40', 'distinct'],
                'contenido.series.*.titulo' => ['required', 'string', 'max:60'],
                'contenido.series.*.eje' => ['nullable', 'in:'.implode(',', self::EJES)],
                'contenido.series.*.color' => ['nullable', 'string', 'max:20'],
                'contenido.filas' => ['present', 'array'],
                'contenido.filas.*.x' => ['required', 'string', 'max:60'],
                'contenido.filas.*.valores' => ['present', 'array'],
                'contenido.lectura' => ['nullable', 'string'],
            ],
            TipoSeccion::Tabla => [
                'contenido.columnas' => ['present', 'array'],
                // `distinct` impide dos columnas con la misma clave: sin esta regla,
                // renombrar una clave a otra existente pisaba los datos de la otra
                // columna en todas las filas y el backend lo aceptaba con un 200.
                'contenido.columnas.*.clave' => ['required', 'string', 'max:40', 'distinct'],
                'contenido.columnas.*.titulo' => ['required', 'string', 'max:80'],
                'contenido.columnas.*.tipo' => ['nullable', $formatos],
                'contenido.columnas.*.alineacion' => ['nullable', $alineaciones],
                'contenido.filas' => ['present', 'array'],
                'contenido.filas.*.valores' => ['present', 'array'],
                'contenido.filas.*.destacada' => ['nullable', 'boolean'],
                'contenido.filas.*.motivo' => ['nullable', 'string', 'max:255'],
                'contenido.filas_excluidas' => ['nullable', 'array'],
                'contenido.filas_excluidas.*.valores' => ['present', 'array'],
                'contenido.filas_excluidas.*.motivo' => ['nullable', 'string', 'max:255'],
                'contenido.nota_excluidas' => ['nullable', 'string'],
                'contenido.totales' => ['nullable', 'array'],
                'contenido.nota' => ['nullable', 'string'],
            ],
            TipoSeccion::Ficha => [
                'contenido.dimensiones' => ['nullable', 'array'],
                'contenido.dimensiones.*.clave' => ['required', 'string', 'max:40', 'distinct'],
                'contenido.dimensiones.*.titulo' => ['required', 'string', 'max:60'],
                'contenido.campos' => ['present', 'array'],
                'contenido.campos.*.clave' => ['required', 'string', 'max:40', 'distinct'],
                'contenido.campos.*.titulo' => ['required', 'string', 'max:80'],
                'contenido.campos.*.dimension' => ['nullable', 'string', 'max:40'],
                'contenido.registros' => ['present', 'array'],
                'contenido.registros.*.titulo' => ['required', 'string', 'max:255'],
                'contenido.registros.*.valores' => ['present', 'array'],
                'contenido.registros.*.valores.*.valor' => ['nullable'],
                'contenido.registros.*.valores.*.estado' => ['nullable', $estados],
                'contenido.registros.*.veredicto' => ['nullable', 'string', 'max:255'],
                'contenido.registros.*.estado' => ['nullable', $estados],
                'contenido.nota' => ['nullable', 'string'],
            ],
            TipoSeccion::Plan => [
                'contenido.acciones' => ['present', 'array'],
                'contenido.acciones.*.prioridad' => ['required', 'in:p0,p1,p2,p3'],
                'contenido.acciones.*.accion' => ['required', 'string', 'max:500'],
                'contenido.acciones.*.evidencia' => ['nullable', 'string', 'max:500'],
                'contenido.acciones.*.area' => ['nullable', 'string', 'max:60'],
                'contenido.acciones.*.impacto' => ['required', 'integer', 'min:1', 'max:5'],
                'contenido.acciones.*.esfuerzo' => ['required', 'integer', 'min:1', 'max:5'],
                'contenido.acciones.*.kpi' => ['nullable', 'string', 'max:255'],
                'contenido.leyenda' => ['nullable', 'string'],
                'contenido.leyenda_prioridades' => ['nullable', 'string'],
            ],
            TipoSeccion::Texto => [
                'contenido.formato' => ['nullable', 'in:pares,narrativa'],
                'contenido.bloques' => ['present', 'array'],
                'contenido.bloques.*.titulo' => ['nullable', 'string', 'max:255'],
                'contenido.bloques.*.cuerpo' => ['required', 'string'],
            ],
        };
    }

    /**
     * Claves, en orden, que espera cada columna del texto pegado desde una hoja
     * de cálculo. Para tabla, serie y ficha el orden lo definen las columnas que
     * el usuario ya configuró: sin ellas se devuelve un array vacío, que es la
     * señal que usa el controlador para pedir que se configuren primero. Las
     * claves fijas (`x`, `titulo`, `veredicto`) solo se anteponen si hay alguna
     * columna configurada; si no, el pegado descartaría los valores en silencio.
     *
     * @param  array<string, mixed>  $contenido
     * @return array<int, string>
     */
    public static function columnasPegado(TipoSeccion $tipo, array $contenido): array
    {
        $series = array_column($contenido['series'] ?? [], 'clave');
        $campos = array_column($contenido['campos'] ?? [], 'clave');

        return match ($tipo) {
            TipoSeccion::Tabla => array_column($contenido['columnas'] ?? [], 'clave'),
            TipoSeccion::Serie => $series === [] ? [] : array_merge(['x'], $series),
            TipoSeccion::Ficha => $campos === [] ? [] : array_merge(['titulo'], $campos, ['veredicto']),
            TipoSeccion::Plan => ['prioridad', 'accion', 'evidencia', 'area', 'impacto', 'esfuerzo', 'kpi'],
            default => [],
        };
    }

    /**
     * Dónde aterrizan las filas pegadas dentro del contenido de cada tipo, y bajo
     * qué clave anidada quedan los valores por columna (null = plano).
     *
     * `planas` son las claves que quedan en la raíz del registro aunque el tipo
     * anide el resto (el `x` de una serie, el `titulo` y el `veredicto` de una
     * ficha); las demás caen dentro de `anidado`.
     *
     * @return array{destino: string, anidado: ?string, planas: array<int, string>}
     */
    public static function destinoPegado(TipoSeccion $tipo): array
    {
        return match ($tipo) {
            TipoSeccion::Tabla => ['destino' => 'filas', 'anidado' => 'valores', 'planas' => []],
            TipoSeccion::Serie => ['destino' => 'filas', 'anidado' => 'valores', 'planas' => ['x']],
            TipoSeccion::Ficha => ['destino' => 'registros', 'anidado' => 'valores', 'planas' => ['titulo', 'veredicto']],
            TipoSeccion::Plan => ['destino' => 'acciones', 'anidado' => null, 'planas' => []],
            default => ['destino' => '', 'anidado' => null, 'planas' => []],
        };
    }

    /**
     * Tipo declarado de cada columna pegable o capturable, para normalizar la
     * celda. Lo consumen tanto el pegado masivo como el autosave, de modo que un
     * valor tecleado y el mismo valor pegado se almacenen idénticos.
     *
     * @param  array<string, mixed>  $contenido
     * @return array<string, string>
     */
    public static function tiposColumna(TipoSeccion $tipo, array $contenido): array
    {
        return match ($tipo) {
            TipoSeccion::Tabla => collect($contenido['columnas'] ?? [])
                ->filter(fn ($col) => is_array($col) && ($col['clave'] ?? '') !== '')
                ->mapWithKeys(fn ($col) => [(string) $col['clave'] => (string) ($col['tipo'] ?? 'texto')])
                ->all(),
            TipoSeccion::Serie => collect($contenido['series'] ?? [])
                ->filter(fn ($s) => is_array($s) && ($s['clave'] ?? '') !== '')
                ->mapWithKeys(fn ($s) => [(string) $s['clave'] => 'decimal'])
                ->all() + ['x' => 'texto'],
            TipoSeccion::Ficha => collect($contenido['campos'] ?? [])
                ->filter(fn ($c) => is_array($c) && ($c['clave'] ?? '') !== '')
                ->mapWithKeys(fn ($c) => [(string) $c['clave'] => 'texto'])
                ->all() + ['titulo' => 'texto', 'veredicto' => 'texto'],
            TipoSeccion::Plan => [
                'prioridad' => 'clave',
                'accion' => 'texto',
                'evidencia' => 'texto',
                'area' => 'texto',
                'impacto' => 'numero',
                'esfuerzo' => 'numero',
                'kpi' => 'texto',
            ],
            default => [],
        };
    }
}
