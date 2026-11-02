<?php

namespace Tests\Unit\Support\Tablero;

use App\Enums\EstadoKeyword;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordMedicion;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Support\Tablero\TableroCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Las pruebas de buckets, variación y estado vacío usan `analizar()` y los
 * helpers estáticos (sin base de datos); la consulta real de `calcular()` se
 * prueba al final con RefreshDatabase.
 */
class TableroClienteTest extends TestCase
{
    use RefreshDatabase;

    private function med(int $id, string $fecha, ?int $pos, string $nombre = ''): array
    {
        return ['keyword_id' => $id, 'nombre' => $nombre ?: "kw{$id}", 'fecha' => $fecha, 'posicion' => $pos];
    }

    public function test_distribuye_en_rangos_y_manda_nulos_y_mayores_a_100_fuera(): void
    {
        $filas = TableroCliente::distribuir([1 => 1, 2 => 3, 3 => 4, 4 => 10, 5 => 11, 6 => 20, 7 => 21, 8 => 50, 9 => 51, 10 => 100, 11 => 101, 12 => null]);
        $cantidades = array_column($filas, 'cantidad', 'clave');

        $this->assertSame(['top3' => 2, 'top10' => 2, 'top20' => 2, 'top50' => 2, 'top100' => 2, 'fuera' => 2], $cantidades);
        $this->assertSame(16.7, $filas[0]['porcentaje']);
    }

    public function test_top_es_acumulado(): void
    {
        $top = TableroCliente::acumulados([2, 8, 15, 40, null]);

        $this->assertSame(1, $top['top3']['cantidad']);
        $this->assertSame(2, $top['top10']['cantidad']);
        $this->assertSame(3, $top['top20']['cantidad']);
        $this->assertSame(20.0, $top['top3']['porcentaje']);
    }

    public function test_sin_ronda_anterior_la_variacion_es_null(): void
    {
        $r = TableroCliente::analizar([$this->med(1, '2026-10-01', 8), $this->med(2, '2026-10-01', 12)], 2);

        $this->assertSame(10.0, $r['posicion_media']['actual']);
        $this->assertNull($r['posicion_media']['anterior']);
        $this->assertNull($r['posicion_media']['mejora']);
        $this->assertSame([], $r['mejores']);
    }

    public function test_variacion_compara_solo_keywords_con_posicion_en_ambas_rondas(): void
    {
        $r = TableroCliente::analizar([
            $this->med(1, '2026-09-01', 10),
            $this->med(2, '2026-09-01', 20),
            $this->med(1, '2026-10-01', 6),   // mejora 4
            $this->med(2, '2026-10-01', 22),  // empeora 2
            $this->med(3, '2026-10-01', 1),   // keyword nueva: no entra en la variación
        ], 3);

        $media = $r['posicion_media'];
        $this->assertSame(9.7, $media['actual']);
        $this->assertSame(15.0, $media['anterior']);
        $this->assertSame(1.0, $media['mejora']); // (10+20)/2=15 -> (6+22)/2=14
        $this->assertSame(2, $media['comparables']);
        $this->assertSame('2026-10-01', $media['fecha_actual']);
        $this->assertSame('2026-09-01', $media['fecha_anterior']);
    }

    public function test_ronda_sin_posiciones_comunes_no_inventa_variacion(): void
    {
        $r = TableroCliente::analizar([
            $this->med(1, '2026-09-01', null),
            $this->med(1, '2026-10-01', 5),
        ], 1);

        $this->assertNull($r['posicion_media']['anterior']);
        $this->assertNull($r['posicion_media']['mejora']);
        $this->assertSame(0, $r['posicion_media']['comparables']);
    }

    public function test_ranking_de_mejoras_y_caidas_ordenado_y_sin_nulos(): void
    {
        $r = TableroCliente::analizar([
            $this->med(1, '2026-09-01', 30, 'sube mucho'), $this->med(1, '2026-10-01', 5, 'sube mucho'),
            $this->med(2, '2026-09-01', 10, 'sube poco'), $this->med(2, '2026-10-01', 9, 'sube poco'),
            $this->med(3, '2026-09-01', 3, 'cae'), $this->med(3, '2026-10-01', 15, 'cae'),
            $this->med(4, '2026-09-01', 4, 'igual'), $this->med(4, '2026-10-01', 4, 'igual'),
            $this->med(5, '2026-09-01', 7, 'sale del top'), $this->med(5, '2026-10-01', null, 'sale del top'),
        ], 5);

        $this->assertSame(['sube mucho', 'sube poco'], array_column($r['mejores'], 'nombre'));
        $this->assertSame(25, $r['mejores'][0]['mejora']);
        $this->assertSame(['cae'], array_column($r['peores'], 'nombre'));
        $this->assertSame(-12, $r['peores'][0]['mejora']);
    }

    public function test_usa_la_ultima_medicion_de_cada_keyword_para_los_buckets(): void
    {
        $r = TableroCliente::analizar([
            $this->med(1, '2026-10-05', 2),
            $this->med(1, '2026-09-05', 40), // más antigua, no cuenta
            $this->med(2, '2026-09-05', 7),  // kw2 solo medida en septiembre: su última es esa
        ], 3);

        $this->assertSame(2, $r['keywords']['medidas']);
        $this->assertSame(1, $r['keywords']['sin_medir']);
        $this->assertSame(1, $r['top']['top3']['cantidad']);
        $this->assertSame(2, $r['top']['top10']['cantidad']);
        $this->assertSame(100.0, $r['top']['top10']['porcentaje']);
    }

    public function test_serie_corta_a_las_ultimas_rondas_y_marca_huecos_con_null(): void
    {
        $med = [];
        foreach (range(1, 14) as $m) {
            $med[] = $this->med(1, sprintf('2026-01-%02d', $m), $m === 13 ? null : $m);
        }
        $serie = TableroCliente::analizar($med, 1)['serie'];

        $this->assertCount(TableroCliente::RONDAS_EN_SERIE, $serie);
        $this->assertSame('2026-01-03', $serie[0]['fecha']);
        $hueco = collect($serie)->firstWhere('fecha', '2026-01-13');
        $this->assertNull($hueco['media']);
        $this->assertSame(0, $hueco['con_posicion']);
    }

    public function test_estado_vacio_sin_keywords_ni_mediciones(): void
    {
        $r = TableroCliente::analizar([], 0);

        $this->assertSame(['seguimiento' => 0, 'medidas' => 0, 'sin_medir' => 0], $r['keywords']);
        $this->assertNull($r['top']['top3']['porcentaje']);
        $this->assertNull($r['posicion_media']['actual']);
        $this->assertSame([], $r['serie']);
        $this->assertSame([], $r['mejores']);
        $this->assertSame([], $r['peores']);
        $this->assertSame(0, array_sum(array_column($r['distribucion'], 'cantidad')));
        $this->assertNull(TableroCliente::porcentaje(5, 0));
        $this->assertNull(TableroCliente::media([null, null]));
    }

    public function test_calcular_lee_keywords_mediciones_y_tareas_solo_del_cliente(): void
    {
        $hoy = Carbon::parse('2026-11-01');
        $cliente = Cliente::factory()->create();
        $otro = Cliente::factory()->create();

        $kw = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'agencia seo', 'estado' => EstadoKeyword::Seguimiento->value]);
        $descartada = Keyword::create(['cliente_id' => $cliente->id, 'keyword' => 'descartada', 'estado' => EstadoKeyword::Descartada->value]);
        $ajena = Keyword::create(['cliente_id' => $otro->id, 'keyword' => 'ajena', 'estado' => EstadoKeyword::Seguimiento->value]);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'fecha' => '2026-09-01', 'posicion' => 12]);
        KeywordMedicion::create(['keyword_id' => $kw->id, 'fecha' => '2026-10-01', 'posicion' => 4]);
        KeywordMedicion::create(['keyword_id' => $descartada->id, 'fecha' => '2026-10-01', 'posicion' => 1]);
        KeywordMedicion::create(['keyword_id' => $ajena->id, 'fecha' => '2026-10-01', 'posicion' => 1]);

        $proyecto = Proyecto::create(['cliente_id' => $cliente->id, 'nombre' => 'Web', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion']);
        $ajeno = Proyecto::create(['cliente_id' => $otro->id, 'nombre' => 'Otra web', 'tipo' => 'web_nueva', 'fase_actual' => 'planeacion']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'vencida', 'estado' => 'pendiente', 'fecha_limite' => '2026-10-30']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'a tiempo', 'estado' => 'en_progreso', 'fecha_limite' => '2026-11-01']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'sin fecha', 'estado' => 'pendiente']);
        Tarea::create(['proyecto_id' => $proyecto->id, 'titulo' => 'hecha', 'estado' => 'completada', 'fecha_limite' => '2026-10-01']);
        Tarea::create(['proyecto_id' => $ajeno->id, 'titulo' => 'de otro', 'estado' => 'pendiente', 'fecha_limite' => '2026-10-01']);

        $r = (new TableroCliente($hoy))->calcular($cliente);

        $this->assertSame(['seguimiento' => 1, 'medidas' => 1, 'sin_medir' => 0], $r['keywords']);
        $this->assertSame(1, $r['top']['top10']['cantidad']);
        $this->assertSame(8.0, $r['posicion_media']['mejora']);
        $this->assertSame(1, $r['tareas']['vencidas']);
        $this->assertSame(3, $r['tareas']['abiertas']);
        $this->assertSame(33.3, $r['tareas']['porcentaje_vencidas']);
    }

    public function test_cliente_sin_datos_devuelve_ceros_y_nulls(): void
    {
        $r = (new TableroCliente())->calcular(Cliente::factory()->create());

        $this->assertSame(0, $r['keywords']['seguimiento']);
        $this->assertNull($r['posicion_media']['mejora']);
        $this->assertSame(0, $r['tareas']['vencidas']);
        $this->assertNull($r['tareas']['porcentaje_vencidas']);
    }
}
