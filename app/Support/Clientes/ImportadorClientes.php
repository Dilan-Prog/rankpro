<?php

namespace App\Support\Clientes;

use App\Enums\EstadoClienteServicio;
use App\Models\Cliente;
use App\Support\Keywords\ImportadorKeywords;
use App\Support\Plantillas\Giros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Importación de clientes desde CSV (pegado o archivo; también sirve "Archivo -> Publicar en la web -> CSV" de Google Sheets).
 *
 * Columnas: nombre_comercial, contacto, email, telefono, giro, sitio_web, ciudad. Con encabezado se leen por nombre
 * (en cualquier orden); sin él, por posición. La vista previa (`analizar`) NO guarda nada y clasifica cada fila:
 * `nuevo`, `duplicado` (mismo correo o mismo nombre comercial, contra la base y contra filas anteriores del archivo) o
 * `error`. Nada se descarta en silencio: cada fila no creada lleva su motivo. Las filas sin contacto, correo, WhatsApp
 * o renovación se crean igual, marcadas con perfil incompleto (lo persigue el tablero de captura).
 */
class ImportadorClientes
{
    public const COLUMNAS = ['nombre_comercial', 'contacto', 'email', 'telefono', 'giro', 'sitio_web', 'ciudad'];

    public const MAX_FILAS = 500;

    private const ALIAS = [
        'nombre_comercial' => ['nombre_comercial', 'nombre comercial', 'empresa', 'negocio', 'cliente'],
        'contacto' => ['contacto', 'contacto_nombre', 'persona', 'nombre', 'nombre contacto'],
        'email' => ['email', 'correo', 'correo electronico', 'e-mail', 'mail'],
        'telefono' => ['telefono', 'whatsapp', 'celular', 'tel', 'movil'],
        'giro' => ['giro', 'industria', 'sector'],
        'sitio_web' => ['sitio_web', 'sitio web', 'sitio', 'web', 'url', 'pagina web'],
        'ciudad' => ['ciudad', 'localidad', 'municipio'],
    ];

    /**
     * @return array{filas: array<int, array<string, mixed>>, resumen: array{total: int, nuevos: int, duplicados: int, errores: int, incompletos: int}, encabezado: bool}
     */
    public function analizar(string|array $origen): array
    {
        $lineas = ImportadorKeywords::lineas($origen);
        if ($lineas === []) {
            return ['filas' => [], 'resumen' => $this->resumen([]), 'encabezado' => false];
        }
        $separador = (new ImportadorKeywords())->detectarSeparador($lineas);
        [$mapa, $tieneEncabezado] = $this->detectarEncabezado($lineas[0], $separador);
        $inicio = $tieneEncabezado ? 1 : 0;
        if (count($lineas) - $inicio > self::MAX_FILAS) {
            throw new \InvalidArgumentException('El archivo tiene más de '.self::MAX_FILAS.' filas; divídelo en partes.');
        }

        // Una sola consulta con lo existente; los duplicados dentro del archivo se detectan al vuelo.
        $existentes = Cliente::query()->get(['id', 'nombre', 'empresa', 'email']);
        $porCorreo = [];
        $porNombre = [];
        foreach ($existentes as $c) {
            if (filled($c->email)) {
                $porCorreo[mb_strtolower(trim($c->email))] = $c->nombre_comercial;
            }
            $porNombre[self::normalizar($c->nombre_comercial)] = $c->nombre_comercial;
        }

        $filas = [];
        for ($i = $inicio; $i < count($lineas); $i++) {
            $celdas = array_map('trim', str_getcsv(trim($lineas[$i]), $separador));
            $datos = [];
            foreach (self::COLUMNAS as $posicion => $columna) {
                $indice = $tieneEncabezado ? ($mapa[$columna] ?? null) : $posicion;
                $datos[$columna] = $indice !== null && isset($celdas[$indice]) && $celdas[$indice] !== '' ? $celdas[$indice] : null;
            }

            $fila = ['fila' => $i + 1, 'texto' => $lineas[$i], 'datos' => $datos, 'estado' => 'nuevo', 'motivos' => [], 'faltantes' => []];
            $motivos = $this->validar($datos);

            if ($motivos === []) {
                $correo = $datos['email'] ? mb_strtolower($datos['email']) : null;
                $nombre = self::normalizar($datos['nombre_comercial']);
                if ($correo && isset($porCorreo[$correo])) {
                    $fila['estado'] = 'duplicado';
                    $fila['motivos'][] = "Ya existe un cliente con el correo {$datos['email']} ({$porCorreo[$correo]}).";
                } elseif (isset($porNombre[$nombre])) {
                    $fila['estado'] = 'duplicado';
                    $fila['motivos'][] = "Ya existe un cliente con el nombre comercial «{$porNombre[$nombre]}».";
                } else {
                    if ($correo) {
                        $porCorreo[$correo] = $datos['nombre_comercial'];
                    }
                    $porNombre[$nombre] = $datos['nombre_comercial'];
                    $fila['faltantes'] = array_values($this->construir($datos)->camposFaltantes());
                }
            } else {
                $fila['estado'] = 'error';
                $fila['motivos'] = $motivos;
            }

            $filas[] = $fila;
        }

        return ['filas' => $filas, 'resumen' => $this->resumen($filas), 'encabezado' => $tieneEncabezado];
    }

    /**
     * Crea los clientes `nuevo` de la vista previa, todos o ninguno. Devuelve el mismo análisis con `creados`.
     *
     * @return array{filas: array<int, array<string, mixed>>, resumen: array<string, int>, encabezado: bool, creados: int}
     */
    public function importar(string|array $origen): array
    {
        $analisis = $this->analizar($origen);
        $creados = 0;

        DB::transaction(function () use (&$analisis, &$creados) {
            foreach ($analisis['filas'] as &$fila) {
                if ($fila['estado'] !== 'nuevo') {
                    continue;
                }
                $cliente = $this->construir($fila['datos']);
                $cliente->save();
                $fila['cliente_id'] = $cliente->id;
                $creados++;
            }
        });

        return $analisis + ['creados' => $creados];
    }

    /** `nombre` guarda a la persona (o el nombre comercial si no hay contacto): es obligatorio en la tabla. */
    private function construir(array $d): Cliente
    {
        $giro = $this->resolverGiro($d['giro'] ?? null);

        return new Cliente([
            'nombre' => $d['contacto'] ?: $d['nombre_comercial'],
            'empresa' => $d['nombre_comercial'],
            'contacto_nombre' => $d['contacto'],
            'email' => $d['email'],
            'telefono' => $d['telefono'],
            'giro' => $giro,
            'sitio_web' => Sitio::normalizar($d['sitio_web'] ?? null),
            'ciudad' => $d['ciudad'],
            'estado' => EstadoClienteServicio::Activo,
        ]);
    }

    /** @return array<int, string> motivos de error (vacío = fila válida) */
    private function validar(array $d): array
    {
        $v = Validator::make($d, [
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'sitio_web' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre_comercial.required' => 'Falta el nombre comercial.',
            'email.email' => 'El correo no es válido.',
        ]);
        $motivos = $v->errors()->all();

        if (filled($d['giro']) && $this->resolverGiro($d['giro']) === null) {
            $motivos[] = 'Giro no reconocido «'.$d['giro'].'». Usa: '.implode(', ', Giros::etiquetas()).'.';
        }
        if (filled($d['sitio_web']) && ! Sitio::esValido($d['sitio_web'])) {
            $motivos[] = 'El sitio web no parece una dirección válida.';
        }

        return $motivos;
    }

    /** Acepta la clave (`hotel`) o la etiqueta (`Hotel`, `E-commerce`), sin importar mayúsculas ni acentos. */
    private function resolverGiro(?string $texto): ?string
    {
        if (blank($texto)) {
            return null;
        }
        $buscado = self::normalizar($texto);
        foreach (Giros::todos() as $clave => $giro) {
            if ($buscado === self::normalizar($clave) || $buscado === self::normalizar($giro['etiqueta'])) {
                return $clave;
            }
        }

        return null;
    }

    /** @return array{0: array<string, int>, 1: bool} mapa columna => posición, y si la primera línea es encabezado */
    private function detectarEncabezado(string $primera, string $separador): array
    {
        $celdas = array_map(fn ($c) => self::normalizar($c), str_getcsv(trim($primera), $separador));
        $mapa = [];
        foreach (self::ALIAS as $columna => $alias) {
            foreach ($celdas as $indice => $celda) {
                if (in_array($celda, array_map([self::class, 'normalizar'], $alias), true) && ! in_array($indice, $mapa, true)) {
                    $mapa[$columna] = $indice;
                    break;
                }
            }
        }

        // Es encabezado si reconoce el nombre comercial y al menos otra columna conocida (un negocio llamado "Cliente" no basta).
        return [$mapa, isset($mapa['nombre_comercial']) && count($mapa) >= 2];
    }

    private function resumen(array $filas): array
    {
        $cuenta = fn (string $estado) => count(array_filter($filas, fn ($f) => $f['estado'] === $estado));

        return [
            'total' => count($filas),
            'nuevos' => $cuenta('nuevo'),
            'duplicados' => $cuenta('duplicado'),
            'errores' => $cuenta('error'),
            'incompletos' => count(array_filter($filas, fn ($f) => $f['estado'] === 'nuevo' && $f['faltantes'] !== [])),
        ];
    }

    /** Minúsculas, sin acentos ni espacios repetidos: "Hotel  Fratelli" y "hotel fratelli" son el mismo negocio. */
    public static function normalizar(?string $texto): string
    {
        $texto = mb_strtolower(trim((string) $texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/\s+/u', ' ', $texto);
    }
}
