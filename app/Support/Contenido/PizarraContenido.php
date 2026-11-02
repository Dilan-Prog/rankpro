<?php

namespace App\Support\Contenido;

use InvalidArgumentException;

/**
 * Estructura, validación y plantillas de la «Pizarra» del módulo Contenido.
 *
 * Se guarda en la columna `contenido` como JSON:
 *   {"elementos":[{id,tipo,x,y,ancho,alto,texto,color,desde,hasta}], "vista":{x,y,zoom}}
 *
 * El navegador manda la pizarra ENTERA y aquí se reconstruye campo por campo
 * (lista blanca): lo que no está en la estructura no se guarda, así un cliente
 * manipulado no puede colar claves ni objetos anidados. El texto se guarda tal
 * cual lo escribió la persona (puede contener «<» legítimamente); la defensa
 * contra HTML está en la salida: la vista lo entrega en un atributo escapado por
 * Blade y el JS lo pinta solo con textContent, nunca con innerHTML.
 */
class PizarraContenido
{
    public const TIPOS = ['nota', 'texto', 'rectangulo', 'elipse', 'flecha'];

    public const MAX_ELEMENTOS = 500;
    public const MAX_TEXTO = 2000;
    public const MAX_BYTES = 1500000;

    /** Coordenadas del mundo (px): holgadas para un lienzo grande, acotadas para que nada se pierda en el infinito. */
    public const COORD_MIN = -20000;
    public const COORD_MAX = 20000;
    public const TAM_MIN = 20;
    public const TAM_MAX = 4000;

    public const ZOOM_MIN = 0.2;
    public const ZOOM_MAX = 3.0;

    /** Colores por defecto de cada tipo (mismos que ofrece la paleta del editor). */
    public const COLORES = ['#FDE68A', '#A7F3D0', '#FBCFE8', '#BFDBFE', '#FED7AA'];

    public static function vacia(): array
    {
        return ['elementos' => [], 'vista' => ['x' => 0, 'y' => 0, 'zoom' => 1]];
    }

    /** Plantillas disponibles al crear una pizarra: «en blanco» no tiene clave (es el valor por defecto). */
    public const PLANTILLAS = ['lluvia_ideas', 'flujo'];

    public static function plantilla(?string $nombre): array
    {
        return match ($nombre) {
            'lluvia_ideas' => self::plantillaLluviaIdeas(),
            'flujo' => self::plantillaFlujo(),
            default => self::vacia(),
        };
    }

    /** Pregunta central con seis ideas alrededor, unidas por flechas que apuntan a la pregunta. */
    private static function plantillaLluviaIdeas(): array
    {
        $elementos = [self::el('centro', 'elipse', 380, 230, 240, 110, '¿Cuál es la pregunta?', '#BFDBFE')];
        $posiciones = [[120, 60], [380, 20], [640, 60], [120, 400], [380, 440], [640, 400]];

        foreach ($posiciones as $i => [$x, $y]) {
            $elementos[] = self::el('idea'.($i + 1), 'nota', $x, $y, 160, 110, 'Idea '.($i + 1), self::COLORES[$i % count(self::COLORES)]);
        }
        foreach ($posiciones as $i => $_) {
            $elementos[] = self::flecha('flecha'.($i + 1), 'idea'.($i + 1), 'centro');
        }

        return ['elementos' => $elementos, 'vista' => ['x' => 40, 'y' => 40, 'zoom' => 1]];
    }

    /** Tres pasos en fila unidos por flechas. */
    private static function plantillaFlujo(): array
    {
        $elementos = [
            self::el('paso1', 'rectangulo', 80, 160, 200, 90, 'Paso 1', '#A7F3D0'),
            self::el('paso2', 'rectangulo', 380, 160, 200, 90, 'Paso 2', '#BFDBFE'),
            self::el('paso3', 'rectangulo', 680, 160, 200, 90, 'Paso 3', '#FBCFE8'),
            self::flecha('flecha1', 'paso1', 'paso2'),
            self::flecha('flecha2', 'paso2', 'paso3'),
        ];

        return ['elementos' => $elementos, 'vista' => ['x' => 40, 'y' => 40, 'zoom' => 1]];
    }

    private static function el(string $id, string $tipo, int $x, int $y, int $ancho, int $alto, string $texto, string $color): array
    {
        return [
            'id' => $id, 'tipo' => $tipo, 'x' => $x, 'y' => $y, 'ancho' => $ancho, 'alto' => $alto,
            'texto' => $texto, 'color' => $color, 'desde' => null, 'hasta' => null,
        ];
    }

    private static function flecha(string $id, string $desde, string $hasta): array
    {
        return [
            'id' => $id, 'tipo' => 'flecha', 'x' => 0, 'y' => 0, 'ancho' => 0, 'alto' => 0,
            'texto' => '', 'color' => '#94A3B8', 'desde' => $desde, 'hasta' => $hasta,
        ];
    }

    /** Lee lo guardado; si no es una pizarra válida (columna vacía, JSON roto) devuelve una en blanco en vez de fallar la pantalla. */
    public static function decodificar(?string $json): array
    {
        if ($json === null || trim($json) === '') {
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
     * Valida y reconstruye la pizarra.
     *
     * @throws InvalidArgumentException con un mensaje legible para la persona.
     */
    public static function normalizar(array $datos): array
    {
        $crudos = $datos['elementos'] ?? [];
        if (! is_array($crudos) || ($crudos !== [] && ! array_is_list($crudos))) {
            throw new InvalidArgumentException('La lista de elementos no es válida.');
        }
        if (count($crudos) > self::MAX_ELEMENTOS) {
            throw new InvalidArgumentException('Una pizarra admite hasta '.self::MAX_ELEMENTOS.' elementos.');
        }

        $elementos = [];
        $ids = [];
        foreach ($crudos as $crudo) {
            if (! is_array($crudo)) {
                throw new InvalidArgumentException('Hay un elemento con formato inválido.');
            }
            $id = $crudo['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
                throw new InvalidArgumentException('Hay un elemento con identificador inválido.');
            }
            if (isset($ids[$id])) {
                throw new InvalidArgumentException('Hay identificadores de elemento repetidos.');
            }
            $tipo = $crudo['tipo'] ?? null;
            if (! is_string($tipo) || ! in_array($tipo, self::TIPOS, true)) {
                throw new InvalidArgumentException('Hay un elemento con un tipo que no existe.');
            }
            $ids[$id] = $tipo;
            $elementos[] = ['id' => $id, 'tipo' => $tipo, 'crudo' => $crudo];
        }

        $salida = [];
        foreach ($elementos as $e) {
            $salida[] = $e['tipo'] === 'flecha'
                ? self::normalizarFlecha($e['id'], $e['crudo'], $ids)
                : self::normalizarFigura($e['id'], $e['tipo'], $e['crudo']);
        }

        $resultado = ['elementos' => $salida, 'vista' => self::normalizarVista($datos['vista'] ?? null)];

        if (strlen((string) json_encode($resultado, JSON_UNESCAPED_UNICODE)) > self::MAX_BYTES) {
            throw new InvalidArgumentException('La pizarra es demasiado grande para guardarse.');
        }

        return $resultado;
    }

    private static function normalizarFigura(string $id, string $tipo, array $c): array
    {
        return [
            'id' => $id,
            'tipo' => $tipo,
            'x' => self::numero($c['x'] ?? null, self::COORD_MIN, self::COORD_MAX, 'La posición'),
            'y' => self::numero($c['y'] ?? null, self::COORD_MIN, self::COORD_MAX, 'La posición'),
            'ancho' => self::numero($c['ancho'] ?? null, self::TAM_MIN, self::TAM_MAX, 'El ancho'),
            'alto' => self::numero($c['alto'] ?? null, self::TAM_MIN, self::TAM_MAX, 'El alto'),
            'texto' => self::texto($c['texto'] ?? ''),
            'color' => self::color($c['color'] ?? null),
            'desde' => null,
            'hasta' => null,
        ];
    }

    /** Las flechas no tienen posición propia: sus extremos son un id existente o un punto {x, y}. */
    private static function normalizarFlecha(string $id, array $c, array $ids): array
    {
        $extremo = function ($valor, string $nombre) use ($id, $ids) {
            if (is_string($valor)) {
                if ($valor === $id || ! isset($ids[$valor]) || $ids[$valor] === 'flecha') {
                    throw new InvalidArgumentException("Una flecha apunta a un elemento que no existe ($nombre).");
                }

                return $valor;
            }
            if (is_array($valor) && array_key_exists('x', $valor) && array_key_exists('y', $valor)) {
                return [
                    'x' => self::numero($valor['x'], self::COORD_MIN, self::COORD_MAX, 'El extremo de la flecha'),
                    'y' => self::numero($valor['y'], self::COORD_MIN, self::COORD_MAX, 'El extremo de la flecha'),
                ];
            }

            throw new InvalidArgumentException("Una flecha no tiene extremo válido ($nombre).");
        };

        return [
            'id' => $id,
            'tipo' => 'flecha',
            'x' => 0, 'y' => 0, 'ancho' => 0, 'alto' => 0,
            'texto' => '',
            'color' => self::color($c['color'] ?? null),
            'desde' => $extremo($c['desde'] ?? null, 'inicio'),
            'hasta' => $extremo($c['hasta'] ?? null, 'fin'),
        ];
    }

    private static function normalizarVista($vista): array
    {
        if (! is_array($vista)) {
            return ['x' => 0, 'y' => 0, 'zoom' => 1];
        }

        return [
            'x' => self::numero($vista['x'] ?? 0, -100000, 100000, 'La vista'),
            'y' => self::numero($vista['y'] ?? 0, -100000, 100000, 'La vista'),
            'zoom' => self::numero($vista['zoom'] ?? 1, self::ZOOM_MIN, self::ZOOM_MAX, 'El zoom'),
        ];
    }

    /** Número finito dentro del rango, redondeado a 1 decimal. Rechaza (no recorta) lo fuera de rango: un valor así indica un cliente roto o manipulado. */
    private static function numero($valor, float $min, float $max, string $que): float|int
    {
        if (is_string($valor) && is_numeric($valor)) {
            $valor = $valor + 0;
        }
        if (! is_int($valor) && ! is_float($valor)) {
            throw new InvalidArgumentException("$que de un elemento no es un número.");
        }
        if (! is_finite((float) $valor) || $valor < $min || $valor > $max) {
            throw new InvalidArgumentException("$que de un elemento está fuera del rango permitido.");
        }

        $redondeado = round((float) $valor, 1);

        return floor($redondeado) == $redondeado ? (int) $redondeado : $redondeado;
    }

    private static function texto($valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (! is_string($valor)) {
            throw new InvalidArgumentException('El texto de un elemento no es válido.');
        }
        // Sin caracteres de control (salvo salto de línea y tabulador): no aportan nada y ensucian la salida.
        $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r\n", "\n", $valor));
        if ($limpio === null) {
            throw new InvalidArgumentException('El texto de un elemento tiene codificación inválida.');
        }
        if (mb_strlen($limpio) > self::MAX_TEXTO) {
            throw new InvalidArgumentException('Un elemento admite hasta '.self::MAX_TEXTO.' caracteres de texto.');
        }

        return $limpio;
    }

    private static function color($valor): string
    {
        if (! is_string($valor) || ! preg_match('/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $valor)) {
            throw new InvalidArgumentException('Hay un color que no es hexadecimal (#RRGGBB).');
        }

        return strtoupper($valor);
    }
}
