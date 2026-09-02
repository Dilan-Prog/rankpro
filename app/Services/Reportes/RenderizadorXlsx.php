<?php

namespace App\Services\Reportes;

use App\Models\Reporte;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renderiza el entregable como libro de Excel: una hoja índice que hace de
 * portada y una hoja por sección visible.
 *
 * El Excel NO imita la maqueta del PDF: hereda su semántica. Lo que viaja
 * intacto son tres cosas —el encabezado oscuro, la jerarquía de totales
 * (computado en negro y grueso, excluido en gris y fino) y el tratamiento de
 * las filas excluidas—. Todo lo demás se reorganiza para lo que una hoja de
 * cálculo sabe hacer y un PDF no: ordenar, filtrar y sumar.
 *
 * De ahí las tres reglas estructurales:
 *
 *  1. La fila 1 de cada hoja es el encabezado de la tabla, congelada y con
 *     autofiltro. Los títulos, avisos y notas de la sección van AL PIE, no
 *     arriba: cualquier cosa por encima de la fila de cabecera rompería el
 *     autofiltro y obligaría a combinar celdas en medio de los datos.
 *  2. Todo valor numérico se escribe como número, nunca como texto
 *     preformateado. El formato («#,##0», «0,00%», «dd/mm/aaaa», «#,##0 ms»)
 *     vive en el estilo de celda. El cliente debe poder sumar, filtrar y hacer
 *     su propia tabla dinámica.
 *  3. Además del valor, se escribe la FÓRMULA que lo produce. Un cliente que
 *     recibe el Excel espera poder borrar una fila y ver el total
 *     recalcularse; si escribiéramos sólo el valor congelado, la hoja mentiría
 *     en cuanto alguien la tocara. El valor del Armador —el mismo que sale en
 *     el PDF— se conserva como respaldo dentro de un IFERROR.
 *
 * Aritmética: ninguna. Los números salen del Armador. En particular el modo de
 * total de cada columna se lee de `modos_efectivos`, que es el modo que el
 * Armador aplicó DESPUÉS de sus propios fallbacks. Este renderizador no vuelve
 * a decidir un fallback por su cuenta: si lo hiciera, el PDF y el Excel
 * podrían divergir, que es justo lo que se quiere hacer imposible.
 */
class RenderizadorXlsx
{
    /**
     * Nombres de hoja ya usados, para desambiguar.
     *
     * @var array<string, int>
     */
    private array $nombresUsados = [];

    /** Nombre de la hoja de portada. */
    private const HOJA_INDICE = 'Índice';

    /**
     * Nombres que no puede llevar ninguna hoja de sección: el de la portada,
     * porque ya está tomado antes de recorrer las secciones, y `History`, que
     * Excel se reserva para el libro compartido. Sin esta reserva, una sección
     * titulada «Índice» hacía que setTitle() lanzara y el usuario recibía un
     * 500 sin mensaje.
     */
    private const NOMBRES_RESERVADOS = [self::HOJA_INDICE, 'History'];

    private Armador $armador;

    /** @return string Bytes del .xlsx. */
    public function generar(Reporte $reporte): string
    {
        $this->armador = app(Armador::class);
        $this->nombresUsados = [];

        foreach (self::NOMBRES_RESERVADOS as $reservado) {
            $this->nombresUsados[mb_strtolower($reservado)] = 1;
        }

        $datos = $this->armador->armar($reporte);
        $rep = $datos['reporte'];

        $libro = new Spreadsheet;
        $libro->getProperties()
            ->setTitle((string) ($rep['titulo'] ?? ''))
            ->setCompany('RankPro Solutions')
            ->setSubject((string) ($rep['area_label'] ?? ''))
            ->setDescription((string) ($rep['periodo_label'] ?? ''));

        $indice = $libro->getActiveSheet();
        $indice->setTitle(self::HOJA_INDICE);

        $entradas = [];

        foreach ($datos['secciones'] as $i => $seccion) {
            $hoja = $libro->createSheet();
            $titulo = (string) ($seccion['titulo'] ?? '');
            $nombre = $this->nombreHoja($titulo !== '' ? $titulo : 'Sección '.($i + 1));
            $hoja->setTitle($nombre);

            $filas = match ($seccion['tipo']) {
                'kpis' => $this->hojaKpis($hoja, $seccion),
                'hallazgos' => $this->hojaHallazgos($hoja, $seccion),
                'serie' => $this->hojaSerie($hoja, $seccion),
                'tabla' => $this->hojaTabla($hoja, $seccion),
                'ficha' => $this->hojaFicha($hoja, $seccion),
                'plan' => $this->hojaPlan($hoja, $seccion),
                'texto' => $this->hojaTexto($hoja, $seccion),
                default => 0,
            };

            $entradas[] = [
                'nombre' => $nombre,
                'titulo' => $titulo,
                'filas' => $filas,
            ] + $this->estadoHoja($seccion, $filas);
        }

        $this->hojaIndice($indice, $rep, $entradas);
        $libro->setActiveSheetIndex(0);

        return $this->bytes($libro);
    }

    /**
     * Se escribe a un fichero temporal y se lee de vuelta, en lugar de capturar
     * php://output con ob_start(): el writer de PhpSpreadsheet necesita un
     * stream con seek para cerrar el ZIP, y ob_start() dentro de un job en cola
     * o de una respuesta ya iniciada deja el búfer de salida en un estado que no
     * es nuestro. El temporal se borra siempre, incluso si el writer lanza.
     */
    private function bytes(Spreadsheet $libro): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'rep_');

        try {
            // El writer precalcula (por defecto) y guarda el resultado como
            // valor cacheado de cada celda: eso es lo que ve quien abre el
            // libro antes de que Excel recalcule, y por eso todas las fórmulas
            // de este renderizador están escritas para dar el mismo número en
            // el motor de PhpSpreadsheet y en el de Excel.
            (new Xlsx($libro))->save($ruta);
            $bytes = (string) file_get_contents($ruta);
        } finally {
            @unlink($ruta);
            $libro->disconnectWorksheets();
        }

        return $bytes;
    }

    // ---------------------------------------------------------------------
    // Nombres de hoja
    // ---------------------------------------------------------------------

    /**
     * Excel prohíbe : \ / ? * [ ] en los nombres de hoja y los limita a 31
     * caracteres. Si dos secciones colisionan tras truncar —o si una choca con
     * un nombre reservado— se numeran.
     */
    private function nombreHoja(string $titulo): string
    {
        $limpio = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $titulo);
        $limpio = trim(preg_replace('/\s+/u', ' ', $limpio) ?? '');
        $limpio = trim($limpio, "'");

        if ($limpio === '') {
            $limpio = 'Sección';
        }

        $base = mb_substr($limpio, 0, 31);
        $clave = mb_strtolower($base);

        if (! isset($this->nombresUsados[$clave])) {
            $this->nombresUsados[$clave] = 1;

            return $base;
        }

        // El contador de la clave sigue creciendo aunque el nombre resultante
        // sea distinto, para que «Índice (2)» no vuelva a salir en la tercera.
        do {
            $n = ++$this->nombresUsados[$clave];
            $sufijo = ' ('.$n.')';
            $candidato = mb_substr($base, 0, 31 - mb_strlen($sufijo)).$sufijo;
        } while (isset($this->nombresUsados[mb_strtolower($candidato)]));

        $this->nombresUsados[mb_strtolower($candidato)] = 1;

        return $candidato;
    }

    // ---------------------------------------------------------------------
    // Hoja índice (portada)
    // ---------------------------------------------------------------------

    /**
     * Estado de la hoja para el índice: replica el semáforo del PDF.
     *
     * @param  array<string, mixed>  $seccion
     * @return array{estado: string, estado_label: string}
     */
    private function estadoHoja(array $seccion, int $filas): array
    {
        $aviso = $seccion['aviso'] ?? null;

        if ($filas === 0) {
            return ['estado' => 'vacia', 'estado_label' => 'SIN DATOS'];
        }

        if (is_array($aviso) && ($aviso['texto'] ?? '') !== '') {
            $fecha = trim((string) ($aviso['fecha'] ?? ''));

            return [
                'estado' => 'aviso',
                'estado_label' => $fecha !== '' ? 'DATO '.mb_strtoupper($fecha) : 'DATO DESACTUALIZADO',
            ];
        }

        if (($seccion['tipo'] ?? '') === 'tabla' && ! empty($seccion['contenido']['filas_excluidas'])) {
            return ['estado' => 'aviso', 'estado_label' => 'CON EXCLUIDAS'];
        }

        return ['estado' => 'completa', 'estado_label' => 'COMPLETA'];
    }

    /**
     * @param  array<string, mixed>  $rep
     * @param  array<int, array<string, mixed>>  $entradas
     */
    private function hojaIndice(Worksheet $hoja, array $rep, array $entradas): void
    {
        $banda = array_filter([
            mb_strtoupper((string) ($rep['cliente']['empresa'] ?? $rep['cliente']['nombre'] ?? '')),
            mb_strtoupper((string) ($rep['titulo'] ?? '')),
            mb_strtoupper((string) ($rep['periodo_label'] ?? '')),
        ], fn ($t) => $t !== '');

        $hoja->setCellValueExplicit('A1', implode('  ·  ', $banda), DataType::TYPE_STRING);
        $hoja->mergeCells('A1:D1');
        EstiloXlsx::bandaPortada($hoja, 'A1:D1');
        $hoja->getRowDimension(1)->setRowHeight(34);
        // Fila 1 congelada también aquí: el libro entero se comporta igual.
        $hoja->freezePane('A2');

        $fuentes = $rep['fuentes'] ?? null;
        if (is_array($fuentes)) {
            $fuentes = implode(' · ', array_map(fn ($f) => is_scalar($f) ? (string) $f : '', $fuentes));
        }

        $portada = [
            ['Emitido', $rep['fecha_emision'] ?? null],
            ['Folio', $rep['numero'] ?? null],
            ['Estado', $rep['estado'] ?? null],
            ['Versión', $rep['version_etiqueta'] ?? null],
            ['Cliente', $rep['cliente']['empresa'] ?? null],
            ['Contacto', $rep['cliente']['nombre'] ?? null],
            ['Sitio web', $rep['sitio_web'] ?? ($rep['cliente']['sitio_web'] ?? null)],
            ['Área', $rep['area_label'] ?? null],
            ['Periodo', $rep['periodo_label'] ?? null],
            ['Días del periodo', $rep['dias_periodo'] ?? null],
            ['Comparativa', $rep['comparativa_label'] ?? null],
            ['Fuentes', $fuentes],
            ['Notas de alcance', $rep['notas_alcance'] ?? null],
        ];

        $fila = 3;
        foreach ($portada as [$etiqueta, $valor]) {
            $texto = is_scalar($valor) ? trim((string) $valor) : '';
            if ($texto === '') {
                continue;
            }
            $hoja->setCellValueExplicit('A'.$fila, mb_strtoupper($etiqueta), DataType::TYPE_STRING);
            EstiloXlsx::etiqueta($hoja, 'A'.$fila);
            $hoja->setCellValueExplicit('B'.$fila, $texto, DataType::TYPE_STRING);
            EstiloXlsx::valor($hoja, 'B'.$fila.':D'.$fila);
            $fila++;
        }

        $fila++;
        $hoja->setCellValueExplicit('A'.$fila, 'CONTENIDO DEL LIBRO', DataType::TYPE_STRING);
        EstiloXlsx::rotulo($hoja, 'A'.$fila);
        $fila++;

        $cabecera = $fila;
        foreach (['#', 'HOJA', 'FILAS', 'ESTADO'] as $i => $texto) {
            $hoja->setCellValueExplicit($this->col($i + 1).$fila, $texto, DataType::TYPE_STRING);
        }
        EstiloXlsx::encabezado($hoja, 'A'.$cabecera.':D'.$cabecera);
        $hoja->getRowDimension($cabecera)->setRowHeight(22);
        $fila++;

        foreach ($entradas as $i => $entrada) {
            $hoja->setCellValueExplicit('A'.$fila, $i + 1, DataType::TYPE_NUMERIC);

            // Hipervínculo interno: el nombre de hoja es el destino y la
            // etiqueta a la vez. `sheet://` es como PhpSpreadsheet escribe un
            // enlace dentro del propio libro; una URL normal abriría el
            // navegador.
            $hoja->setCellValueExplicit('B'.$fila, $entrada['nombre'], DataType::TYPE_STRING);
            $hoja->getCell('B'.$fila)->getHyperlink()
                ->setUrl("sheet://'".str_replace("'", "''", $entrada['nombre'])."'!A1")
                ->setTooltip((string) $entrada['titulo']);
            $hoja->getStyle('B'.$fila)->getFont()->setUnderline(true)
                ->getColor()->setRGB(EstiloXlsx::MARCA);

            $hoja->setCellValueExplicit('C'.$fila, (int) $entrada['filas'], DataType::TYPE_NUMERIC);
            $hoja->getStyle('C'.$fila)->getNumberFormat()
                ->setFormatCode(EstiloXlsx::formatoNumerico('numero'));

            $hoja->setCellValueExplicit('D'.$fila, (string) $entrada['estado_label'], DataType::TYPE_STRING);
            $hoja->getStyle('D'.$fila)->getFont()->setBold(true)
                ->getColor()->setRGB(EstiloXlsx::colorEstadoHoja((string) $entrada['estado']));

            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':D'.$fila);
            }
            $fila++;
        }

        $hoja->getStyle('A'.($cabecera + 1).':A'.max($cabecera + 1, $fila - 1))
            ->getAlignment()->setHorizontal(EstiloXlsx::alineacion('centro'));
        $hoja->getStyle('C'.($cabecera + 1).':C'.max($cabecera + 1, $fila - 1))
            ->getAlignment()->setHorizontal(EstiloXlsx::alineacion('derecha'));

        $this->anchos($hoja, [6, 40, 10, 22]);
    }

    // ---------------------------------------------------------------------
    // Hojas por tipo. Todas devuelven el número de filas de datos escritas.
    // ---------------------------------------------------------------------

    /** @param array<string, mixed> $seccion */
    private function hojaKpis(Worksheet $hoja, array $seccion): int
    {
        $d = $this->derivados($seccion, 'kpis');

        // `principales` y `secundarios` ya vienen separados y ordenados por el
        // Armador; la columna GRUPO deja que el cliente filtre por ese corte
        // en lugar de perderlo, que es lo que pasaría con dos bloques sueltos.
        $grupos = [];
        foreach (['principales' => 'Principal', 'secundarios' => 'Secundario'] as $clave => $etiqueta) {
            foreach ((array) ($d[$clave] ?? []) as $kpi) {
                if (is_array($kpi)) {
                    $grupos[] = [$etiqueta, $kpi];
                }
            }
        }

        if ($grupos === []) {
            foreach ((array) ($seccion['contenido']['items'] ?? []) as $kpi) {
                if (is_array($kpi)) {
                    $grupos[] = ['Principal', $kpi];
                }
            }
        }

        $this->cabeceras($hoja, ['GRUPO', 'INDICADOR', 'VALOR', 'COMPARATIVO', 'DIRECCIÓN', 'DATO DE', 'DETALLE']);

        $fila = 2;
        foreach ($grupos as $i => [$grupo, $kpi]) {
            $hoja->setCellValueExplicit('A'.$fila, $grupo, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('B'.$fila, (string) ($kpi['label'] ?? ''), DataType::TYPE_STRING);
            $this->celda($hoja, 'C'.$fila, $kpi['valor'] ?? null, (string) ($kpi['formato'] ?? 'texto'));
            $hoja->setCellValueExplicit('D'.$fila, (string) ($kpi['comparativo'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('E'.$fila, (string) ($kpi['direccion'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('F'.$fila, (string) ($kpi['desactualizado'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('G'.$fila, (string) ($kpi['detalle'] ?? ''), DataType::TYPE_STRING);

            if (! empty($kpi['destacado'])) {
                EstiloXlsx::destacada($hoja, 'B'.$fila.':C'.$fila);
            }
            if (($kpi['desactualizado'] ?? '') !== '') {
                EstiloXlsx::noDisponible($hoja, 'F'.$fila);
            }
            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':G'.$fila);
            }
            $fila++;
        }

        $this->cerrarBloque($hoja, 'G', $fila - 1);
        EstiloXlsx::ajustar($hoja, 'G2:G'.max(2, $fila - 1));
        $this->anchos($hoja, [13, 40, 16, 22, 13, 12, 52]);
        $this->pieDeSeccion($hoja, $seccion, $fila, 'G');

        return count($grupos);
    }

    /** @param array<string, mixed> $seccion */
    private function hojaHallazgos(Worksheet $hoja, array $seccion): int
    {
        $items = (array) ($this->derivados($seccion, 'hallazgos')['items'] ?? []);

        $this->cabeceras($hoja, ['#', 'SEVERIDAD', 'HALLAZGO', 'EVIDENCIA']);

        $fila = 2;
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $hoja->setCellValueExplicit('A'.$fila, (int) ($item['indice'] ?? $i + 1), DataType::TYPE_NUMERIC);
            $hoja->setCellValueExplicit('B'.$fila, (string) ($item['severidad_label'] ?? '—'), DataType::TYPE_STRING);
            [$texto, $fondo] = EstiloXlsx::colorSeveridad((string) ($item['severidad'] ?? ''));
            EstiloXlsx::badge($hoja, 'B'.$fila, $texto, $fondo);
            $hoja->setCellValueExplicit('C'.$fila, (string) ($item['titulo'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('D'.$fila, (string) ($item['evidencia'] ?? ''), DataType::TYPE_STRING);
            $fila++;
        }

        $this->cerrarBloque($hoja, 'D', $fila - 1);
        EstiloXlsx::ajustar($hoja, 'C2:D'.max(2, $fila - 1));
        $this->anchos($hoja, [6, 14, 62, 62]);
        $this->pieDeSeccion($hoja, $seccion, $fila, 'D');

        return $fila - 2;
    }

    /**
     * El gráfico de tendencia no se traduce: se entrega como tabla de N filas
     * (fecha + una columna por serie) y, debajo, el agregado semanal que
     * calcula el Armador. Si el cliente quiere gráfico, lo hace él con sus
     * propios ejes; incrustar uno aquí sería imponerle una escala.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function hojaSerie(Worksheet $hoja, array $seccion): int
    {
        $contenido = $seccion['contenido'];
        $d = $this->derivados($seccion, 'serie');

        $series = array_values(array_filter(
            (array) ($contenido['series'] ?? []),
            fn ($s) => is_array($s) && isset($s['clave'])
        ));

        $cabeceras = [mb_strtoupper((string) ($contenido['etiqueta_x'] ?: 'Fecha'))];
        foreach ($series as $s) {
            $cabeceras[] = mb_strtoupper((string) ($s['titulo'] ?? $s['clave']));
        }
        $ultimaCol = $this->col(count($cabeceras));
        $this->cabeceras($hoja, $cabeceras);

        $primera = 2;
        $fila = $primera;
        foreach ((array) ($contenido['filas'] ?? []) as $i => $f) {
            if (! is_array($f)) {
                continue;
            }
            $this->celda($hoja, 'A'.$fila, $f['x'] ?? null, 'fecha');
            $valores = is_array($f['valores'] ?? null) ? $f['valores'] : [];
            foreach ($series as $c => $s) {
                $this->celda($hoja, $this->col($c + 2).$fila, $valores[$s['clave']] ?? null, 'decimal', (string) $s['clave']);
            }
            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            }
            $fila++;
        }
        $ultima = $fila - 1;
        $conteo = max(0, $ultima - $primera + 1);

        $this->cerrarBloque($hoja, $ultimaCol, $ultima);

        if ($conteo > 0) {
            $hoja->setCellValueExplicit('A'.$fila, 'TOTAL', DataType::TYPE_STRING);
            foreach ($series as $c => $s) {
                $letra = $this->col($c + 2);
                // Fórmula viva: si el cliente borra una fila, el pie se
                // recalcula solo. SUM ignora el texto «n/d», así que no puede
                // devolver #VALUE!; aun así se deja el respaldo del Armador.
                $respaldo = $this->respaldo($d['totales'][$s['clave']] ?? null, 'decimal');
                $hoja->setCellValue($letra.$fila, '=IFERROR(SUM('.$letra.$primera.':'.$letra.$ultima.'),'.$respaldo.')');
                $hoja->getStyle($letra.$fila)->getNumberFormat()
                    ->setFormatCode(EstiloXlsx::formatoNumerico('decimal', (string) $s['clave']));
            }
            EstiloXlsx::totalComputado($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            $fila++;
        }

        $fila = $this->bloqueAgregado($hoja, $d['agregado'] ?? null, $series, $fila + 1, $ultimaCol);
        $fila = $this->leyendaSeries($hoja, $series, $fila + 1);

        $this->anchos($hoja, array_merge([16], array_fill(0, max(1, count($series)), 14)));
        $this->pieDeSeccion($hoja, $seccion, $fila, $ultimaCol);

        return $conteo;
    }

    /**
     * Vuelca el agregado semanal del Armador como una segunda tabla, debajo de
     * la diaria y con su propio sub-encabezado. No se recalcula nada: son las
     * mismas cifras que el PDF usa para su lectura del periodo.
     *
     * @param  array<int, array<string, mixed>>  $series
     */
    private function bloqueAgregado(Worksheet $hoja, mixed $agregado, array $series, int $fila, string $ultimaCol): int
    {
        $filas = [];
        if (is_array($agregado)) {
            $filas = is_array($agregado['filas'] ?? null) ? $agregado['filas'] : $agregado;
        }
        $filas = array_values(array_filter((array) $filas, 'is_array'));

        if ($filas === []) {
            return $fila;
        }

        $hoja->setCellValueExplicit('A'.$fila, 'AGREGADO SEMANAL', DataType::TYPE_STRING);
        EstiloXlsx::rotulo($hoja, 'A'.$fila);
        $fila++;

        $hoja->setCellValueExplicit('A'.$fila, 'SEMANA', DataType::TYPE_STRING);
        foreach ($series as $c => $s) {
            $hoja->setCellValueExplicit(
                $this->col($c + 2).$fila,
                mb_strtoupper((string) ($s['titulo'] ?? $s['clave'])),
                DataType::TYPE_STRING
            );
        }
        EstiloXlsx::subEncabezado($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
        $fila++;

        foreach ($filas as $i => $f) {
            $this->celda($hoja, 'A'.$fila, $f['x'] ?? ($f['semana'] ?? null), 'texto');
            $valores = is_array($f['valores'] ?? null) ? $f['valores'] : $f;
            foreach ($series as $c => $s) {
                $this->celda($hoja, $this->col($c + 2).$fila, $valores[$s['clave']] ?? null, 'decimal', (string) $s['clave']);
            }
            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            }
            $fila++;
        }

        return $fila;
    }

    /**
     * Leyenda de series: título en su color de gráfica y el eje al que se
     * asigna. Es lo único que sobrevive del gráfico, y sirve para que el
     * cliente reconstruya el mismo dibujo si quiere.
     *
     * @param  array<int, array<string, mixed>>  $series
     */
    private function leyendaSeries(Worksheet $hoja, array $series, int $fila): int
    {
        $conMeta = array_filter($series, fn ($s) => ($s['eje'] ?? '') !== '' || ($s['color'] ?? '') !== '');
        if ($conMeta === []) {
            return $fila;
        }

        $hoja->setCellValueExplicit('A'.$fila, 'SERIES', DataType::TYPE_STRING);
        EstiloXlsx::rotulo($hoja, 'A'.$fila);
        $fila++;

        foreach ($series as $s) {
            $hoja->setCellValueExplicit('A'.$fila, (string) ($s['titulo'] ?? $s['clave']), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit(
                'B'.$fila,
                ($s['eje'] ?? '') === 'der' ? 'Eje derecho' : 'Eje izquierdo',
                DataType::TYPE_STRING
            );
            EstiloXlsx::nota($hoja, 'B'.$fila);
            $color = ltrim((string) ($s['color'] ?? ''), '#');
            if (preg_match('/^[0-9A-Fa-f]{6}$/', $color) === 1) {
                $hoja->getStyle('A'.$fila)->getFont()->setBold(true)->getColor()->setRGB(mb_strtoupper($color));
            }
            $fila++;
        }

        return $fila;
    }

    /**
     * Tabla densa: el corazón del entregable.
     *
     * Orden de escritura y por qué: filas computadas, luego filas excluidas,
     * luego el pie. Las excluidas van al final del bloque —dentro del
     * autofiltro, nunca ocultas, tachadas y en gris— y el rango de las
     * fórmulas de total termina en la última fila computada. Así quedan fuera
     * del total POR CONSTRUCCIÓN: no hay ninguna condición que mantener, es la
     * geometría de la hoja la que lo garantiza, y sigue siendo cierto aunque
     * alguien añada filas después.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function hojaTabla(Worksheet $hoja, array $seccion): int
    {
        $contenido = $seccion['contenido'];
        $d = $this->derivados($seccion, 'tabla');

        $columnas = array_values(array_filter(
            (array) ($contenido['columnas'] ?? []),
            fn ($c) => is_array($c) && isset($c['clave'])
        ));

        if ($columnas === []) {
            $hoja->setCellValueExplicit('A1', 'Esta tabla no tiene columnas configuradas.', DataType::TYPE_STRING);
            EstiloXlsx::nota($hoja, 'A1');

            return 0;
        }

        $excluidas = array_values(array_filter((array) ($contenido['filas_excluidas'] ?? []), 'is_array'));
        $conMotivo = $excluidas !== [];

        $cabeceras = array_map(fn ($c) => mb_strtoupper((string) ($c['titulo'] ?? $c['clave'])), $columnas);
        if ($conMotivo) {
            // El motivo de exclusión es columna propia, no un sufijo pegado al
            // texto de la primera celda: así se puede filtrar por motivo.
            $cabeceras[] = 'MOTIVO DE EXCLUSIÓN';
        }
        $nCols = count($cabeceras);
        $ultimaCol = $this->col($nCols);
        $colMotivo = $conMotivo ? $this->col($nCols) : null;

        $this->cabeceras($hoja, $cabeceras);

        $primera = 2;
        $fila = $primera;
        foreach ((array) ($contenido['filas'] ?? []) as $i => $f) {
            if (! is_array($f)) {
                continue;
            }
            $valores = is_array($f['valores'] ?? null) ? $f['valores'] : $f;
            foreach ($columnas as $c => $col) {
                $this->celda(
                    $hoja,
                    $this->col($c + 1).$fila,
                    $valores[$col['clave']] ?? null,
                    (string) ($col['tipo'] ?? 'texto'),
                    (string) $col['clave']
                );
            }
            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            }
            if (! empty($f['destacada'])) {
                EstiloXlsx::destacada($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            }
            $fila++;
        }
        $ultima = $fila - 1;
        $conteo = max(0, $ultima - $primera + 1);

        $primeraEx = $fila;
        foreach ($excluidas as $f) {
            $valores = is_array($f['valores'] ?? null) ? $f['valores'] : $f;
            foreach ($columnas as $c => $col) {
                $this->celda(
                    $hoja,
                    $this->col($c + 1).$fila,
                    $valores[$col['clave']] ?? null,
                    (string) ($col['tipo'] ?? 'texto'),
                    (string) $col['clave']
                );
            }
            if ($colMotivo !== null) {
                $hoja->setCellValueExplicit($colMotivo.$fila, (string) ($f['motivo'] ?? ''), DataType::TYPE_STRING);
            }
            EstiloXlsx::excluida($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            $fila++;
        }
        $ultimaEx = $fila - 1;

        $this->cerrarBloque($hoja, $ultimaCol, $fila - 1);

        // Pie de totales SÓLO si el Armador calculó alguno. Dibujar un pie
        // vacío era una divergencia con el PDF, que no lo pinta.
        $modos = $this->modosEfectivos($d);
        $totales = (array) ($d['totales'] ?? []);

        if ($totales !== [] && $conteo > 0) {
            $this->filaTotales(
                $hoja, $fila, $columnas, $modos, $totales,
                $primera, $ultima, $ultimaCol,
                'TOTAL COMPUTADO ('.$conteo.')', true
            );
            $fila++;

            $totalesEx = (array) ($d['totales_excluidas'] ?? []);
            if ($excluidas !== [] && $totalesEx !== []) {
                $this->filaTotales(
                    $hoja, $fila, $columnas, $modos, $totalesEx,
                    $primeraEx, $ultimaEx, $ultimaCol,
                    'TOTAL EXCLUIDO ('.count($excluidas).')', false
                );
                $fila++;
            }
        }

        // Alineación por columna sobre todo el bloque, cabecera incluida.
        foreach ($columnas as $c => $col) {
            $letra = $this->col($c + 1);
            $hoja->getStyle($letra.'1:'.$letra.max(2, $fila - 1))->getAlignment()->setHorizontal(
                isset($col['alineacion'])
                    ? EstiloXlsx::alineacion((string) $col['alineacion'])
                    : EstiloXlsx::alineacionDeTipo((string) ($col['tipo'] ?? 'texto'))
            );
        }

        $anchos = array_map(fn ($c) => EstiloXlsx::anchoDeTipo((string) ($c['tipo'] ?? 'texto')), $columnas);
        if ($conMotivo) {
            $anchos[] = 44;
        }
        $this->anchos($hoja, $anchos);

        if ($conMotivo && ($contenido['nota_excluidas'] ?? '') !== '') {
            $fila++;
            $hoja->setCellValueExplicit('A'.$fila, (string) $contenido['nota_excluidas'], DataType::TYPE_STRING);
            EstiloXlsx::nota($hoja, 'A'.$fila);
            $fila++;
        }

        $this->pieDeSeccion($hoja, $seccion, $fila, $ultimaCol);

        return $conteo + count($excluidas);
    }

    /**
     * Modo de total realmente aplicable a cada columna.
     *
     * Se lee `modos_efectivos`, que es lo que el Armador aplicó DESPUÉS de sus
     * fallbacks (por ejemplo `ctr` degradado a `promedio` cuando la tabla no
     * trae clics e impresiones, o cuando las impresiones suman cero). Escribir
     * la fórmula de ese modo, y no volver a decidir el fallback aquí, es lo
     * que hace imposible que el PDF y el Excel den cifras distintas.
     *
     * @param  array<string, mixed>  $derivados
     * @return array<string, string>
     */
    private function modosEfectivos(array $derivados): array
    {
        $modos = $derivados['modos_efectivos'] ?? $derivados['modos'] ?? [];
        $salida = [];

        foreach ((array) $modos as $clave => $modo) {
            if (is_string($clave) && is_string($modo) && $modo !== 'ninguno') {
                $salida[$clave] = $modo;
            }
        }

        return $salida;
    }

    /**
     * Escribe una fila de pie como fórmulas de Excel.
     *
     * Cada fórmula va envuelta en IFERROR con el valor del Armador —el mismo
     * que sale en el PDF— como respaldo. No es decoración:
     *
     *   · B8 · una columna en modo `promedio` con todas las celdas vacías hace
     *     que AVERAGE() opere sobre un rango sin números y devuelva #DIV/0!.
     *     El PDF muestra «n/d»; sin IFERROR el Excel entregaba un error en
     *     rojo al cliente.
     *   · B9 · SUMPRODUCT devuelve #VALUE! si CUALQUIER celda de sus rangos
     *     contiene texto, y este renderizador escribe «n/d» —texto— donde no
     *     hay dato, tal como manda el diseño. El Armador ignora esas filas y
     *     totaliza bien; IFERROR hace que la celda muestre exactamente eso.
     *
     * En el caso sano la fórmula calcula y coincide con el respaldo; en el
     * degenerado el respaldo es lo que se ve. En ninguno de los dos puede
     * aparecer un valor que el PDF no tenga.
     *
     * @param  array<int, array<string, mixed>>  $columnas
     * @param  array<string, string>  $modos
     * @param  array<string, float|null>  $valores
     */
    private function filaTotales(
        Worksheet $hoja,
        int $fila,
        array $columnas,
        array $modos,
        array $valores,
        int $primera,
        int $ultima,
        string $ultimaCol,
        string $etiqueta,
        bool $computado
    ): void {
        $letraDe = [];
        foreach ($columnas as $c => $col) {
            $letraDe[(string) $col['clave']] = $this->col($c + 1);
        }

        $rango = fn (string $letra) => $letra.$primera.':'.$letra.$ultima;
        $etiquetaPuesta = false;

        foreach ($columnas as $c => $col) {
            $clave = (string) $col['clave'];
            $letra = $this->col($c + 1);
            $tipo = (string) ($col['tipo'] ?? 'texto');
            $modo = $modos[$clave] ?? null;

            if ($modo === null) {
                if (! $etiquetaPuesta) {
                    $hoja->setCellValueExplicit($letra.$fila, $etiqueta, DataType::TYPE_STRING);
                    $etiquetaPuesta = true;
                }

                continue;
            }

            $respaldo = $this->respaldo($valores[$clave] ?? null, $tipo);

            $formula = match ($modo) {
                'suma' => '=IFERROR(SUM('.$rango($letra).'),'.$respaldo.')',
                'promedio' => '=IFERROR(AVERAGE('.$rango($letra).'),'.$respaldo.')',
                // CTR agregado = clics totales / impresiones totales de la
                // misma tabla, que es como lo agrega Search Console. Si el modo
                // efectivo sigue siendo `ctr`, el Armador ya comprobó que ambas
                // columnas existen y que las impresiones suman más que cero.
                'ctr' => isset($letraDe['clics'], $letraDe['impresiones'])
                    ? '=IFERROR(SUM('.$rango($letraDe['clics']).')/SUM('.$rango($letraDe['impresiones']).'),'.$respaldo.')'
                    : null,
                // Media ponderada por impresiones: la posición media de Search
                // Console lo es.
                // B9 · SUMPRODUCT devuelve #VALUE! en cuanto una celda de sus
                // rangos contiene texto, y aquí se escribe «n/d» —texto— donde
                // no hay dato. El guardarraíl es explícito, con COUNT frente a
                // ROWS, en vez de dejarlo al error: así el resultado es el
                // mismo en Excel y en cualquier otro motor, y es exactamente
                // el que el Armador calculó ignorando esas filas. Sin él, el
                // valor cacheado del libro podía salir de tratar el texto como
                // cero y no coincidir con el PDF.
                'ponderado' => isset($letraDe['impresiones'])
                    ? '=IFERROR(IF(AND(COUNT('.$rango($letra).')=ROWS('.$rango($letra).'),'
                        .'COUNT('.$rango($letraDe['impresiones']).')=ROWS('.$rango($letraDe['impresiones']).')),'
                        .'SUMPRODUCT('.$rango($letra).','.$rango($letraDe['impresiones']).')/SUM('.$rango($letraDe['impresiones']).'),'
                        .$respaldo.'),'.$respaldo.')'
                    : null,
                default => null,
            };

            if ($formula === null) {
                // El modo efectivo necesita una columna que esta vista no
                // muestra. Se escribe el valor del Armador tal cual antes que
                // inventar aquí un fallback distinto del suyo.
                if (($valores[$clave] ?? null) === null) {
                    $hoja->setCellValueExplicit($letra.$fila, EstiloXlsx::ND, DataType::TYPE_STRING);
                    EstiloXlsx::noDisponible($hoja, $letra.$fila);

                    continue;
                }
                $literal = $this->armador->numero($valores[$clave]);
                $literal = ($tipo === 'porcentaje' && $literal !== null)
                    ? $this->armador->aPorcentaje($literal) / 100
                    : $literal;
                $hoja->setCellValueExplicit($letra.$fila, (float) $literal, DataType::TYPE_NUMERIC);
            } else {
                $hoja->setCellValue($letra.$fila, $formula);
            }

            $hoja->getStyle($letra.$fila)->getNumberFormat()
                ->setFormatCode(EstiloXlsx::formatoNumerico($tipo, $clave));
        }

        if (! $etiquetaPuesta) {
            $hoja->setCellValueExplicit('A'.$fila, $etiqueta, DataType::TYPE_STRING);
        }

        if ($computado) {
            EstiloXlsx::totalComputado($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
        } else {
            EstiloXlsx::totalExcluido($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
        }
    }

    /**
     * Ficha de auditoría: primero el semáforo como tabla (una columna por
     * dimensión) y el detalle campo a campo debajo. En el PDF son bloques
     * porque no caben en carta; aquí es una tabla ancha, que es lo que una
     * hoja de cálculo sabe filtrar.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function hojaFicha(Worksheet $hoja, array $seccion): int
    {
        $contenido = $seccion['contenido'];
        $d = $this->derivados($seccion, 'ficha');
        $semaforo = is_array($d['semaforo'] ?? null) ? $d['semaforo'] : [];

        $dimensiones = array_values(array_filter(
            (array) ($semaforo['dimensiones'] ?? $contenido['dimensiones'] ?? []),
            fn ($x) => is_array($x) && isset($x['clave'])
        ));
        $registrosSem = array_values(array_filter(
            (array) ($semaforo['registros'] ?? $semaforo['filas'] ?? []),
            'is_array'
        ));

        $campos = array_values(array_filter(
            (array) ($contenido['campos'] ?? []),
            fn ($c) => is_array($c) && isset($c['clave'])
        ));
        $registros = array_values(array_filter((array) ($contenido['registros'] ?? []), 'is_array'));

        $cabeceras = ['REGISTRO'];
        foreach ($dimensiones as $dim) {
            $cabeceras[] = mb_strtoupper((string) ($dim['titulo'] ?? $dim['clave']));
        }
        $cabeceras[] = 'ESTADO';
        $cabeceras[] = 'VEREDICTO';
        $ultimaCol = $this->col(count($cabeceras));
        $this->cabeceras($hoja, $cabeceras);

        $fila = 2;
        foreach ($registrosSem as $i => $reg) {
            $hoja->setCellValueExplicit('A'.$fila, (string) ($reg['titulo'] ?? ''), DataType::TYPE_STRING);
            $estados = is_array($reg['estados'] ?? null) ? $reg['estados'] : (is_array($reg['valores'] ?? null) ? $reg['valores'] : []);

            foreach ($dimensiones as $c => $dim) {
                $bruto = $estados[$dim['clave']] ?? null;
                $estado = is_array($bruto) ? ($bruto['estado'] ?? null) : $bruto;
                $celda = $this->col($c + 2).$fila;
                [$texto, $fondo, $etiqueta] = EstiloXlsx::semaforo(is_scalar($estado) ? (string) $estado : null);
                $hoja->setCellValueExplicit($celda, $etiqueta, DataType::TYPE_STRING);
                EstiloXlsx::badge($hoja, $celda, $texto, $fondo);
            }

            $colEstado = $this->col(count($dimensiones) + 2);
            [$texto, $fondo, $etiqueta] = EstiloXlsx::semaforo((string) ($reg['estado'] ?? ''));
            $hoja->setCellValueExplicit($colEstado.$fila, $etiqueta, DataType::TYPE_STRING);
            EstiloXlsx::badge($hoja, $colEstado.$fila, $texto, $fondo);

            $hoja->setCellValueExplicit($ultimaCol.$fila, (string) ($reg['veredicto'] ?? ''), DataType::TYPE_STRING);

            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':A'.$fila);
            }
            $fila++;
        }

        $this->cerrarBloque($hoja, $ultimaCol, $fila - 1);
        $this->anchos($hoja, array_merge([46], array_fill(0, count($dimensiones) + 1, 10), [40]));

        // Detalle campo a campo, debajo del semáforo.
        if ($registros !== [] && $campos !== []) {
            $fila++;
            $hoja->setCellValueExplicit('A'.$fila, 'DETALLE POR CAMPO', DataType::TYPE_STRING);
            EstiloXlsx::rotulo($hoja, 'A'.$fila);
            $fila++;

            $hoja->setCellValueExplicit('A'.$fila, 'REGISTRO', DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('B'.$fila, 'CAMPO', DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('C'.$fila, 'VALOR', DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('D'.$fila, 'ESTADO', DataType::TYPE_STRING);
            EstiloXlsx::subEncabezado($hoja, 'A'.$fila.':D'.$fila);
            $fila++;

            $n = 0;
            foreach ($registros as $registro) {
                $valores = is_array($registro['valores'] ?? null) ? $registro['valores'] : [];
                foreach ($campos as $campo) {
                    $bruto = $valores[$campo['clave']] ?? null;
                    $valor = is_array($bruto) ? ($bruto['valor'] ?? null) : $bruto;
                    $estado = is_array($bruto) ? ($bruto['estado'] ?? null) : null;

                    $hoja->setCellValueExplicit('A'.$fila, (string) ($registro['titulo'] ?? ''), DataType::TYPE_STRING);
                    $hoja->setCellValueExplicit('B'.$fila, (string) ($campo['titulo'] ?? $campo['clave']), DataType::TYPE_STRING);
                    $this->celda($hoja, 'C'.$fila, $valor, 'texto', (string) $campo['clave']);

                    [$texto, $fondo, $etiqueta] = EstiloXlsx::semaforo(is_scalar($estado) ? (string) $estado : null);
                    $hoja->setCellValueExplicit('D'.$fila, $etiqueta, DataType::TYPE_STRING);
                    EstiloXlsx::badge($hoja, 'D'.$fila, $texto, $fondo);

                    if ($n % 2 === 1) {
                        EstiloXlsx::zebra($hoja, 'A'.$fila.':C'.$fila);
                    }
                    $n++;
                    $fila++;
                }
            }
        }

        $this->pieDeSeccion($hoja, $seccion, $fila + 1, $ultimaCol);

        return count($registrosSem) ?: count($registros);
    }

    /** @param array<string, mixed> $seccion */
    private function hojaPlan(Worksheet $hoja, array $seccion): int
    {
        $acciones = (array) ($this->derivados($seccion, 'plan')['acciones'] ?? []);

        $this->cabeceras($hoja, ['PRIORIDAD', 'ACCIÓN', 'EVIDENCIA', 'ÁREA', 'IMPACTO', 'ESFUERZO', 'SCORE', 'KPI']);

        $fila = 2;
        foreach ($acciones as $accion) {
            if (! is_array($accion)) {
                continue;
            }
            $hoja->setCellValueExplicit('A'.$fila, (string) ($accion['prioridad_label'] ?? '—'), DataType::TYPE_STRING);
            EstiloXlsx::chip($hoja, 'A'.$fila, EstiloXlsx::colorPrioridad((string) ($accion['prioridad'] ?? '')));
            $hoja->setCellValueExplicit('B'.$fila, (string) ($accion['accion'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('C'.$fila, (string) ($accion['evidencia'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('D'.$fila, (string) ($accion['area'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('E'.$fila, (int) ($accion['impacto'] ?? 0), DataType::TYPE_NUMERIC);
            $hoja->setCellValueExplicit('F'.$fila, (int) ($accion['esfuerzo'] ?? 0), DataType::TYPE_NUMERIC);

            // Score vivo: si el equipo reajusta impacto o esfuerzo en la hoja,
            // el score se recalcula. IFERROR cubre el esfuerzo en cero, que da
            // #DIV/0!; el respaldo es el score del Armador, el del PDF.
            $hoja->setCellValue(
                'G'.$fila,
                '=IFERROR(E'.$fila.'/F'.$fila.','.$this->respaldo($accion['score'] ?? null, 'decimal').')'
            );
            $hoja->getStyle('G'.$fila)->getNumberFormat()->setFormatCode('0.00');

            $hoja->setCellValueExplicit('H'.$fila, (string) ($accion['kpi'] ?? ''), DataType::TYPE_STRING);
            $fila++;
        }

        $this->cerrarBloque($hoja, 'H', $fila - 1);
        EstiloXlsx::ajustar($hoja, 'B2:C'.max(2, $fila - 1));
        $hoja->getStyle('E2:G'.max(2, $fila - 1))->getAlignment()
            ->setHorizontal(EstiloXlsx::alineacion('derecha'));
        $this->anchos($hoja, [11, 58, 50, 18, 10, 10, 9, 34]);

        $leyendas = array_filter([
            (string) ($seccion['contenido']['leyenda_prioridades'] ?? ''),
            (string) ($seccion['contenido']['leyenda'] ?? ''),
        ], fn ($t) => trim($t) !== '');

        $fila++;
        foreach ($leyendas as $leyenda) {
            $hoja->setCellValueExplicit('A'.$fila, $leyenda, DataType::TYPE_STRING);
            EstiloXlsx::nota($hoja, 'A'.$fila);
            $fila++;
        }

        $this->pieDeSeccion($hoja, $seccion, $fila, 'H');

        return count($acciones);
    }

    /** @param array<string, mixed> $seccion */
    private function hojaTexto(Worksheet $hoja, array $seccion): int
    {
        $bloques = array_values(array_filter((array) ($seccion['contenido']['bloques'] ?? []), 'is_array'));

        $this->cabeceras($hoja, ['APARTADO', 'CONTENIDO']);

        $fila = 2;
        foreach ($bloques as $i => $bloque) {
            $hoja->setCellValueExplicit('A'.$fila, (string) ($bloque['titulo'] ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('B'.$fila, (string) ($bloque['cuerpo'] ?? ''), DataType::TYPE_STRING);
            if ($i % 2 === 1) {
                EstiloXlsx::zebra($hoja, 'A'.$fila.':B'.$fila);
            }
            $fila++;
        }

        $this->cerrarBloque($hoja, 'B', $fila - 1);
        EstiloXlsx::ajustar($hoja, 'A2:B'.max(2, $fila - 1));
        $this->anchos($hoja, [30, 110]);
        $this->pieDeSeccion($hoja, $seccion, $fila, 'B');

        return count($bloques);
    }

    // ---------------------------------------------------------------------
    // Utilidades de escritura
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $seccion
     * @return array<string, mixed>
     */
    private function derivados(array $seccion, string $tipo): array
    {
        $d = $seccion['derivados'] ?? [];

        if (! is_array($d)) {
            return [];
        }

        // El Armador puede entregar los derivados anidados bajo el tipo de
        // sección o directamente en la raíz; se aceptan las dos formas.
        if (isset($d[$tipo]) && is_array($d[$tipo])) {
            return $d[$tipo];
        }

        return $d;
    }

    /**
     * Fila 1: encabezado oscuro, congelada y con autofiltro. Es lo primero que
     * se escribe en cada hoja de datos.
     *
     * @param  array<int, string>  $cabeceras
     */
    private function cabeceras(Worksheet $hoja, array $cabeceras): void
    {
        if ($cabeceras === []) {
            return;
        }

        foreach ($cabeceras as $i => $cabecera) {
            $hoja->setCellValueExplicit($this->col($i + 1).'1', mb_strtoupper($cabecera), DataType::TYPE_STRING);
        }

        EstiloXlsx::encabezado($hoja, 'A1:'.$this->col(count($cabeceras)).'1');
        $hoja->getRowDimension(1)->setRowHeight(26);
        $hoja->freezePane('A2');
    }

    /**
     * Cierra el bloque de datos: autofiltro sobre cabecera + filas (incluidas
     * las excluidas, que nunca se ocultan) y rejilla interna. El pie de
     * totales queda deliberadamente FUERA del rango del autofiltro: si
     * entrara, Excel lo trataría como un dato más al ordenar.
     */
    private function cerrarBloque(Worksheet $hoja, string $ultimaCol, int $ultimaFila): void
    {
        $hoja->setAutoFilter('A1:'.$ultimaCol.max(1, $ultimaFila));

        if ($ultimaFila >= 2) {
            EstiloXlsx::rejilla($hoja, 'A2:'.$ultimaCol.$ultimaFila);
        }
    }

    /**
     * Rótulo, aviso y nota de la sección, al pie de la hoja: por encima de la
     * fila 1 no puede ir nada sin romper el encabezado congelado y el
     * autofiltro.
     *
     * @param  array<string, mixed>  $seccion
     */
    private function pieDeSeccion(Worksheet $hoja, array $seccion, int $fila, string $ultimaCol): void
    {
        $fila++;

        $rotulo = trim((string) ($seccion['rotulo'] ?? ''));
        $titulo = trim((string) ($seccion['titulo'] ?? ''));
        if ($rotulo !== '' || $titulo !== '') {
            $hoja->setCellValueExplicit(
                'A'.$fila,
                trim($rotulo !== '' ? $rotulo.' · '.$titulo : $titulo),
                DataType::TYPE_STRING
            );
            EstiloXlsx::rotulo($hoja, 'A'.$fila);
            $fila++;
        }

        $aviso = $seccion['aviso'] ?? null;
        if (is_array($aviso) && trim((string) ($aviso['texto'] ?? '')) !== '') {
            $fecha = trim((string) ($aviso['fecha'] ?? ''));
            $hoja->setCellValueExplicit(
                'A'.$fila,
                'DATO DESACTUALIZADO'.($fecha !== '' ? ' · '.$fecha : '').' — '.trim((string) $aviso['texto']),
                DataType::TYPE_STRING
            );
            EstiloXlsx::aviso($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
            $fila++;
        }

        $nota = trim((string) ($seccion['contenido']['nota'] ?? ''));
        if ($nota === '') {
            // La sección `serie` llama `lectura` a su nota de pie.
            $nota = trim((string) ($seccion['contenido']['lectura'] ?? ''));
        }
        if ($nota !== '') {
            $hoja->setCellValueExplicit('A'.$fila, $nota, DataType::TYPE_STRING);
            EstiloXlsx::nota($hoja, 'A'.$fila.':'.$ultimaCol.$fila);
        }
    }

    /**
     * Escribe una celda de dato.
     *
     * Un número siempre entra como número, con su formato en el estilo; el
     * texto, como texto. Donde no hay dato se escribe «n/d» en gris —nunca la
     * celda vacía ni un 0, que mentiría—, aun sabiendo que ese texto es lo que
     * obliga a envolver SUMPRODUCT en IFERROR más abajo: es la decisión del
     * diseño y el coste está contenido.
     */
    private function celda(Worksheet $hoja, string $celda, mixed $valor, string $formato, ?string $clave = null): void
    {
        $esTexto = in_array($formato, ['texto', 'nivel'], true);

        if ($valor === null || $valor === '' || is_array($valor)) {
            if ($esTexto) {
                return;
            }
            $hoja->setCellValueExplicit($celda, EstiloXlsx::ND, DataType::TYPE_STRING);
            EstiloXlsx::noDisponible($hoja, $celda);

            return;
        }

        if ($formato === 'nivel') {
            $texto = mb_strtoupper(trim((string) $valor));
            $hoja->setCellValueExplicit($celda, $texto, DataType::TYPE_STRING);
            EstiloXlsx::nivel($hoja, $celda, $texto);

            return;
        }

        if ($formato === 'texto') {
            $hoja->setCellValueExplicit($celda, is_scalar($valor) ? (string) $valor : '', DataType::TYPE_STRING);

            return;
        }

        if ($formato === 'fecha') {
            $serie = $this->fechaSerial($valor);
            if ($serie === null) {
                // Etiqueta no reconocible como fecha («Semana 32»): texto.
                $hoja->setCellValueExplicit($celda, is_scalar($valor) ? (string) $valor : '', DataType::TYPE_STRING);

                return;
            }
            $hoja->setCellValueExplicit($celda, $serie, DataType::TYPE_NUMERIC);
            $hoja->getStyle($celda)->getNumberFormat()->setFormatCode(EstiloXlsx::formatoNumerico('fecha'));

            return;
        }

        $numero = $this->armador->numero($valor);

        if ($numero === null) {
            $hoja->setCellValueExplicit($celda, is_scalar($valor) ? (string) $valor : EstiloXlsx::ND, DataType::TYPE_STRING);
            EstiloXlsx::noDisponible($hoja, $celda);

            return;
        }

        if ($formato === 'porcentaje') {
            // El formato 0.00% de Excel multiplica por 100 al mostrar, así que
            // la celda debe guardar la fracción (0,0432), no los puntos (4,32).
            $numero = $this->armador->aPorcentaje($numero) / 100;
        }

        $hoja->setCellValueExplicit($celda, $numero, DataType::TYPE_NUMERIC);
        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode(EstiloXlsx::formatoNumerico($formato, $clave));
    }

    /**
     * Valor del Armador listo para usarse de respaldo. Se le aplica la MISMA
     * transformación que a las celdas de datos (los porcentajes viajan como
     * fracción) para que respaldo y fórmula sean comparables celda a celda.
     *
     * Sin dato → la cadena «n/d», que es lo que muestra el PDF.
     */
    private function respaldoValor(mixed $valor, string $formato): float|string
    {
        $numero = is_scalar($valor) ? $this->armador->numero($valor) : null;

        if ($numero === null) {
            return EstiloXlsx::ND;
        }

        return $formato === 'porcentaje'
            ? $this->armador->aPorcentaje($numero) / 100
            : $numero;
    }

    /** El mismo respaldo, escrito como literal dentro de una fórmula. */
    private function respaldo(mixed $valor, string $formato): string
    {
        $v = $this->respaldoValor($valor, $formato);

        // Punto decimal y sin separador de millares: la sintaxis de fórmula de
        // un .xlsx es siempre en-US, la traduce Excel al abrirla.
        return is_string($v)
            ? '"'.$v.'"'
            : (rtrim(rtrim(number_format($v, 10, '.', ''), '0'), '.') ?: '0');
    }

    /**
     * Convierte una fecha a serial de Excel. Sólo acepta formas inequívocas
     * (ISO y dd/mm/aaaa): cualquier otra etiqueta del eje X se deja como texto
     * antes que arriesgar una fecha inventada.
     */
    private function fechaSerial(mixed $valor): ?float
    {
        if ($valor instanceof \DateTimeInterface) {
            return (float) FechaExcel::PHPToExcel($valor);
        }

        if (! is_string($valor)) {
            return null;
        }

        $texto = trim($valor);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m) === 1) {
            [$y, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m) === 1) {
            [$y, $mes, $dia] = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } else {
            return null;
        }

        if (! checkdate($mes, $dia, $y)) {
            return null;
        }

        return (float) FechaExcel::formattedPHPToExcel($y, $mes, $dia);
    }

    /** @param array<int, int> $anchos */
    private function anchos(Worksheet $hoja, array $anchos): void
    {
        foreach ($anchos as $i => $ancho) {
            $hoja->getColumnDimension($this->col($i + 1))->setWidth($ancho);
        }
    }

    private function col(int $indice): string
    {
        return Coordinate::stringFromColumnIndex(max(1, $indice));
    }
}
