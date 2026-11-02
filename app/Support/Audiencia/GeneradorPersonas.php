<?php

namespace App\Support\Audiencia;

use InvalidArgumentException;

/**
 * Arma 3 buyer personas BORRADOR a partir de 4 respuestas de un cuestionario.
 *
 * Es una plantilla de texto, no IA ni análisis de mercado: reordena lo que el
 * usuario escribió dentro de frases fijas. Por eso es determinista (mismas
 * respuestas, mismo resultado), no inventa cifras ni datos demográficos y
 * nunca guarda nada: devuelve arreglos con las mismas claves que el formulario
 * de la buyer persona para que el usuario los revise, edite y guarde él.
 *
 * Las frases de dolor genéricas («no sabe en quién confiar»…) son hipótesis
 * comunes de compra, no hallazgos: la interfaz lo rotula como tal.
 */
class GeneradorPersonas
{
    /** Límite de caracteres por respuesta; el controlador valida con estos mismos valores. */
    public const LIMITES = [
        'producto' => 120,
        'publico' => 300,
        'dolor' => 500,
        'donde' => 200,
    ];

    /** Con más dolores el borrador se vuelve una lista larga que nadie revisa. */
    private const MAX_DOLORES = 5;

    private const MAX_DOLOR_LARGO = 120;

    /** Límites de las columnas de buyer_personas (ver AudienciaController::validar). */
    private const MAX_NOMBRE = 120;

    private const MAX_TEXTO = 2000;

    private const MAX_CANALES = 255;

    /**
     * @param  array<string, mixed>  $respuestas  claves: producto, publico, dolor, donde
     * @return list<array{nombre: string, perfil: string, dolores: string, mensaje: string, canales: string}>
     *
     * @throws InvalidArgumentException si alguna respuesta queda vacía tras limpiarla
     */
    public function generar(array $respuestas): array
    {
        $producto = $this->respuesta($respuestas, 'producto');
        $publico = rtrim($this->respuesta($respuestas, 'publico'), ' .,;:');
        $donde = $this->respuesta($respuestas, 'donde');
        $dolores = $this->separarDolores($this->respuesta($respuestas, 'dolor', true));

        if ($dolores === []) {
            throw new InvalidArgumentException('Escribe al menos un dolor o problema.');
        }

        // Con menos de 3 dolores se reutiliza el primero, como en el prototipo.
        $dolorA = $dolores[0];
        $dolorB = $dolores[1] ?? $dolores[0];
        $dolorC = $dolores[2] ?? $dolores[0];
        $productoMin = mb_strtolower($producto);

        $borradores = [
            [
                'nombre' => 'El decisor práctico',
                'perfil' => "{$publico}. Perfil hipotético: quiere resolver rápido y comparar poco.",
                'dolores' => [$dolorA, 'No sabe en quién confiar', 'Teme perder tiempo y dinero'],
                'mensaje' => $producto.': una solución concreta para que «'.mb_strtolower($dolorA).'» deje de ser un problema.',
                'canales' => $donde,
            ],
            [
                'nombre' => 'El explorador cuidadoso',
                'perfil' => "{$publico}. Perfil hipotético: investiga, compara opciones y busca pruebas antes de decidir.",
                'dolores' => [$dolorB, 'Demasiada información contradictoria', 'Miedo a equivocarse'],
                'mensaje' => "Explica con claridad cómo funciona {$productoMin} y qué puede esperar; responde sus dudas antes de pedirle que decida.",
                'canales' => $donde,
            ],
            [
                'nombre' => 'El recomendado',
                'perfil' => "{$publico}. Perfil hipotético: llega por recomendación y espera una experiencia acorde a lo que le contaron.",
                'dolores' => [$dolorC, 'Expectativas altas desde el primer contacto', 'Poca paciencia con procesos largos'],
                'mensaje' => "Cuida la primera impresión: {$productoMin} con atención cercana y respuestas directas.",
                'canales' => "Recomendaciones de clientes y conocidos, reseñas · {$donde}",
            ],
        ];

        return array_map(fn (array $b) => [
            'nombre' => $this->cortar($b['nombre'], self::MAX_NOMBRE),
            'perfil' => $this->cortar($b['perfil'], self::MAX_TEXTO),
            // Sin repetidos: con un solo dolor, los tres primeros serían la misma línea.
            'dolores' => $this->cortar(implode("\n", $this->sinRepetidos($b['dolores'])), self::MAX_TEXTO),
            'mensaje' => $this->cortar($b['mensaje'], self::MAX_TEXTO),
            'canales' => $this->cortar($b['canales'], self::MAX_CANALES),
        ], $borradores);
    }

    private function respuesta(array $respuestas, string $clave, bool $conservarSaltos = false): string
    {
        $valor = $respuestas[$clave] ?? '';
        $texto = is_scalar($valor) ? (string) $valor : '';
        // Se recorta ANTES de limpiar para no procesar un texto gigante enviado a mano.
        $texto = mb_substr($texto, 0, self::LIMITES[$clave] * 2);
        $texto = $this->limpiar($texto, $conservarSaltos);
        $texto = trim(mb_substr($texto, 0, self::LIMITES[$clave]));

        if ($texto === '') {
            throw new InvalidArgumentException("La respuesta «{$clave}» está vacía.");
        }

        return $texto;
    }

    /**
     * Deja texto plano: sin etiquetas ni caracteres de control. El HTML no se
     * escapa aquí (Blade y el JS ya escapan al pintar; escapar dos veces
     * mostraría «&amp;»), pero sin `<` ni `>` no puede colarse marcado.
     */
    private function limpiar(string $texto, bool $conservarSaltos): string
    {
        $texto = strip_tags($texto);
        $texto = str_replace(['<', '>'], '', $texto);
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? '';

        if ($conservarSaltos) {
            // Los saltos de línea separan dolores; el resto de espacios se compacta.
            $texto = preg_replace('/\R/u', "\n", $texto) ?? '';

            return preg_replace('/[^\S\n]+/u', ' ', $texto) ?? '';
        }

        return preg_replace('/\s+/u', ' ', $texto) ?? '';
    }

    /** @return list<string> */
    private function separarDolores(string $texto): array
    {
        $dolores = [];
        foreach (preg_split('/[\n,;]+/u', $texto) ?: [] as $parte) {
            $parte = trim($parte);
            if ($parte !== '') {
                $dolores[] = $this->cortar($parte, self::MAX_DOLOR_LARGO);
            }
        }

        return array_slice($this->sinRepetidos($dolores), 0, self::MAX_DOLORES);
    }

    /**
     * @param  list<string>  $lineas
     * @return list<string>
     */
    private function sinRepetidos(array $lineas): array
    {
        $vistos = [];
        $salida = [];
        foreach ($lineas as $linea) {
            $clave = mb_strtolower($linea);
            if (! isset($vistos[$clave])) {
                $vistos[$clave] = true;
                $salida[] = $linea;
            }
        }

        return $salida;
    }

    private function cortar(string $texto, int $max): string
    {
        return rtrim(mb_substr($texto, 0, $max));
    }
}
