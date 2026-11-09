<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Support\Keywords\ImportadorKeywords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ImportadorKeywords, extraído de KeywordImportController: mismo comportamiento, ahora reutilizable. */
class ImportadorKeywordsTest extends TestCase
{
    use RefreshDatabase;

    private function lista(): KeywordLista
    {
        return KeywordLista::create(['cliente_id' => Cliente::factory()->create()->id, 'nombre' => 'L', 'canal' => 'seo', 'estado' => 'en_uso']);
    }

    public function test_lineas_quita_el_bom_y_las_vacias(): void
    {
        $this->assertSame(['uno', 'dos'], ImportadorKeywords::lineas("\xEF\xBB\xBFuno\r\n\r\ndos\n"));
        $this->assertSame([], ImportadorKeywords::lineas('  '));
        $this->assertSame(['a'], ImportadorKeywords::lineas(["\xEF\xBB\xBFa", '']));
        $this->assertSame([], ImportadorKeywords::lineas(false));
    }

    public function test_detecta_coma_punto_y_coma_y_tabulador(): void
    {
        $i = new ImportadorKeywords();

        $this->assertSame(',', $i->detectarSeparador(['a,1,2']));
        $this->assertSame(';', $i->detectarSeparador(['a;1;2', 'b;3']));
        $this->assertSame("\t", $i->detectarSeparador(["a\t1\t2"]));
        $this->assertSame(',', $i->detectarSeparador(['solo texto']));
    }

    public function test_importa_reporta_duplicadas_y_errores_por_fila(): void
    {
        $lista = $this->lista();
        Keyword::create(['cliente_id' => $lista->cliente_id, 'lista_id' => $lista->id, 'keyword' => 'Ya Existe']);

        $r = (new ImportadorKeywords())->importar($lista, [
            'nueva uno;100;30;1.5;transaccional',
            'ya existe',
            'nueva uno',
            'mala;abc',
            'nueva dos;50;200',
        ]);

        $this->assertCount(1, $r['creadas']);
        $this->assertSame(2, $r['duplicadas']);
        $this->assertCount(2, $r['errores']);
        $this->assertSame([4, 5], array_column($r['errores'], 'fila'));
        $this->assertDatabaseHas('keywords', ['lista_id' => $lista->id, 'keyword' => 'nueva uno', 'volumen_busqueda' => 100]);
    }

    public function test_es_transaccional_si_una_fila_revienta_no_queda_a_medias(): void
    {
        $lista = $this->lista();

        try {
            (new ImportadorKeywords())->importar($lista, ['buena uno', ['no es texto']]);
        } catch (\Throwable) {
            // esperado: una "línea" que no es texto rompe el procesamiento
        }

        $this->assertSame(0, Keyword::where('lista_id', $lista->id)->count());
    }
}
