<?php

namespace App\Services\Reportes;

use App\Enums\PrioridadAccion;
use App\Enums\SeveridadHallazgo;
use App\Enums\TipoSeccion;
use App\Models\Reporte;
use App\Models\ReporteSeccion;
use App\Support\Reportes\EsquemaSeccion;

/**
 * Motor de armado del reporte: normaliza el JSON de cada sección y calcula
 * TODOS los derivados (totales de tabla, escalado de barras, semáforo de ficha,
 * score del plan, orden de hallazgos) en un único sitio.
 *
 * Los renderizadores —PDF y XLSX— no hacen aritmética: pintan lo que sale de
 * aquí. Ese es el punto: el PDF y el Excel de un mismo reporte no pueden
 * diferir porque no hay dos implementaciones del cálculo. Es el mismo motivo
 * por el que existe App\Support\FinanzasMetrics para el dashboard y finanzas.
 *
 * La estructura devuelta por armar() se pasa tal cual a Pdf::loadView(), así
 * que sus claves de primer nivel ('reporte' y 'secciones') son las variables
 * que reciben las vistas Blade.
 *
 * Contrato de salida de armar():
 *
 *   reporte   {titulo, numero, estado, area, area_label, cliente:{nombre,
 *              empresa, sitio_web}, sitio_web, fuentes:[string],
 *              version_etiqueta, periodo_inicio, periodo_fin, periodo_label,
 *              dias_periodo, comparativa_label, comparativa_dias,
 *              fecha_emision, notas_alcance}
 *   secciones [{tipo, titulo, rotulo, aviso, contenido, derivados}]
 *
 * Derivados por tipo (ver cada método derivadosX para el detalle):
 *   tabla     {totales, totales_excluidas, conteo, conteo_excluidas,
 *              destacadas, modos, modos_efectivos, filas, filas_excluidas}
 *   serie     {maximos, totales, conteo, ejes, barras, etiquetas_x, agregado}
 *   ficha     {semaforo:{dimensiones, filas}}
 *   kpis      {principales, secundarios}
 *   plan      {acciones, por_prioridad}
 *   hallazgos {items, por_severidad}
 *   texto     {}
 */
class Armador
{
    /** Meses en español para el formato 'd M Y' sin depender del locale de Carbon. */
    private const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    /** SeveridadHallazgo no expone label(); las etiquetas acentuadas viven aquí. */
    private const LABELS_SEVERIDAD = [
        'critico' => 'Crítico',
        'alto' => 'Alto',
        'medio' => 'Medio',
        'informativo' => 'Informativo',
    ];

    /**
     * Alto útil, en píxeles, del área de barras del gráfico de serie. Es una
     * constante del motor y no del renderizador porque la regla del módulo es
     * que el PDF no calcula: el Blade solo interpola `altura` en un style. El
     * valor sale de la maqueta (240 px de caja menos 24 px de etiquetas del eje
     * X) y solo se cambia aquí, en un sitio, para que un cambio de maqueta no
     * pueda desincronizar dos plantillas.
     */
    public const ALTO_GRAFICO_PX = 216;

    /** Peor gana: un `critico` en cualquier campo tiñe toda la dimensión. */
    private const PESO_ESTADO = ['nd' => 0, 'ok' => 1, 'revisar' => 2, 'critico' => 3];

    /**
     * Marcador de dato ausente en una celda de tabla o de ficha.
     *
     * OJO con la distinción: el resto del proyecto (App\Support\Labels y los
     * exports CSV) usa el em dash `—` para "sin valor". En el entregable al
     * cliente el diseño pide `n/d` explícito para las celdas de datos, porque
     * un guion largo dentro de una columna numérica se lee como un cero o como
     * un signo menos truncado. El `—` se conserva donde ya lo usa el resto del
     * proyecto (etiquetas de prioridad/severidad vacías, Labels), y `n/d` es
     * exclusivo de lo que sale de formatear(), es decir de las celdas.
     */
    public const AUSENTE = 'n/d';

    /**
     * @return array{reporte: array<string, mixed>, secciones: array<int, array<string, mixed>>}
     */
    public function armar(Reporte $reporte): array
    {
        $reporte->loadMissing(['cliente', 'seoCampana', 'seccionesVisibles']);

        $inicio = $reporte->periodo_inicio;
        $fin = $reporte->periodo_fin;

        $inicioLabel = $this->fechaCorta($inicio);
        $finLabel = $this->fechaCorta($fin);

        $secciones = [];

        foreach ($reporte->seccionesVisibles as $seccion) {
            $secciones[] = $this->armarSeccion($seccion);
        }

        // No hay columna de sitio en clientes: el sitio auditado se declara en
        // el propio reporte y, si viene vacío, se hereda de la campaña SEO
        // enlazada (seo_campanas.url_sitio), que es de donde salía antes.
        $sitioWeb = trim((string) ($reporte->sitio_web ?? '')) ?: ($reporte->seoCampana?->url_sitio ?: null);
        $sitioWeb = $sitioWeb ?: null;

        return [
            'reporte' => [
                'titulo' => (string) $reporte->titulo,
                'numero' => $reporte->numero,
                'estado' => $reporte->estado?->value ?? '',
                'area' => $reporte->area?->value ?? '',
                'area_label' => $reporte->area?->label() ?? '',
                'cliente' => [
                    'nombre' => (string) ($reporte->cliente?->nombre ?? ''),
                    'empresa' => (string) ($reporte->cliente?->empresa ?? ''),
                    'sitio_web' => $sitioWeb,
                ],
                'sitio_web' => $sitioWeb,
                'fuentes' => $this->fuentes($reporte),
                'version_etiqueta' => $this->textoONull($reporte->version_etiqueta),
                'periodo_inicio' => $inicioLabel,
                'periodo_fin' => $finLabel,
                'periodo_label' => trim($inicioLabel.' — '.$finLabel),
                // Periodo inclusivo: del 1 al 31 son 31 días, no 30.
                'dias_periodo' => ($inicio && $fin) ? ((int) $inicio->diffInDays($fin)) + 1 : 0,
                'comparativa_label' => $this->rangoLabel($reporte->comparativa_inicio, $reporte->comparativa_fin),
                'comparativa_dias' => ($reporte->comparativa_inicio && $reporte->comparativa_fin)
                    ? ((int) $reporte->comparativa_inicio->diffInDays($reporte->comparativa_fin)) + 1
                    : 0,
                'fecha_emision' => $this->fechaCorta($reporte->fecha_emision) ?: null,
                'notas_alcance' => $reporte->notas_alcance,
            ],
            'secciones' => $secciones,
        ];
    }

    /**
     * @return array{tipo: string, titulo: string, rotulo: ?string, aviso: ?array<string, mixed>, contenido: array<string, mixed>, derivados: array<string, mixed>}
     */
    public function armarSeccion(ReporteSeccion $seccion): array
    {
        $tipo = $seccion->tipo;
        $contenido = $this->normalizar($tipo, is_array($seccion->contenido) ? $seccion->contenido : []);

        return [
            'tipo' => $tipo->value,
            'titulo' => (string) $seccion->titulo,
            'rotulo' => $this->textoONull($seccion->rotulo),
            // El aviso se pasa tal cual: su forma la deciden el editor y los
            // renderizadores, aquí no hay nada que calcular sobre él.
            'aviso' => is_array($seccion->aviso) && $seccion->aviso !== [] ? $seccion->aviso : null,
            'contenido' => $contenido,
            'derivados' => match ($tipo) {
                TipoSeccion::Tabla => $this->derivadosTabla($contenido),
                TipoSeccion::Serie => $this->derivadosSerie($contenido),
                TipoSeccion::Ficha => $this->derivadosFicha($contenido),
                TipoSeccion::Kpis => $this->derivadosKpis($contenido),
                TipoSeccion::Plan => $this->derivadosPlan($contenido),
                TipoSeccion::Hallazgos => $this->derivadosHallazgos($contenido),
                default => [],
            },
        ];
    }

    // ---------------------------------------------------------------------
    // Normalización
    // ---------------------------------------------------------------------

    /**
     * Rellena con EsquemaSeccion::vacio() las claves que falten, de modo que
     * ni las vistas ni el escritor de Excel tengan que comprobar existencia.
     *
     * @param  array<string, mixed>  $contenido
     * @return array<string, mixed>
     */
    public function normalizar(TipoSeccion $tipo, array $contenido): array
    {
        $base = EsquemaSeccion::vacio($tipo);
        $out = $contenido + $base;

        foreach ($base as $clave => $valorPorDefecto) {
            if (is_array($valorPorDefecto) && ! is_array($out[$clave] ?? null)) {
                $out[$clave] = [];
            }
            if (is_string($valorPorDefecto) && ! is_string($out[$clave] ?? null)) {
                $out[$clave] = $valorPorDefecto;
            }
        }

        // Las listas llegan del JSON con claves posiblemente no contiguas.
        foreach (['items', 'series', 'filas', 'filas_excluidas', 'columnas', 'dimensiones', 'campos', 'registros', 'acciones', 'bloques'] as $lista) {
            if (isset($out[$lista]) && is_array($out[$lista])) {
                $out[$lista] = array_values(array_filter($out[$lista], 'is_array'));
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Derivados: tabla
    // ---------------------------------------------------------------------

    /**
     * Totales de tabla. Las filas excluidas NUNCA entran en `totales`: se
     * totalizan aparte en `totales_excluidas` con los mismos modos, para que
     * el lector pueda ver cuánto pesa lo que se dejó fuera.
     *
     * `modos` sale ya sin las columnas en modo `ninguno`: es la lista de
     * columnas que llevan pie de totales. Antes el filtrado del `ninguno`
     * ocurría dentro del cálculo y cada renderizador decidía por su cuenta si
     * dibujaba la fila de totales; ahora los dos preguntan lo mismo.
     *
     * `modos_efectivos` es el modo REALMENTE aplicado tras los fallbacks (ver
     * modoEfectivo). El Excel escribe una fórmula por columna y necesita saber
     * si esa columna acabó siendo un CTR agregado o una media aritmética; si lo
     * decidiera por su cuenta, la fórmula del Excel y la cifra del PDF podrían
     * discrepar sobre los mismos datos.
     *
     * @param  array<string, mixed>  $contenido
     * @return array{totales: array<string, float|null>, totales_excluidas: array<string, float|null>, conteo: int, conteo_excluidas: int, destacadas: int, modos: array<string, string>, modos_efectivos: array<string, string>, filas: array<int, array{valores: array<string, mixed>, destacada: bool, motivo: string}>, filas_excluidas: array<int, array{valores: array<string, mixed>, destacada: bool, motivo: string}>}
     */
    private function derivadosTabla(array $contenido): array
    {
        $modos = [];
        foreach ((array) ($contenido['totales'] ?? []) as $clave => $modo) {
            // json_decode convierte una clave numérica ("2024") en int y el
            // filtro `is_string($clave)` la descartaba en silencio: la columna
            // perdía su total sin ningún aviso. Se acepta la clave int y se
            // castea, que es como viaja en el resto del contenido.
            if (! is_string($clave) && ! is_int($clave)) {
                continue;
            }
            if (is_string($modo) && in_array($modo, EsquemaSeccion::TOTALES, true) && $modo !== 'ninguno') {
                $modos[(string) $clave] = $modo;
            }
        }

        $filas = $this->normalizarFilasTabla($contenido['filas'] ?? []);
        $excluidas = $this->normalizarFilasTabla($contenido['filas_excluidas'] ?? []);

        $valores = array_column($filas, 'valores');
        $valoresExcluidas = array_column($excluidas, 'valores');

        $efectivos = [];
        foreach ($modos as $clave => $modo) {
            $efectivos[$clave] = $this->modoEfectivo($valores, $contenido, $clave, $modo);
        }

        $destacadas = 0;
        foreach ($filas as $fila) {
            if ($fila['destacada']) {
                $destacadas++;
            }
        }

        return [
            'totales' => $this->totalesDe($valores, $efectivos),
            // Las excluidas se totalizan con el modo efectivo del bloque
            // principal a propósito: son dos vistas de la misma columna y una
            // media aquí frente a un CTR agregado allí no serían comparables.
            'totales_excluidas' => $this->totalesDe($valoresExcluidas, $efectivos),
            'conteo' => count($filas),
            'conteo_excluidas' => count($excluidas),
            'destacadas' => $destacadas,
            'modos' => $modos,
            'modos_efectivos' => $efectivos,
            'filas' => $filas,
            'filas_excluidas' => $excluidas,
        ];
    }

    /**
     * Modo de total realmente aplicable a una columna tras los fallbacks.
     *
     * Existe como método público —y no como una decisión enterrada dentro de
     * ctr()/ponderado()— porque el renderizador de Excel escribe una FÓRMULA
     * por columna: si el motor cae a media aritmética por falta de datos y el
     * Excel escribe igualmente un SUM(clics)/SUM(impresiones), los dos
     * documentos muestran cifras distintas de la misma tabla. Preguntando aquí,
     * las dos ramas parten de la misma respuesta.
     *
     * @param  array<int, array<string, mixed>>  $filas  mapas de valores por columna
     * @param  array<string, mixed>  $contenido
     */
    public function modoEfectivo(array $filas, array $contenido, string $clave, string $modo): string
    {
        if ($modo === 'ninguno') {
            return 'ninguno';
        }

        $filas = array_map(fn ($fila) => $this->valoresFila($fila), $filas);

        if ($modo === 'ctr') {
            $columnas = array_map('strval', array_column((array) ($contenido['columnas'] ?? []), 'clave'));

            if (! in_array('clics', $columnas, true) || ! in_array('impresiones', $columnas, true)) {
                return 'promedio';
            }

            $impresiones = $this->suma($filas, 'impresiones');
            $clics = $this->suma($filas, 'clics');

            if ($clics === null || $impresiones === null || $impresiones <= 0.0) {
                return 'promedio';
            }

            return 'ctr';
        }

        if ($modo === 'ponderado') {
            return $this->pesoPonderado($filas, $clave) > 0.0 ? 'ponderado' : 'promedio';
        }

        return $modo;
    }

    /**
     * Lleva las filas a la forma nueva {valores, destacada, motivo}. Tolera la
     * forma vieja (mapa plano clave => valor) para que un reporte guardado
     * antes del cambio de esquema siga renderizando.
     *
     * @param  array<int|string, mixed>  $filas
     * @return array<int, array{valores: array<string, mixed>, destacada: bool, motivo: string}>
     */
    private function normalizarFilasTabla(mixed $filas): array
    {
        $out = [];

        foreach ((array) $filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $out[] = [
                'valores' => $this->valoresFila($fila),
                'destacada' => (bool) ($fila['destacada'] ?? false),
                'motivo' => isset($fila['motivo']) && is_scalar($fila['motivo']) ? trim((string) $fila['motivo']) : '',
            ];
        }

        return $out;
    }

    /**
     * Mapa de valores por columna de una fila, venga en la forma nueva
     * (anidado bajo `valores`) o en la vieja (plano en la raíz).
     *
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function valoresFila(array $fila): array
    {
        if (isset($fila['valores']) && is_array($fila['valores'])) {
            $valores = $fila['valores'];
        } else {
            $valores = array_diff_key($fila, array_flip(['destacada', 'motivo', 'valores']));
        }

        $out = [];
        foreach ($valores as $clave => $valor) {
            $out[(string) $clave] = $valor;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas  mapas de valores
     * @param  array<string, string>  $modos  modos YA efectivos
     * @return array<string, float|null>
     */
    private function totalesDe(array $filas, array $modos): array
    {
        $totales = [];

        foreach ($modos as $clave => $modo) {
            if ($modo === 'ninguno') {
                continue;
            }

            $totales[$clave] = match ($modo) {
                'suma' => $this->suma($filas, $clave),
                'promedio' => $this->promedio($filas, $clave),
                'ctr' => $this->ctr($filas, $clave),
                'ponderado' => $this->ponderado($filas, $clave),
                default => null,
            };
        }

        return $totales;
    }

    /**
     * CTR agregado. Un CTR total NO es la media de los CTR fila a fila: es
     * clics totales / impresiones totales de la MISMA tabla, que es como lo
     * agrega Search Console. El fallback a media aritmética lo decide
     * modoEfectivo(); aquí se conserva la guarda solo por seguridad numérica.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function ctr(array $filas, string $clave): ?float
    {
        $clics = $this->suma($filas, 'clics');
        $impresiones = $this->suma($filas, 'impresiones');

        if ($clics === null || $impresiones === null || $impresiones <= 0.0) {
            return $this->promedio($filas, $clave);
        }

        return $clics / $impresiones;
    }

    /**
     * Media ponderada por impresiones. La posición media de Search Console es
     * ponderada: una consulta con 10.000 impresiones en posición 3 pesa mucho
     * más que una con 2 impresiones en posición 80, y promediarlas a secas da
     * un número que no corresponde a nada. Por eso el peso son SIEMPRE las
     * impresiones: son la unidad de exposición del dato, no una columna
     * cualquiera. Sin peso utilizable degrada a media aritmética (lo declara
     * modoEfectivo()).
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function ponderado(array $filas, string $clave): ?float
    {
        $numerador = 0.0;
        $pesoTotal = 0.0;

        foreach ($filas as $fila) {
            $valor = $this->numero($fila[$clave] ?? null);
            if ($valor === null) {
                continue;
            }
            $peso = $this->numero($fila['impresiones'] ?? null);
            if ($peso !== null && $peso > 0) {
                $numerador += $valor * $peso;
                $pesoTotal += $peso;
            }
        }

        if ($pesoTotal <= 0.0) {
            return $this->promedio($filas, $clave);
        }

        return $numerador / $pesoTotal;
    }

    /**
     * Suma de pesos utilizables para la media ponderada de una columna: solo
     * cuentan las filas que tienen a la vez valor y peso.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function pesoPonderado(array $filas, string $clave): float
    {
        $pesoTotal = 0.0;

        foreach ($filas as $fila) {
            if ($this->numero($fila[$clave] ?? null) === null) {
                continue;
            }
            $peso = $this->numero($fila['impresiones'] ?? null);
            if ($peso !== null && $peso > 0) {
                $pesoTotal += $peso;
            }
        }

        return $pesoTotal;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function suma(array $filas, string $clave): ?float
    {
        $total = 0.0;
        $hubo = false;

        foreach ($filas as $fila) {
            $valor = $this->numero($fila[$clave] ?? null);
            if ($valor !== null) {
                $total += $valor;
                $hubo = true;
            }
        }

        return $hubo ? $total : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function promedio(array $filas, string $clave): ?float
    {
        $total = 0.0;
        $n = 0;

        foreach ($filas as $fila) {
            $valor = $this->numero($fila[$clave] ?? null);
            if ($valor !== null) {
                $total += $valor;
                $n++;
            }
        }

        return $n > 0 ? $total / $n : null;
    }

    // ---------------------------------------------------------------------
    // Derivados: serie
    // ---------------------------------------------------------------------

    /**
     * Todo lo que el gráfico necesita ya resuelto en números: el renderizador
     * solo interpola.
     *
     * - `maximos`/`totales`: por clave de serie.
     * - `ejes`: máximo y claves de cada eje. Una serie sin `eje` cae en `izq`,
     *   que es el eje por defecto del diseño.
     * - `barras`: por fila, el valor y su ALTURA EN PÍXELES ya escalada contra
     *   el máximo de su propio eje (ese es el sentido del doble eje: clics e
     *   impresiones no comparten escala).
     * - `etiquetas_x`: 7 marcas repartidas; con más de 7 filas el eje X se
     *   vuelve ilegible en el ancho de una página A4.
     * - `agregado`: resumen semanal cuando el eje X son fechas.
     *
     * @param  array<string, mixed>  $contenido
     * @return array{maximos: array<string, float>, totales: array<string, float>, conteo: int, ejes: array<string, array{maximo: float, series: array<int, string>}>, barras: array<int, array{x: string, valores: array<string, array{valor: float, altura: int}>}>, etiquetas_x: array<int, array{indice: int, label: string}>, agregado: array<int, array{label: string, valores: array<string, float>, dias: int, peso: float}>}
     */
    private function derivadosSerie(array $contenido): array
    {
        $claves = [];
        $ejeDe = [];

        foreach ($contenido['series'] as $serie) {
            if (! isset($serie['clave']) || ! is_string($serie['clave']) || $serie['clave'] === '') {
                continue;
            }
            $claves[] = $serie['clave'];
            $eje = (string) ($serie['eje'] ?? '');
            $ejeDe[$serie['clave']] = in_array($eje, EsquemaSeccion::EJES, true) ? $eje : 'izq';
        }

        $maximos = array_fill_keys($claves, 0.0);
        $totales = array_fill_keys($claves, 0.0);

        foreach ($contenido['filas'] as $fila) {
            $valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : [];
            foreach ($claves as $clave) {
                $valor = $this->numero($valores[$clave] ?? null);
                if ($valor === null) {
                    continue;
                }
                $totales[$clave] += $valor;
                if ($valor > $maximos[$clave]) {
                    $maximos[$clave] = $valor;
                }
            }
        }

        $ejes = ['izq' => ['maximo' => 0.0, 'series' => []], 'der' => ['maximo' => 0.0, 'series' => []]];
        foreach ($claves as $clave) {
            $eje = $ejeDe[$clave];
            $ejes[$eje]['series'][] = $clave;
            if ($maximos[$clave] > $ejes[$eje]['maximo']) {
                $ejes[$eje]['maximo'] = $maximos[$clave];
            }
        }

        return [
            'maximos' => $maximos,
            'totales' => $totales,
            'conteo' => count($contenido['filas']),
            'ejes' => $ejes,
            'barras' => $this->barrasSerie($contenido['filas'], $claves, $ejeDe, $ejes),
            'etiquetas_x' => $this->etiquetasX($contenido['filas']),
            'agregado' => $this->agregadoSemanal($contenido['filas'], $claves, $ejes, $totales),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @param  array<int, string>  $claves
     * @param  array<string, string>  $ejeDe
     * @param  array<string, array{maximo: float, series: array<int, string>}>  $ejes
     * @return array<int, array{x: string, valores: array<string, array{valor: float, altura: int}>}>
     */
    private function barrasSerie(array $filas, array $claves, array $ejeDe, array $ejes): array
    {
        $barras = [];

        foreach ($filas as $fila) {
            $valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : [];
            $celdas = [];

            foreach ($claves as $clave) {
                $valor = $this->numero($valores[$clave] ?? null) ?? 0.0;
                $maximo = $ejes[$ejeDe[$clave]]['maximo'];

                $altura = 0;
                if ($valor > 0 && $maximo > 0) {
                    $altura = (int) round($valor / $maximo * self::ALTO_GRAFICO_PX);
                    // Un valor positivo nunca puede dibujarse como barra de 0 px:
                    // desaparecería del gráfico y se leería como "no hubo dato".
                    $altura = max(1, $altura);
                }

                $celdas[$clave] = ['valor' => $valor, 'altura' => $altura];
            }

            $barras[] = [
                'x' => isset($fila['x']) && is_scalar($fila['x']) ? (string) $fila['x'] : '',
                'valores' => $celdas,
            ];
        }

        return $barras;
    }

    /**
     * 7 etiquetas del eje X repartidas uniformemente (primera, última y cinco
     * intermedias). Con 7 filas o menos se etiquetan todas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, array{indice: int, label: string}>
     */
    private function etiquetasX(array $filas): array
    {
        $n = count($filas);
        if ($n === 0) {
            return [];
        }

        $indices = [];
        if ($n <= 7) {
            $indices = range(0, $n - 1);
        } else {
            for ($i = 0; $i < 7; $i++) {
                $indices[] = (int) round($i * ($n - 1) / 6);
            }
            $indices = array_values(array_unique($indices));
        }

        $out = [];
        foreach ($indices as $indice) {
            $fila = $filas[$indice] ?? [];
            $out[] = [
                'indice' => $indice,
                'label' => isset($fila['x']) && is_scalar($fila['x']) ? (string) $fila['x'] : '',
            ];
        }

        return $out;
    }

    /**
     * Agrupación semanal de la serie. Solo tiene sentido cuando el eje X son
     * fechas: si una sola fila no parsea como fecha se devuelve vacío, porque
     * media agrupación es peor que ninguna.
     *
     * `dias` cuenta las filas de la semana con algún valor > 0 (una semana con
     * 3 días de datos y 4 de ceros no es una semana completa y el lector debe
     * verlo). `peso` es la fracción del total que aporta esa semana, medida
     * sobre la serie de referencia: la primera del eje derecho si lo hay —el
     * eje derecho es donde el diseño coloca la magnitud principal— y si no la
     * primera serie declarada.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @param  array<int, string>  $claves
     * @param  array<string, array{maximo: float, series: array<int, string>}>  $ejes
     * @param  array<string, float>  $totales
     * @return array<int, array{label: string, valores: array<string, float>, dias: int, peso: float}>
     */
    private function agregadoSemanal(array $filas, array $claves, array $ejes, array $totales): array
    {
        if ($filas === [] || $claves === []) {
            return [];
        }

        $referencia = $ejes['der']['series'][0] ?? $claves[0];
        $totalReferencia = $totales[$referencia] ?? 0.0;

        $semanas = [];

        foreach ($filas as $fila) {
            $fecha = $this->fechaDe($fila['x'] ?? null);
            if ($fecha === null) {
                return [];
            }

            // Clave año ISO + semana ISO: sin el año, la semana 1 de dos años
            // distintos se fundiría en una sola barra.
            $key = $fecha->format('o-W');

            if (! isset($semanas[$key])) {
                $semanas[$key] = [
                    'label' => 'Sem '.((int) $fecha->format('W')),
                    'valores' => array_fill_keys($claves, 0.0),
                    'dias' => 0,
                    'peso' => 0.0,
                ];
            }

            $valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : [];
            $conDato = false;

            foreach ($claves as $clave) {
                $valor = $this->numero($valores[$clave] ?? null);
                if ($valor === null) {
                    continue;
                }
                $semanas[$key]['valores'][$clave] += $valor;
                if ($valor > 0) {
                    $conDato = true;
                }
            }

            if ($conDato) {
                $semanas[$key]['dias']++;
            }
        }

        $out = [];
        foreach ($semanas as $semana) {
            $semana['peso'] = $totalReferencia > 0
                ? round($semana['valores'][$referencia] / $totalReferencia, 4)
                : 0.0;
            $out[] = $semana;
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Derivados: ficha
    // ---------------------------------------------------------------------

    /**
     * Semáforo de la ficha: una matriz registro × dimensión que resume en un
     * solo color los estados de todos los campos de esa dimensión.
     *
     * El PEOR estado gana (critico > revisar > ok) porque el semáforo es un
     * resumen de riesgo, no una media de cumplimiento: una dimensión con nueve
     * campos correctos y uno crítico sigue teniendo un problema crítico, y
     * pintarla en verde por mayoría lo escondería justo en la vista que existe
     * para encontrarlo. `nd` solo aparece cuando NINGÚN campo de la dimensión
     * tiene estado: no hay dato que resumir.
     *
     * Si la ficha no declara dimensiones, `semaforo` va vacío y el PDF cae a
     * pintar solo las fichas individuales.
     *
     * @param  array<string, mixed>  $contenido
     * @return array{semaforo: array{dimensiones?: array<int, array{clave: string, titulo: string}>, filas?: array<int, array{titulo: string, estados: array<string, string>}>}}
     */
    private function derivadosFicha(array $contenido): array
    {
        $dimensiones = [];
        foreach ($contenido['dimensiones'] ?? [] as $dimension) {
            $clave = (string) ($dimension['clave'] ?? '');
            if ($clave === '') {
                continue;
            }
            $dimensiones[] = ['clave' => $clave, 'titulo' => (string) ($dimension['titulo'] ?? $clave)];
        }

        if ($dimensiones === []) {
            return ['semaforo' => []];
        }

        // Campos agrupados por dimensión; un campo sin dimensión no entra en el
        // semáforo (solo se ve en la ficha de detalle).
        $camposDe = [];
        foreach ($contenido['campos'] ?? [] as $campo) {
            $clave = (string) ($campo['clave'] ?? '');
            $dimension = (string) ($campo['dimension'] ?? '');
            if ($clave === '' || $dimension === '') {
                continue;
            }
            $camposDe[$dimension][] = $clave;
        }

        $filas = [];
        foreach ($contenido['registros'] ?? [] as $registro) {
            $valores = is_array($registro['valores'] ?? null) ? $registro['valores'] : [];
            $estados = [];

            foreach ($dimensiones as $dimension) {
                $peor = 'nd';
                foreach ($camposDe[$dimension['clave']] ?? [] as $campo) {
                    $celda = $valores[$campo] ?? null;
                    $estado = is_array($celda) ? (string) ($celda['estado'] ?? '') : '';
                    if (! isset(self::PESO_ESTADO[$estado]) || $estado === 'nd') {
                        continue;
                    }
                    if (self::PESO_ESTADO[$estado] > self::PESO_ESTADO[$peor]) {
                        $peor = $estado;
                    }
                }
                $estados[$dimension['clave']] = $peor;
            }

            $filas[] = [
                'titulo' => isset($registro['titulo']) && is_scalar($registro['titulo']) ? (string) $registro['titulo'] : '',
                'estados' => $estados,
            ];
        }

        return ['semaforo' => ['dimensiones' => $dimensiones, 'filas' => $filas]];
    }

    // ---------------------------------------------------------------------
    // Derivados: kpis
    // ---------------------------------------------------------------------

    /**
     * Reparte los KPIs en dos bandejas según el flag `destacado`. El diseño
     * pinta los principales grandes y los secundarios en una tira compacta; el
     * reparto se hace aquí para que el Excel agrupe igual que el PDF. Sin flag,
     * un KPI es principal: es el comportamiento que había antes del flag.
     *
     * @param  array<string, mixed>  $contenido
     * @return array{principales: array<int, array<string, mixed>>, secundarios: array<int, array<string, mixed>>}
     */
    private function derivadosKpis(array $contenido): array
    {
        $principales = [];
        $secundarios = [];

        foreach ($contenido['items'] ?? [] as $item) {
            $destacado = ! array_key_exists('destacado', $item) || (bool) $item['destacado'];
            if ($destacado) {
                $principales[] = $item;
            } else {
                $secundarios[] = $item;
            }
        }

        return ['principales' => $principales, 'secundarios' => $secundarios];
    }

    // ---------------------------------------------------------------------
    // Derivados: plan y hallazgos
    // ---------------------------------------------------------------------

    /**
     * Score = impacto / esfuerzo. Ordena por prioridad declarada primero
     * (P0 antes que P1 aunque el P1 tenga mejor score: una prioridad es una
     * decisión, no un cálculo) y por score descendente dentro de cada grupo.
     *
     * @param  array<string, mixed>  $contenido
     * @return array{acciones: array<int, array<string, mixed>>, por_prioridad: array<string, int>}
     */
    private function derivadosPlan(array $contenido): array
    {
        $acciones = [];
        $porPrioridad = ['p0' => 0, 'p1' => 0, 'p2' => 0, 'p3' => 0];

        foreach ($contenido['acciones'] as $accion) {
            $prioridad = PrioridadAccion::tryFrom((string) ($accion['prioridad'] ?? ''));
            $impacto = (float) ($this->numero($accion['impacto'] ?? null) ?? 0);
            $esfuerzo = (float) ($this->numero($accion['esfuerzo'] ?? null) ?? 0);

            $accion['prioridad'] = $prioridad?->value ?? '';
            $accion['prioridad_label'] = $prioridad ? mb_strtoupper($prioridad->value) : '—';
            $accion['impacto'] = $impacto;
            $accion['esfuerzo'] = $esfuerzo;
            $accion['score'] = $esfuerzo > 0 ? round($impacto / $esfuerzo, 2) : 0.0;
            $accion['orden_prioridad'] = $prioridad?->orden() ?? 99;

            foreach (['accion', 'evidencia', 'area', 'kpi'] as $texto) {
                $accion[$texto] = isset($accion[$texto]) && is_scalar($accion[$texto]) ? (string) $accion[$texto] : '';
            }

            if ($prioridad) {
                $porPrioridad[$prioridad->value]++;
            }

            $acciones[] = $accion;
        }

        usort($acciones, function (array $a, array $b): int {
            return [$a['orden_prioridad'], -$a['score']] <=> [$b['orden_prioridad'], -$b['score']];
        });

        return ['acciones' => array_values($acciones), 'por_prioridad' => $porPrioridad];
    }

    /**
     * @param  array<string, mixed>  $contenido
     * @return array{items: array<int, array<string, mixed>>, por_severidad: array<string, int>}
     */
    private function derivadosHallazgos(array $contenido): array
    {
        $items = [];
        $porSeveridad = ['critico' => 0, 'alto' => 0, 'medio' => 0, 'informativo' => 0];

        foreach ($contenido['items'] as $item) {
            $severidad = SeveridadHallazgo::tryFrom((string) ($item['severidad'] ?? ''));

            $item['severidad'] = $severidad?->value ?? '';
            $item['severidad_label'] = $severidad ? self::LABELS_SEVERIDAD[$severidad->value] : '—';
            $item['orden_severidad'] = $severidad?->orden() ?? 99;
            $item['titulo'] = isset($item['titulo']) && is_scalar($item['titulo']) ? (string) $item['titulo'] : '';
            $item['evidencia'] = isset($item['evidencia']) && is_scalar($item['evidencia']) ? (string) $item['evidencia'] : '';

            if ($severidad) {
                $porSeveridad[$severidad->value]++;
            }

            $items[] = $item;
        }

        usort($items, fn (array $a, array $b): int => $a['orden_severidad'] <=> $b['orden_severidad']);

        $items = array_values($items);
        foreach ($items as $i => $item) {
            $items[$i]['indice'] = $i + 1;
        }

        return ['items' => $items, 'por_severidad' => $porSeveridad];
    }

    // ---------------------------------------------------------------------
    // Formato de celda
    // ---------------------------------------------------------------------

    /**
     * Pinta un valor según el formato declarado en la columna. Es el único
     * sitio donde se decide cómo se ve un número, para que el PDF y las
     * cabeceras del Excel coincidan. Vacío o null → self::AUSENTE ('n/d'); ver
     * la nota de esa constante sobre por qué aquí no se usa el em dash.
     */
    public function formatear(mixed $valor, string $formato): string
    {
        if ($valor === null || $valor === '' || (is_array($valor) && $valor === [])) {
            return self::AUSENTE;
        }

        if ($formato === 'nivel') {
            // ALTA / MEDIA / BAJA: escala cualitativa, siempre en mayúsculas.
            return is_scalar($valor) ? mb_strtoupper(trim((string) $valor)) : self::AUSENTE;
        }

        if ($formato === 'texto' || $formato === 'fecha') {
            return is_scalar($valor) ? trim((string) $valor) : self::AUSENTE;
        }

        $numero = $this->numero($valor);

        if ($numero === null) {
            // Un valor no numérico en una columna numérica se muestra tal cual
            // en vez de romper el render.
            return is_scalar($valor) ? trim((string) $valor) : self::AUSENTE;
        }

        return match ($formato) {
            'numero' => number_format($numero, 0, '.', ','),
            'decimal' => number_format($numero, 1, '.', ','),
            'porcentaje' => number_format($this->aPorcentaje($numero), 2, '.', ',').'%',
            'moneda' => '$'.number_format($numero, 2, '.', ','),
            default => number_format($numero, 0, '.', ','),
        };
    }

    /**
     * Los CTR pueden llegar como fracción (0.0432) o ya en puntos (4.32). Se
     * asume fracción cuando el valor cabe en -1..1 AMBOS INCLUSIVE: con el
     * intervalo abierto, un CTR de 1.0 (el 100 %, una fila con tantos clics
     * como impresiones) caía del lado "ya en puntos" y se imprimía como
     * 1,00 %. El 1.0 exacto es un caso real en tablas con pocas impresiones.
     */
    public function aPorcentaje(float $numero): float
    {
        return ($numero >= -1.0 && $numero <= 1.0) ? $numero * 100 : $numero;
    }

    /**
     * Convierte a float lo que se pueda; devuelve null para lo que no sea
     * numérico, de modo que las celdas de texto se ignoren en los totales en
     * vez de contar como cero.
     */
    public function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return is_nan((float) $valor) || is_infinite((float) $valor) ? null : (float) $valor;
        }

        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim($valor);
        if ($limpio === '') {
            return null;
        }

        // Tolera lo que sale de una hoja de cálculo: "1,234.5", "12%", "$980.00".
        $limpio = str_replace(['$', '%', ',', ' ', "\u{00A0}"], '', $limpio);

        if (! is_numeric($limpio)) {
            return null;
        }

        return (float) $limpio;
    }

    // ---------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------

    /**
     * Fuentes de datos declaradas en el reporte, saneadas a lista de strings.
     *
     * @return array<int, string>
     */
    private function fuentes(Reporte $reporte): array
    {
        $out = [];

        foreach ((array) ($reporte->fuentes ?? []) as $fuente) {
            if (! is_scalar($fuente)) {
                continue;
            }
            $texto = trim((string) $fuente);
            if ($texto !== '') {
                $out[] = $texto;
            }
        }

        return array_values(array_unique($out));
    }

    private function textoONull(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /**
     * Etiqueta de un rango, ej. '12 may — 11 jul 2026'. Cuando las dos fechas
     * caen en el mismo año el año se escribe una sola vez, al final: es el
     * formato de la maqueta y evita repetir "2026" dos veces en una línea que
     * ya compite por espacio con el periodo principal.
     */
    private function rangoLabel(mixed $inicio, mixed $fin): ?string
    {
        if (! $inicio instanceof \DateTimeInterface || ! $fin instanceof \DateTimeInterface) {
            return null;
        }

        $inicioLabel = $inicio->format('Y') === $fin->format('Y')
            ? $inicio->format('d').' '.self::MESES[((int) $inicio->format('n')) - 1]
            : $this->fechaCorta($inicio);

        return $inicioLabel.' — '.$this->fechaCorta($fin);
    }

    /**
     * Parsea el eje X de una serie como fecha. Solo se aceptan formatos
     * inequívocos (ISO y d/m/Y): dejar que strtotime adivine convertiría
     * etiquetas como "Sem 3" o "Top 10" en fechas absurdas y el agregado
     * semanal saldría inventado.
     */
    private function fechaDe(mixed $valor): ?\DateTimeImmutable
    {
        if ($valor instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromFormat('U', (string) $valor->getTimestamp()) ?: null;
        }

        if (! is_string($valor)) {
            return null;
        }

        $texto = trim($valor);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ].*)?$/', $texto, $m)) {
            return $this->fechaValida((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
            return $this->fechaValida((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function fechaValida(int $anio, int $mes, int $dia): ?\DateTimeImmutable
    {
        if (! checkdate($mes, $dia, $anio)) {
            return null;
        }

        return (new \DateTimeImmutable())->setDate($anio, $mes, $dia)->setTime(0, 0);
    }

    /** 'd M Y' en español, ej. '12 jul 2026'. Cadena vacía si no hay fecha. */
    private function fechaCorta(mixed $fecha): string
    {
        if (! $fecha instanceof \DateTimeInterface) {
            return '';
        }

        return $fecha->format('d').' '.self::MESES[((int) $fecha->format('n')) - 1].' '.$fecha->format('Y');
    }
}
