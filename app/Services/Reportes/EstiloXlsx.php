<?php

namespace App\Services\Reportes;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Traducción de la hoja de estilo del sistema de reportes (artboard 09) a
 * estilos de PhpSpreadsheet.
 *
 * El Excel no imita la maqueta del PDF: hereda su semántica. Los tres
 * elementos que viajan intactos son el encabezado oscuro, la jerarquía de
 * totales (computado en negro y grueso, excluido en gris y fino) y el
 * tratamiento de las filas excluidas. Todo eso vive aquí.
 *
 * REGLA: el renderizador no lleva ni un hex ni un código de formato numérico.
 * Si cambia la marca, se cambia este archivo y ningún otro.
 */
class EstiloXlsx
{
    // -----------------------------------------------------------------
    // Paleta (artboard 09 · «PALETA Y USOS»)
    // -----------------------------------------------------------------

    /** Marca. Rótulos, banda de portada, oportunidad ALTA. */
    public const MARCA = '0F9D6E';

    /** Texto principal Y encabezado de tabla. El negro de tabla es este, no #000. */
    public const TEXTO = '1A2332';

    /** Texto secundario, etiquetas, notas. */
    public const SECUNDARIO = '64748B';

    /** Sólo para dato ausente («n/d») o fila excluida. */
    public const TENUE = '94A3B8';

    /** Superficie de bloque (pie de totales, sub-encabezados). */
    public const SUPERFICIE = 'F5F7FA';

    /** Banda zebra. */
    public const ZEBRA = 'F9FAFC';

    public const BLANCO = 'FFFFFF';

    public const BORDE = 'E5E7EB';

    /** Rejilla interna de tabla densa. */
    public const REJILLA = 'EEF1F5';

    // Estados. En texto pequeño la alerta y el info se oscurecen para que
    // contrasten impresos: por eso ALERTA_TEXTO ≠ ALERTA.
    public const POSITIVO = '34D399';

    public const ALERTA = 'F59E0B';

    public const NEGATIVO = 'EF4444';

    public const INFO = '3B82F6';

    public const ALERTA_TEXTO = 'B45309';

    public const INFO_TEXTO = '1D4ED8';

    /** Fondos tenues de los badges de severidad y del semáforo. */
    public const FONDO_OK = 'ECFBF4';

    public const FONDO_ALERTA = 'FFFBEB';

    public const FONDO_ERROR = 'FEF2F2';

    /** Texto que sustituye a un dato ausente. Nunca celda vacía, nunca 0. */
    public const ND = 'n/d';

    // -----------------------------------------------------------------
    // Formatos numéricos (artboard 10 · «FORMATOS NUMÉRICOS»)
    // -----------------------------------------------------------------

    /**
     * Los códigos de formato de Excel se escriben siempre en la forma canónica
     * en-US (punto decimal, `yyyy`); Excel los muestra con el separador y los
     * nombres del locale del cliente. Es decir: `0.00%` se ve como `0,00%` y
     * `dd/mm/yyyy` como `dd/mm/aaaa`, que es justo lo que pide el diseño.
     */
    public const FORMATOS = [
        'texto' => '@',
        'numero' => '#,##0',
        'decimal' => '0.0',
        'porcentaje' => '0.00%',
        'moneda' => '"$"#,##0.00',
        'fecha' => 'dd/mm/yyyy',
        // Escala cualitativa ALTA/MEDIA/BAJA: es texto, el color hace el trabajo.
        'nivel' => '@',
    ];

    /** TTFB lleva su unidad dentro del formato, no dentro del valor. */
    public const FORMATO_TTFB = '#,##0 "ms"';

    /**
     * Formato de Excel de una columna. La unidad «ms» del TTFB no es un tipo
     * del esquema —sería un tipo por unidad—, así que se reconoce por la clave
     * de la columna: el valor sigue siendo un número puro y la unidad vive en
     * el estilo, como manda el diseño.
     */
    public static function formatoNumerico(?string $tipo, ?string $clave = null): string
    {
        $tipo ??= 'texto';

        if ($clave !== null && in_array($tipo, ['numero', 'decimal'], true)
            && str_contains(mb_strtolower($clave), 'ttfb')) {
            return self::FORMATO_TTFB;
        }

        return self::FORMATOS[$tipo] ?? '@';
    }

    // -----------------------------------------------------------------
    // Colores por dominio
    // -----------------------------------------------------------------

    /** Oportunidad / nivel: sólo peso y color, nunca cápsula. */
    public static function colorNivel(?string $nivel): string
    {
        return match (mb_strtoupper(trim((string) $nivel))) {
            'ALTA', 'ALTO' => self::MARCA,
            'MEDIA', 'MEDIO' => self::ALERTA_TEXTO,
            'BAJA', 'BAJO' => self::TENUE,
            default => self::SECUNDARIO,
        };
    }

    /**
     * Semáforo de la auditoría: OK cumple · REV revisar · ERR incumple ·
     * n/d sin dato.
     *
     * @return array{0: string, 1: string, 2: string} [texto, fondo, etiqueta]
     */
    public static function semaforo(?string $estado): array
    {
        return match (mb_strtolower(trim((string) $estado))) {
            'ok' => [self::MARCA, self::FONDO_OK, 'OK'],
            'revisar' => [self::ALERTA_TEXTO, self::FONDO_ALERTA, 'REV'],
            'critico' => [self::NEGATIVO, self::FONDO_ERROR, 'ERR'],
            default => [self::TENUE, self::SUPERFICIE, self::ND],
        };
    }

    /**
     * Severidad de hallazgo: contorno + fondo tenue.
     *
     * @return array{0: string, 1: string} [texto, fondo]
     */
    public static function colorSeveridad(?string $severidad): array
    {
        return match ((string) $severidad) {
            'critico' => [self::NEGATIVO, self::FONDO_ERROR],
            'alto' => [self::ALERTA_TEXTO, self::FONDO_ALERTA],
            'medio' => [self::INFO_TEXTO, 'EFF6FF'],
            'informativo' => [self::SECUNDARIO, self::SUPERFICIE],
            default => [self::SECUNDARIO, self::SUPERFICIE],
        };
    }

    /** Prioridad del plan: fondo sólido, el único caso de la hoja de estilo. */
    public static function colorPrioridad(?string $prioridad): string
    {
        return match ((string) $prioridad) {
            'p0' => self::NEGATIVO,
            'p1' => self::ALERTA,
            'p2' => self::INFO,
            'p3' => self::SECUNDARIO,
            default => self::SECUNDARIO,
        };
    }

    /** Estado de una hoja en el índice: replica el semáforo del PDF. */
    public static function colorEstadoHoja(string $estado): string
    {
        return match ($estado) {
            'completa' => self::MARCA,
            'aviso' => self::ALERTA_TEXTO,
            'vacia' => self::TENUE,
            default => self::SECUNDARIO,
        };
    }

    // -----------------------------------------------------------------
    // Bloques de estilo
    // -----------------------------------------------------------------

    /** Banda verde combinada de la fila 1 del índice: la portada del libro. */
    public static function bandaPortada(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::BLANCO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::MARCA]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ]);
    }

    /**
     * Fila 1 de cada hoja de datos: relleno #1A2332, texto blanco en negrita.
     * Es uno de los tres elementos que viajan intactos desde el PDF.
     */
    public static function encabezado(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::BLANCO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TEXTO]],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
    }

    /** Encabezado de un bloque secundario (tabla agregada, detalle de ficha). */
    public static function subEncabezado(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::TEXTO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SUPERFICIE]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDE]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    /**
     * Banda zebra.
     *
     * Se aplica como ESTILO DE CELDA y no como formato condicional a propósito:
     * un formato condicional del tipo `=RESIDUO(FILA();2)=0` se recalcula al
     * ordenar o al filtrar, y la banda salta de fila; el cliente ve un rayado
     * distinto cada vez que toca el autofiltro. Como estilo de celda la banda
     * viaja con la fila y sobrevive a ordenar y filtrar.
     */
    public static function zebra(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::ZEBRA]],
        ]);
    }

    /** Fila destacada: mismo fondo, sólo peso. */
    public static function destacada(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB(self::TEXTO);
    }

    /**
     * Fila excluida del cómputo: texto #94A3B8 y tachado. Nunca se oculta —el
     * cliente debe poder verla— y nunca entra en el rango de las fórmulas de
     * total; de eso se encarga el orden en que las escribe el renderizador.
     */
    public static function excluida(Worksheet $hoja, string $rango): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setStrikethrough(true)->setBold(false)->getColor()->setRGB(self::TENUE);
        $estilo->getFill()->setFillType(Fill::FILL_NONE);
    }

    /** Pie de TOTAL COMPUTADO: negro, grueso, borde superior grueso y relleno. */
    public static function totalComputado(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::TEXTO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::SUPERFICIE]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => self::TEXTO]]],
        ]);
    }

    /**
     * Pie de TOTAL EXCLUIDO: sin relleno y en gris, por debajo jerárquicamente
     * del computado. Es la misma jerarquía que el PDF.
     */
    public static function totalExcluido(Worksheet $hoja, string $rango): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->applyFromArray([
            'font' => ['bold' => false, 'color' => ['rgb' => self::TENUE]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDE]]],
        ]);
        $estilo->getFill()->setFillType(Fill::FILL_NONE);
    }

    /** Dato ausente: el texto «n/d» en gris, alineado como la cifra que sustituye. */
    public static function noDisponible(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getFont()->getColor()->setRGB(self::TENUE);
    }

    /** Celda de nivel/oportunidad: sólo peso y color. */
    public static function nivel(Worksheet $hoja, string $rango, ?string $valor): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::colorNivel($valor)]],
        ]);
    }

    /** Celda de semáforo o de badge: texto de color sobre fondo tenue. */
    public static function badge(Worksheet $hoja, string $rango, string $texto, string $fondo): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $texto]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fondo]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    /** Chip de prioridad: fondo sólido y texto blanco. */
    public static function chip(Worksheet $hoja, string $rango, string $rgb): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::BLANCO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    /** Rótulo de bloque: mayúsculas pequeñas en verde de marca. */
    public static function rotulo(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::MARCA]],
        ]);
    }

    /** Etiqueta de un par etiqueta/valor. */
    public static function etiqueta(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::SECUNDARIO]],
        ]);
    }

    /** Valor de un par etiqueta/valor. */
    public static function valor(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => self::TEXTO]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
    }

    /** Nota al pie de hoja. */
    public static function nota(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => self::SECUNDARIO]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        ]);
    }

    /** Aviso de dato desactualizado: la cifra queda en negro, la fecha avisa. */
    public static function aviso(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => self::ALERTA_TEXTO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::FONDO_ALERTA]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        ]);
    }

    /** Rejilla interna suave sobre el bloque de datos. */
    public static function rejilla(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => self::REJILLA]]],
        ]);
    }

    public static function ajustar(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    /** Traduce la alineación del esquema a la de PhpSpreadsheet. */
    public static function alineacion(?string $alineacion): string
    {
        return match ($alineacion) {
            'derecha' => Alignment::HORIZONTAL_RIGHT,
            'centro' => Alignment::HORIZONTAL_CENTER,
            default => Alignment::HORIZONTAL_LEFT,
        };
    }

    /**
     * Alineación por defecto de un tipo de columna: las cifras a la derecha,
     * para que se lean con el mismo número de decimales unas debajo de otras.
     */
    public static function alineacionDeTipo(?string $tipo): string
    {
        return match ($tipo) {
            'numero', 'decimal', 'porcentaje', 'moneda' => Alignment::HORIZONTAL_RIGHT,
            'nivel', 'fecha' => Alignment::HORIZONTAL_CENTER,
            default => Alignment::HORIZONTAL_LEFT,
        };
    }

    /** Ancho de columna razonable según el tipo, para no dejar ##### ni huecos. */
    public static function anchoDeTipo(?string $tipo): int
    {
        return match ($tipo) {
            'numero', 'decimal', 'porcentaje' => 12,
            'moneda' => 15,
            'fecha' => 14,
            'nivel' => 10,
            default => 38,
        };
    }
}
