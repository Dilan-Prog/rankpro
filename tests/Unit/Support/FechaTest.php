<?php

namespace Tests\Unit\Support;

use App\Support\Fecha;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Formato único de fechas visibles (dd/mm/aaaa). Usa Tests\TestCase para tener
 * config('app.timezone') y la directiva Blade; no toca la base de datos.
 */
class FechaTest extends TestCase
{
    public function test_formatea_carbon_y_datetime(): void
    {
        $this->assertSame('05/12/2026', Fecha::mx(Carbon::create(2026, 12, 5, 23, 59)));
        $this->assertSame('05/12/2026', Fecha::mx(CarbonImmutable::create(2026, 12, 5)));
        $this->assertSame('05/12/2026', Fecha::mx(new \DateTime('2026-12-05 08:00:00')));
        $this->assertSame('05/12/2026 23:59', Fecha::mx(Carbon::create(2026, 12, 5, 23, 59), true));
    }

    public function test_formatea_strings_iso(): void
    {
        $this->assertSame('05/12/2026', Fecha::mx('2026-12-05'));
        $this->assertSame('05/12/2026', Fecha::mx('2026-12-05 14:30:00'));
        $this->assertSame('05/12/2026 14:30', Fecha::mx('2026-12-05 14:30:00', true));
        $this->assertSame('05/12/2026 08:05', Fecha::mx('2026-12-05T08:05', true));
        $this->assertSame('05/12/2026', Fecha::mx('  2026-12-05  '));
        $this->assertSame('05/12/2026 00:00', Fecha::mx('2026-12-05', true));
    }

    public function test_fechas_limite(): void
    {
        $this->assertSame('31/12/2026', Fecha::mx('2026-12-31'));
        $this->assertSame('01/01/2027', Fecha::mx(Carbon::create(2026, 12, 31, 12)->addDay()));
        $this->assertSame('29/02/2028', Fecha::mx('2028-02-29'));
        $this->assertSame('29/02/2028', Fecha::mx(Carbon::create(2028, 2, 29)));
    }

    public function test_zona_explicita_pasa_a_la_zona_de_la_app(): void
    {
        config(['app.timezone' => 'America/Mexico_City']);

        // 02:30 UTC del 5 de diciembre ya es el 4 por la noche en México.
        $this->assertSame('04/12/2026 20:30', Fecha::mx('2026-12-05T02:30:00.000000Z', true));
        $this->assertSame('04/12/2026', Fecha::mx('2026-12-05T02:30:00Z'));
    }

    public function test_acepta_lo_que_ya_viene_en_dd_mm_aaaa(): void
    {
        $this->assertSame('05/12/2026', Fecha::mx('05/12/2026'));
        $this->assertSame('05/12/2026 14:30', Fecha::mx('05/12/2026 14:30', true));
        $this->assertSame('—', Fecha::mx('31/02/2026'));
    }

    public function test_nulos_vacios_e_invalidos_no_lanzan(): void
    {
        foreach ([null, '', '   ', 'no es fecha', '2026-13-01', '2026-02-29', '2026-02-30', '0000-00-00', 12345, [], new \stdClass(), true] as $malo) {
            $this->assertSame('—', Fecha::mx($malo), var_export($malo, true));
        }

        $this->assertSame('', Fecha::mx(null, false, ''));
        $this->assertSame('Sin fecha', Fecha::mx('', false, 'Sin fecha'));
    }

    public function test_en_texto_reescribe_solo_las_fechas_iso_validas(): void
    {
        $this->assertSame('21/07/2026 — 09/09/2026', Fecha::enTexto('2026-07-21 — 2026-09-09'));
        $this->assertSame('act. 03/09/2026 15:18', Fecha::enTexto('act. 2026-09-03 15:18:22'));
        $this->assertSame('sin cambios 2026-02-30', Fecha::enTexto('sin cambios 2026-02-30'));
        $this->assertSame('', Fecha::enTexto(null));
        $this->assertSame('05/12/2026', fecha_mx_texto('2026-12-05'));
    }

    public function test_helper_global_y_directiva_blade(): void
    {
        $this->assertSame('05/12/2026', fecha_mx('2026-12-05'));
        $this->assertSame('05/12/2026 14:30', fecha_mx('2026-12-05 14:30:00', true));
        $this->assertSame('—', fecha_mx(null));

        $html = \Illuminate\Support\Facades\Blade::render('@fecha($f)|@fecha($f, true)|@fecha($n)', [
            'f' => Carbon::create(2026, 12, 5, 9, 7),
            'n' => null,
        ]);

        $this->assertSame('05/12/2026|05/12/2026 09:07|—', $html);
    }
}
