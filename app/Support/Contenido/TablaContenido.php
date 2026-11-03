<?php

namespace App\Support\Contenido;

use InvalidArgumentException;

/**
 * Formato, validación y CSV de las tablas editables del módulo «Contenido».
 *
 * La tabla se guarda COMPLETA como JSON en cm_items.contenido:
 *
 *   {
 *     "columnas": [{"id": "c1", "nombre": "Título", "tipo": "texto|numero|fecha|seleccion", "opciones": ["A", "B"]}],
 *     "filas":    [{"id": "f1", "celdas": {"c1": "valor"}}],
 *     "vista":    "tabla|kanban|calendario|galeria",
 *     "columna_kanban": "c3" | null,
 *     "columna_fecha": "c2" | null,       (columna de tipo fecha que ubica cada fila en el calendario)
 *     "columna_titulo": "c1" | null,      (texto que encabeza cada tarjeta/evento)
 *     "columnas_visibles": ["c1", "c2"],  (columnas que muestra la galería; vacío = todas)
 *     "origen": {"clave": "ads_campanas", "fecha": "2026-11-01 14:30"} | null   (de qué «foto» de datos reales se llenó)
 *   }
 *
 * Todo valor llega del navegador, así que `normalizar()` es la única puerta de
 * entrada: topes de tamaño, ids con forma fija, celdas que solo pueden apuntar a
 * columnas existentes y valores coherentes con el tipo. Lo que sale de ahí es lo
 * único que se persiste.
 */
class TablaContenido
{
    public const TIPOS = ['texto', 'numero', 'fecha', 'seleccion'];
    public const VISTAS = ['tabla', 'kanban', 'calendario', 'galeria'];

    public const MAX_COLUMNAS = 30;
    public const MAX_FILAS = 2000;
    public const MAX_OPCIONES = 40;
    public const MAX_TEXTO = 2000;
    public const MAX_NOMBRE = 80;
    /** Tope del JSON ya normalizado (bytes): protege la fila de la BD y la memoria del navegador. */
    public const MAX_BYTES = 1500000;

    /** Caracteres que un programa de hojas de cálculo interpreta como inicio de fórmula. */
    private const INICIOS_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    public static function vacia(): array
    {
        return [
            'columnas' => [], 'filas' => [], 'vista' => 'tabla', 'columna_kanban' => null,
            'columna_fecha' => null, 'columna_titulo' => null, 'columnas_visibles' => [], 'origen' => null,
        ];
    }

    /**
     * Calendario de contenido (etapa 4): tabla preconfigurada, sin filas. Las
     * opciones de Red y Estado son un punto de partida editable, no datos.
     */
    public static function plantillaCalendario(): array
    {
        return [
            'columnas' => [
                ['id' => 'c1', 'nombre' => 'Título', 'tipo' => 'texto', 'opciones' => []],
                ['id' => 'c2', 'nombre' => 'Red', 'tipo' => 'seleccion', 'opciones' => ['Instagram', 'Facebook', 'TikTok', 'LinkedIn', 'YouTube', 'Blog']],
                ['id' => 'c3', 'nombre' => 'Fecha', 'tipo' => 'fecha', 'opciones' => []],
                ['id' => 'c4', 'nombre' => 'Estado', 'tipo' => 'seleccion', 'opciones' => ['Idea', 'En producción', 'En revisión', 'Programado', 'Publicado']],
                ['id' => 'c5', 'nombre' => 'Responsable', 'tipo' => 'texto', 'opciones' => []],
            ],
            'filas' => [],
            'vista' => 'tabla',
            'columna_kanban' => 'c4',
            'columna_fecha' => 'c3',
            'columna_titulo' => 'c1',
            'columnas_visibles' => [],
            'origen' => null,
        ];
    }

    /** Decodifica lo guardado; una fila vacía o dañada se trata como tabla vacía. */
    public static function decodificar(?string $json): array
    {
        if ($json === null || $json === '') {
            return self::vacia();
        }

        $datos = json_decode($json, true);

        if (! is_array($datos)) {
            return self::vacia();
        }

        try {
            return self::normalizar($datos);
        } catch (InvalidArgumentException) {
            return self::vacia();
        }
    }

    /**
     * Valida y limpia la estructura recibida.
     *
     * @throws InvalidArgumentException con un mensaje apto para mostrar al usuario
     */
    public static function normalizar(mixed $entrada): array
    {
        if (! is_array($entrada)) {
            throw new InvalidArgumentException('La tabla no tiene un formato válido.');
        }

        $columnasEntrada = $entrada['columnas'] ?? [];
        $filasEntrada = $entrada['filas'] ?? [];

        if (! is_array($columnasEntrada) || ! is_array($filasEntrada)) {
            throw new InvalidArgumentException('La tabla no tiene un formato válido.');
        }
        if (count($columnasEntrada) > self::MAX_COLUMNAS) {
            throw new InvalidArgumentException('Una tabla admite como máximo '.self::MAX_COLUMNAS.' columnas.');
        }
        if (count($filasEntrada) > self::MAX_FILAS) {
            throw new InvalidArgumentException('Una tabla admite como máximo '.self::MAX_FILAS.' filas.');
        }

        $columnas = [];
        $porId = [];

        foreach (array_values($columnasEntrada) as $col) {
            if (! is_array($col)) {
                throw new InvalidArgumentException('Hay una columna con formato inválido.');
            }

            $id = self::id($col['id'] ?? null, 'columna');
            if (isset($porId[$id])) {
                throw new InvalidArgumentException('Hay columnas con el mismo identificador.');
            }

            $nombre = self::texto($col['nombre'] ?? '', self::MAX_NOMBRE);
            if ($nombre === '') {
                throw new InvalidArgumentException('Todas las columnas necesitan un nombre.');
            }

            $tipo = $col['tipo'] ?? null;
            if (! in_array($tipo, self::TIPOS, true)) {
                throw new InvalidArgumentException("El tipo de la columna «{$nombre}» no es válido.");
            }

            $opciones = [];
            if ($tipo === 'seleccion') {
                $opcionesEntrada = $col['opciones'] ?? [];
                if (! is_array($opcionesEntrada) || count($opcionesEntrada) > self::MAX_OPCIONES) {
                    throw new InvalidArgumentException("La columna «{$nombre}» admite como máximo ".self::MAX_OPCIONES.' opciones.');
                }
                foreach ($opcionesEntrada as $op) {
                    if (! is_string($op)) {
                        throw new InvalidArgumentException("Las opciones de «{$nombre}» deben ser texto.");
                    }
                    $op = self::texto($op, self::MAX_NOMBRE);
                    if ($op !== '' && ! in_array($op, $opciones, true)) {
                        $opciones[] = $op;
                    }
                }
            }

            $porId[$id] = ['tipo' => $tipo, 'opciones' => $opciones];
            $columnas[] = ['id' => $id, 'nombre' => $nombre, 'tipo' => $tipo, 'opciones' => $opciones];
        }

        $filas = [];
        $idsFila = [];

        foreach (array_values($filasEntrada) as $fila) {
            if (! is_array($fila)) {
                throw new InvalidArgumentException('Hay una fila con formato inválido.');
            }

            $id = self::id($fila['id'] ?? null, 'fila');
            if (isset($idsFila[$id])) {
                throw new InvalidArgumentException('Hay filas con el mismo identificador.');
            }
            $idsFila[$id] = true;

            $celdasEntrada = $fila['celdas'] ?? [];
            if (! is_array($celdasEntrada)) {
                throw new InvalidArgumentException('Hay una fila con celdas inválidas.');
            }

            $celdas = [];
            foreach ($celdasEntrada as $colId => $valor) {
                $colId = (string) $colId;
                if (! isset($porId[$colId])) {
                    throw new InvalidArgumentException('Una fila tiene datos de una columna que no existe.');
                }
                $celdas[$colId] = self::celda($valor, $porId[$colId]['tipo'], $porId[$colId]['opciones']);
            }

            $filas[] = ['id' => $id, 'celdas' => $celdas];
        }

        $vista = $entrada['vista'] ?? 'tabla';
        if (! in_array($vista, self::VISTAS, true)) {
            throw new InvalidArgumentException('La vista elegida no es válida.');
        }

        $kanban = $entrada['columna_kanban'] ?? null;
        if ($kanban !== null && (! is_string($kanban) || ($porId[$kanban]['tipo'] ?? null) !== 'seleccion')) {
            // La columna agrupadora fue borrada o cambió: se descarta en vez de rechazar todo el guardado.
            $kanban = null;
        }
        if ($vista === 'kanban' && $kanban === null) {
            $vista = 'tabla';
        }

        // Igual que el kanban: si la columna de fecha ya no es una fecha se descarta, no se rechaza el guardado.
        $colFecha = $entrada['columna_fecha'] ?? null;
        if ($colFecha !== null && (! is_string($colFecha) || ($porId[$colFecha]['tipo'] ?? null) !== 'fecha')) {
            $colFecha = null;
        }
        if ($vista === 'calendario' && $colFecha === null) {
            $vista = 'tabla';
        }

        $colTitulo = $entrada['columna_titulo'] ?? null;
        if ($colTitulo !== null && (! is_string($colTitulo) || ! isset($porId[$colTitulo]))) {
            $colTitulo = null;
        }

        $visibles = [];
        $visiblesEntrada = $entrada['columnas_visibles'] ?? [];
        if (is_array($visiblesEntrada)) {
            foreach ($visiblesEntrada as $idVisible) {
                if (is_string($idVisible) && isset($porId[$idVisible]) && ! in_array($idVisible, $visibles, true)) {
                    $visibles[] = $idVisible;
                }
            }
        }

        $tabla = [
            'columnas' => $columnas,
            'filas' => $filas,
            'vista' => $vista,
            'columna_kanban' => $kanban,
            'columna_fecha' => $colFecha,
            'columna_titulo' => $colTitulo,
            'columnas_visibles' => $visibles,
            'origen' => self::origen($entrada['origen'] ?? null),
        ];

        if (strlen((string) json_encode($tabla, JSON_UNESCAPED_UNICODE)) > self::MAX_BYTES) {
            throw new InvalidArgumentException('La tabla es demasiado grande para guardarse.');
        }

        return $tabla;
    }

    // ------------------------------------------------------------------
    // Cambio de tipo de una columna ya creada
    // ------------------------------------------------------------------

    /**
     * Cambia el tipo de una columna convirtiendo sus celdas SIN perder datos.
     *
     * Hacia «texto» siempre se puede (todo valor ya es texto). Hacia número,
     * fecha o selección solo si TODAS las celdas llenas encajan: convertir lo
     * que sí cabe y vaciar el resto destruiría datos en silencio, así que se
     * rechaza con un mensaje que dice cuántas celdas lo impiden. Pasar a
     * selección arma las opciones con los valores distintos que ya hay.
     *
     * @throws InvalidArgumentException con un mensaje apto para mostrar al usuario
     */
    public static function cambiarTipoColumna(mixed $entrada, string $colId, string $tipoNuevo): array
    {
        $tabla = self::normalizar($entrada);

        if (! in_array($tipoNuevo, self::TIPOS, true)) {
            throw new InvalidArgumentException('El tipo elegido no es válido.');
        }

        $pos = array_search($colId, array_column($tabla['columnas'], 'id'), true);
        if ($pos === false) {
            throw new InvalidArgumentException('La columna ya no existe.');
        }

        $col = $tabla['columnas'][$pos];
        if ($col['tipo'] === $tipoNuevo) {
            return $tabla;
        }

        $valores = [];
        foreach ($tabla['filas'] as $fila) {
            $valor = (string) ($fila['celdas'][$colId] ?? '');
            if ($valor !== '') {
                $valores[] = $valor;
            }
        }

        $opciones = [];
        if ($tipoNuevo !== 'texto') {
            $nombreTipo = ['numero' => 'Número', 'fecha' => 'Fecha', 'seleccion' => 'Selección'][$tipoNuevo];
            $malas = [];

            foreach ($valores as $valor) {
                $encaja = match ($tipoNuevo) {
                    'numero' => is_numeric($valor) && strlen($valor) <= 30,
                    'fecha' => self::fechaValida($valor),
                    default => mb_strlen($valor) <= self::MAX_NOMBRE,
                };
                if (! $encaja) {
                    $malas[] = $valor;
                }
            }

            if ($malas) {
                $ejemplo = mb_substr($malas[0], 0, 40);
                throw new InvalidArgumentException(
                    "No se puede convertir «{$col['nombre']}» a {$nombreTipo}: ".count($malas)." celda(s) no encajan (p. ej. «{$ejemplo}»). Corrígelas o déjalas vacías y vuelve a intentarlo."
                );
            }

            if ($tipoNuevo === 'seleccion') {
                $opciones = array_values(array_unique($valores));
                if (count($opciones) > self::MAX_OPCIONES) {
                    throw new InvalidArgumentException(
                        "No se puede convertir «{$col['nombre']}» a Selección: tiene ".count($opciones).' valores distintos y el máximo es '.self::MAX_OPCIONES.'.'
                    );
                }
            }
        }

        $tabla['columnas'][$pos]['tipo'] = $tipoNuevo;
        $tabla['columnas'][$pos]['opciones'] = $opciones;

        return self::normalizar($tabla);
    }

    // ------------------------------------------------------------------
    // «Llenar desde...»: foto de una fuente real de solo lectura
    // ------------------------------------------------------------------

    /**
     * Vuelca una foto de datos reales en la tabla.
     *
     * `$foto` = ['columnas' => [['nombre','tipo','opciones'?]], 'filas' => [[valor, ...]]].
     * «reemplazar» sustituye columnas y filas; «agregar» empareja columnas por
     * nombre (sin distinguir mayúsculas) y crea las que falten. Pasa por
     * `normalizar()`, así que valores y topes se validan igual que cualquier
     * guardado. Lo que no cabe en el tope de filas se omite y se informa.
     *
     * @return array{tabla: array, importadas: int, omitidas: int}
     *
     * @throws InvalidArgumentException
     */
    public static function llenarDesdeFoto(mixed $entrada, array $foto, string $modo, string $clave, string $cuando): array
    {
        $tabla = $modo === 'agregar' ? self::normalizar($entrada) : self::vacia();
        $total = max((int) ($foto['total'] ?? 0), count($foto['filas']));

        // posicion de la foto -> id de columna de la tabla
        $destino = [];
        foreach ($foto['columnas'] as $i => $def) {
            $hallada = null;
            foreach ($tabla['columnas'] as $k => $col) {
                if (mb_strtolower($col['nombre']) === mb_strtolower($def['nombre'])) {
                    $hallada = $k;
                    break;
                }
            }

            if ($hallada === null) {
                if (count($tabla['columnas']) >= self::MAX_COLUMNAS) {
                    throw new InvalidArgumentException('No caben las columnas de la fuente: una tabla admite como máximo '.self::MAX_COLUMNAS.' columnas.');
                }
                $nuevoId = self::idLibre('c', array_column($tabla['columnas'], 'id'));
                $tabla['columnas'][] = ['id' => $nuevoId, 'nombre' => $def['nombre'], 'tipo' => $def['tipo'], 'opciones' => $def['opciones'] ?? []];
                $hallada = count($tabla['columnas']) - 1;
            }

            $destino[$i] = $hallada;
        }

        $espacio = self::MAX_FILAS - count($tabla['filas']);
        if ($total > 0 && $espacio <= 0) {
            throw new InvalidArgumentException('La tabla ya tiene el máximo de '.self::MAX_FILAS.' filas.');
        }

        $usados = array_column($tabla['filas'], 'id');
        $agregadas = 0;

        foreach (array_slice($foto['filas'], 0, max(0, $espacio)) as $registro) {
            $celdas = [];
            foreach ($destino as $i => $k) {
                $col = &$tabla['columnas'][$k];
                $valor = trim((string) ($registro[$i] ?? ''));

                // Columna existente de otro tipo: lo que no encaja queda vacío en vez de romper el guardado.
                $valor = match ($col['tipo']) {
                    'numero' => is_numeric($valor) ? $valor : '',
                    'fecha' => self::fechaValida($valor) ? $valor : '',
                    default => mb_substr($valor, 0, $col['tipo'] === 'seleccion' ? self::MAX_NOMBRE : self::MAX_TEXTO),
                };

                if ($col['tipo'] === 'seleccion' && $valor !== '' && ! in_array($valor, $col['opciones'], true)) {
                    $col['opciones'][] = $valor;
                }
                unset($col);

                $celdas[$tabla['columnas'][$k]['id']] = $valor;
            }

            $id = self::idLibre('f', $usados);
            $usados[] = $id;
            $tabla['filas'][] = ['id' => $id, 'celdas' => $celdas];
            $agregadas++;
        }

        $tabla['origen'] = ['clave' => $clave, 'fecha' => $cuando];

        return ['tabla' => self::normalizar($tabla), 'importadas' => $agregadas, 'omitidas' => $total - $agregadas];
    }

    /** Primer id «<prefijo><n>» que no esté en uso. */
    private static function idLibre(string $prefijo, array $usados): string
    {
        $n = count($usados) + 1;
        while (in_array($prefijo.$n, $usados, true)) {
            $n++;
        }

        return $prefijo.$n;
    }

    /** Marca de «de dónde salió la foto»: clave y fecha con forma fija, o nada. */
    private static function origen(mixed $origen): ?array
    {
        if (! is_array($origen)) {
            return null;
        }

        $clave = $origen['clave'] ?? null;
        $fecha = $origen['fecha'] ?? null;

        if (! is_string($clave) || ! preg_match('/^[a-z_]{1,40}$/', $clave)
            || ! is_string($fecha) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $fecha)) {
            return null;
        }

        return ['clave' => $clave, 'fecha' => $fecha];
    }

    // ------------------------------------------------------------------
    // CSV
    // ------------------------------------------------------------------

    /**
     * Filas del CSV de exportación (encabezado incluido). Toda celda de texto
     * pasa por `escaparCelda()` para que Excel/Sheets no ejecute una fórmula
     * escrita por un tercero (inyección de fórmulas en CSV).
     *
     * @return list<list<string>>
     */
    public static function filasCsv(array $tabla): array
    {
        $salida = [array_map(fn ($c) => self::escaparCelda($c['nombre']), $tabla['columnas'])];

        foreach ($tabla['filas'] as $fila) {
            $linea = [];
            foreach ($tabla['columnas'] as $col) {
                $valor = (string) ($fila['celdas'][$col['id']] ?? '');
                // Un número validado (p. ej. -5) no es una fórmula: prefijarlo lo convertiría en texto.
                $linea[] = ($col['tipo'] === 'numero' && is_numeric($valor)) ? $valor : self::escaparCelda($valor);
            }
            $salida[] = $linea;
        }

        return $salida;
    }

    /** Prefija con apóstrofo lo que empiece como fórmula. */
    public static function escaparCelda(string $valor): string
    {
        if ($valor !== '' && in_array($valor[0], self::INICIOS_FORMULA, true)) {
            return "'".$valor;
        }

        return $valor;
    }

    /**
     * Lee un CSV y lo vuelca en una tabla.
     *
     * Sin `$existente` («reemplazar»): las columnas salen del encabezado y el
     * tipo se infiere de los datos. Con `$existente` («agregar»): las filas se
     * suman a esa tabla emparejando columnas por nombre (sin distinguir
     * mayúsculas); las columnas del CSV que no existen se ignoran y las opciones
     * nuevas de una selección se añaden a la columna.
     *
     * @return array{tabla: array, importadas: int, ignoradas: int}
     *
     * @throws InvalidArgumentException
     */
    public static function desdeCsv(string $csv, ?array $existente = null): array
    {
        $registros = self::leerCsv($csv);

        if (count($registros) < 1) {
            throw new InvalidArgumentException('El archivo CSV está vacío.');
        }

        $encabezado = array_map(fn ($c) => self::texto(self::quitarEscape((string) $c), self::MAX_NOMBRE), array_shift($registros));
        $datos = $registros;

        if (count($datos) > self::MAX_FILAS) {
            throw new InvalidArgumentException('El CSV supera el máximo de '.self::MAX_FILAS.' filas.');
        }

        if ($existente === null) {
            return self::reemplazarDesdeCsv($encabezado, $datos);
        }

        return self::agregarDesdeCsv($existente, $encabezado, $datos);
    }

    // ------------------------------------------------------------------
    // Interno
    // ------------------------------------------------------------------

    private static function reemplazarDesdeCsv(array $encabezado, array $datos): array
    {
        if (count($encabezado) > self::MAX_COLUMNAS) {
            throw new InvalidArgumentException('El CSV supera el máximo de '.self::MAX_COLUMNAS.' columnas.');
        }

        $columnas = [];
        foreach ($encabezado as $i => $nombre) {
            $valores = array_map(fn ($f) => self::quitarEscape(trim((string) ($f[$i] ?? ''))), $datos);
            $columnas[] = [
                'id' => 'c'.($i + 1),
                'nombre' => $nombre !== '' ? $nombre : 'Columna '.($i + 1),
                'tipo' => self::inferirTipo($valores),
                'opciones' => [],
            ];
        }

        $filas = [];
        foreach ($datos as $n => $registro) {
            $celdas = [];
            foreach ($columnas as $i => $col) {
                $celdas[$col['id']] = self::valorImportado((string) ($registro[$i] ?? ''), $col['tipo']);
            }
            $filas[] = ['id' => 'f'.($n + 1), 'celdas' => $celdas];
        }

        $tabla = self::normalizar(['columnas' => $columnas, 'filas' => $filas, 'vista' => 'tabla', 'columna_kanban' => null]);

        return ['tabla' => $tabla, 'importadas' => count($filas), 'ignoradas' => 0];
    }

    private static function agregarDesdeCsv(array $existente, array $encabezado, array $datos): array
    {
        $tabla = self::normalizar($existente);

        $minusculas = array_map('mb_strtolower', $encabezado);
        $indicePorColumna = [];
        foreach ($tabla['columnas'] as $k => $col) {
            $pos = array_search(mb_strtolower($col['nombre']), $minusculas, true);
            if ($pos !== false) {
                $indicePorColumna[$k] = $pos;
            }
        }

        if (! $indicePorColumna) {
            throw new InvalidArgumentException('Ninguna columna del CSV coincide con las de la tabla.');
        }

        $ignoradas = max(0, count(array_filter($encabezado, fn ($n) => $n !== '')) - count($indicePorColumna));
        $siguiente = count($tabla['filas']);
        $usados = array_column($tabla['filas'], 'id');

        foreach ($datos as $registro) {
            $celdas = [];
            foreach ($indicePorColumna as $k => $pos) {
                $col = $tabla['columnas'][$k];
                $valor = self::valorImportado((string) ($registro[$pos] ?? ''), $col['tipo']);
                if ($col['tipo'] === 'seleccion' && $valor !== '' && ! in_array($valor, $tabla['columnas'][$k]['opciones'], true)) {
                    $tabla['columnas'][$k]['opciones'][] = $valor;
                }
                $celdas[$col['id']] = $valor;
            }

            do {
                $siguiente++;
                $id = 'f'.$siguiente;
            } while (in_array($id, $usados, true));
            $usados[] = $id;

            $tabla['filas'][] = ['id' => $id, 'celdas' => $celdas];
        }

        return ['tabla' => self::normalizar($tabla), 'importadas' => count($datos), 'ignoradas' => $ignoradas];
    }

    /** Valor de una celda importada, ya coherente con el tipo de su columna (si no encaja, queda vacía). */
    private static function valorImportado(string $bruto, string $tipo): string
    {
        $valor = self::quitarEscape(trim($bruto));

        return match ($tipo) {
            'numero' => is_numeric($valor) ? $valor : '',
            'fecha' => self::fechaValida($valor) ? $valor : '',
            default => self::texto($valor, self::MAX_TEXTO),
        };
    }

    /** @param list<string> $valores */
    private static function inferirTipo(array $valores): string
    {
        $llenos = array_values(array_filter($valores, fn ($v) => $v !== ''));

        if (! $llenos) {
            return 'texto';
        }
        if (count(array_filter($llenos, fn ($v) => is_numeric($v))) === count($llenos)) {
            return 'numero';
        }
        if (count(array_filter($llenos, fn ($v) => self::fechaValida($v))) === count($llenos)) {
            return 'fecha';
        }

        return 'texto';
    }

    /**
     * Quita el apóstrofo que añadió nuestra propia exportación, para que un
     * CSV exportado y vuelto a importar no acumule apóstrofos. Es seguro: nada
     * del módulo evalúa fórmulas, y al exportar se vuelve a escapar.
     */
    private static function quitarEscape(string $valor): string
    {
        if (strlen($valor) > 1 && $valor[0] === "'" && in_array($valor[1], self::INICIOS_FORMULA, true)) {
            return substr($valor, 1);
        }

        return $valor;
    }

    /** @return list<list<string>> */
    private static function leerCsv(string $csv): array
    {
        if (str_starts_with($csv, "\xEF\xBB\xBF")) {
            $csv = substr($csv, 3);
        }
        if (! mb_check_encoding($csv, 'UTF-8')) {
            // Excel en Windows suele guardar en Windows-1252.
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }

        $primera = strtok($csv, "\n") ?: '';
        $delimitador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $csv);
        rewind($flujo);

        $registros = [];
        while (($linea = fgetcsv($flujo, 0, $delimitador, '"', '')) !== false) {
            if ($linea === [null]) {
                continue; // línea en blanco
            }
            $registros[] = array_map(fn ($c) => (string) $c, $linea);
        }
        fclose($flujo);

        return $registros;
    }

    private static function celda(mixed $valor, string $tipo, array $opciones): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_int($valor) || is_float($valor)) {
            $valor = (string) $valor;
        }
        if (! is_string($valor)) {
            throw new InvalidArgumentException('Hay una celda con un valor inválido.');
        }

        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }

        switch ($tipo) {
            case 'numero':
                if (! is_numeric($valor) || strlen($valor) > 30) {
                    throw new InvalidArgumentException("«{$valor}» no es un número válido.");
                }

                return $valor;
            case 'fecha':
                if (! self::fechaValida($valor)) {
                    throw new InvalidArgumentException("«{$valor}» no es una fecha válida (AAAA-MM-DD).");
                }

                return $valor;
            case 'seleccion':
                // Las opciones se recortan a MAX_NOMBRE al normalizar la columna; la celda debe recortarse igual
                // o una opción larga (que el cliente guarda entera) nunca coincidiría y todo el guardado daría 422.
                $valor = mb_substr($valor, 0, self::MAX_NOMBRE);
                if (! in_array($valor, $opciones, true)) {
                    throw new InvalidArgumentException("«{$valor}» no es una de las opciones de la columna.");
                }

                return $valor;
            default:
                return self::texto($valor, self::MAX_TEXTO);
        }
    }

    private static function fechaValida(string $valor): bool
    {
        $fecha = \DateTime::createFromFormat('!Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor;
    }

    private static function id(mixed $valor, string $que): string
    {
        if (! is_string($valor) || ! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $valor)) {
            throw new InvalidArgumentException("Hay una {$que} con un identificador inválido.");
        }

        return $valor;
    }

    /** Recorta espacios y largo máximo; no toca el contenido (se escapa al renderizar). */
    private static function texto(mixed $valor, int $max): string
    {
        return mb_substr(trim((string) $valor), 0, $max);
    }
}
