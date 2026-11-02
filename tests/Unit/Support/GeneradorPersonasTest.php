<?php

namespace Tests\Unit\Support;

use App\Support\Audiencia\GeneradorPersonas;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * La plantilla del cuestionario de Audiencia: determinista, sin marcado,
 * con longitudes acotadas y sin aceptar respuestas vacías.
 */
class GeneradorPersonasTest extends TestCase
{
    private function respuestas(array $cambios = []): array
    {
        return array_merge([
            'producto' => 'Clínica dental',
            'publico' => 'Familias de la zona',
            'dolor' => 'Miedo al dentista, precios poco claros, falta de tiempo',
            'donde' => 'Google, Instagram',
        ], $cambios);
    }

    public function test_genera_tres_borradores_con_las_claves_del_formulario(): void
    {
        $personas = (new GeneradorPersonas())->generar($this->respuestas());

        $this->assertCount(3, $personas);
        foreach ($personas as $persona) {
            $this->assertSame(['nombre', 'perfil', 'dolores', 'mensaje', 'canales'], array_keys($persona));
            $this->assertNotSame('', $persona['nombre']);
        }
        $this->assertSame(['El decisor práctico', 'El explorador cuidadoso', 'El recomendado'], array_column($personas, 'nombre'));
    }

    public function test_es_determinista(): void
    {
        $generador = new GeneradorPersonas();

        $this->assertSame($generador->generar($this->respuestas()), $generador->generar($this->respuestas()));
        $this->assertSame($generador->generar($this->respuestas()), (new GeneradorPersonas())->generar($this->respuestas()));
    }

    public function test_reparte_los_dolores_del_usuario_entre_las_personas(): void
    {
        $personas = (new GeneradorPersonas())->generar($this->respuestas());

        $this->assertStringStartsWith('Miedo al dentista', $personas[0]['dolores']);
        $this->assertStringStartsWith('precios poco claros', $personas[1]['dolores']);
        $this->assertStringStartsWith('falta de tiempo', $personas[2]['dolores']);
        $this->assertStringContainsString('Clínica dental', $personas[0]['mensaje']);
        $this->assertStringContainsString('Google, Instagram', $personas[2]['canales']);
    }

    public function test_con_un_solo_dolor_no_repite_lineas(): void
    {
        $personas = (new GeneradorPersonas())->generar($this->respuestas(['dolor' => 'Falta de tiempo']));

        foreach ($personas as $persona) {
            $lineas = explode("\n", $persona['dolores']);
            $this->assertSame($lineas, array_values(array_unique($lineas)));
            $this->assertSame('Falta de tiempo', $lineas[0]);
        }
    }

    public function test_acepta_dolores_separados_por_lineas_comas_o_punto_y_coma(): void
    {
        $personas = (new GeneradorPersonas())->generar($this->respuestas(['dolor' => "uno\ndos;tres, cuatro"]));

        $this->assertStringStartsWith('uno', $personas[0]['dolores']);
        $this->assertStringStartsWith('dos', $personas[1]['dolores']);
        $this->assertStringStartsWith('tres', $personas[2]['dolores']);
    }

    public function test_quita_etiquetas_y_caracteres_de_control(): void
    {
        $personas = (new GeneradorPersonas())->generar($this->respuestas([
            'producto' => '<script>alert(1)</script>Tienda <b>online</b>',
            'publico' => "Gente <img src=x onerror=alert(1)> joven\x00",
            'dolor' => '<i>Precios</i> altos',
            'donde' => "Redes\x07 <a href=\"javascript:x\">sociales</a>",
        ]));

        foreach ($personas as $persona) {
            foreach ($persona as $texto) {
                $this->assertStringNotContainsString('<', $texto);
                $this->assertStringNotContainsString('>', $texto);
                $this->assertStringNotContainsString("\x00", $texto);
                $this->assertStringNotContainsString("\x07", $texto);
            }
        }
        $this->assertStringContainsString('Tienda online', $personas[0]['mensaje']);
        $this->assertStringStartsWith('Precios altos', $personas[0]['dolores']);
    }

    public function test_limita_las_longitudes_al_tamano_de_las_columnas(): void
    {
        $largo = str_repeat('á', 5000);
        $personas = (new GeneradorPersonas())->generar([
            'producto' => $largo,
            'publico' => $largo,
            'dolor' => $largo.','.$largo,
            'donde' => $largo,
        ]);

        foreach ($personas as $persona) {
            $this->assertLessThanOrEqual(120, mb_strlen($persona['nombre']));
            $this->assertLessThanOrEqual(2000, mb_strlen($persona['perfil']));
            $this->assertLessThanOrEqual(2000, mb_strlen($persona['dolores']));
            $this->assertLessThanOrEqual(2000, mb_strlen($persona['mensaje']));
            $this->assertLessThanOrEqual(255, mb_strlen($persona['canales']));
        }
    }

    /** @dataProvider respuestasVacias */
    public function test_rechaza_entradas_vacias(string $clave, mixed $valor): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GeneradorPersonas())->generar($this->respuestas([$clave => $valor]));
    }

    public static function respuestasVacias(): array
    {
        return [
            'producto vacio' => ['producto', ''],
            'publico solo espacios' => ['publico', "  \n\t "],
            'dolor solo separadores' => ['dolor', ' , ; ,'],
            'donde solo etiquetas' => ['donde', '<b></b>'],
            'producto no texto' => ['producto', ['x']],
            'producto nulo' => ['producto', null],
        ];
    }

    public function test_rechaza_si_falta_una_clave(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GeneradorPersonas())->generar(['producto' => 'X', 'publico' => 'Y', 'dolor' => 'Z']);
    }

    public function test_no_inventa_cifras_ni_datos_demograficos(): void
    {
        $personas = (new GeneradorPersonas())->generar($this->respuestas());

        // Sin números en ningún borrador cuando el usuario no escribió ninguno.
        foreach ($personas as $persona) {
            $this->assertDoesNotMatchRegularExpression('/\d/', implode(' ', $persona));
        }
    }
}
