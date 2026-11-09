<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Models\User;
use App\Support\Clientes\ImportadorClientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Importación de clientes por CSV: vista previa sin guardar, errores por fila, duplicados y perfil incompleto. */
class ImportadorClientesTest extends TestCase
{
    use RefreshDatabase;

    private const ENCABEZADO = 'nombre_comercial,contacto,email,telefono,giro,sitio_web,ciudad';

    private function importador(): ImportadorClientes
    {
        return app(ImportadorClientes::class);
    }

    public function test_la_vista_previa_clasifica_cada_fila_y_no_guarda_nada(): void
    {
        Cliente::factory()->create(['email' => 'existe@correo.com', 'empresa' => 'Otro Negocio']);

        $r = $this->importador()->analizar(implode("\n", [
            self::ENCABEZADO,
            'Hotel Uno,Ana Ruiz,ana@uno.com,4491112222,Hotel,hoteluno.com,Aguascalientes',
            ',Sin Nombre,sin@nombre.com,,,,',
            'Negocio Con Correo Malo,Luis,no-es-correo,,,,',
            'Repetido Por Correo,Eva,existe@correo.com,,,,',
        ]));

        $this->assertTrue($r['encabezado']);
        $this->assertSame(['total' => 4, 'nuevos' => 1, 'duplicados' => 1, 'errores' => 2, 'incompletos' => 1], $r['resumen']);
        $this->assertSame(['nuevo', 'error', 'error', 'duplicado'], array_column($r['filas'], 'estado'));
        $this->assertSame([2, 3, 4, 5], array_column($r['filas'], 'fila'), 'el número de fila cuenta el encabezado');
        $this->assertStringContainsString('Falta el nombre comercial', $r['filas'][1]['motivos'][0]);
        $this->assertStringContainsString('correo no es válido', $r['filas'][2]['motivos'][0]);
        $this->assertStringContainsString('existe@correo.com', $r['filas'][3]['motivos'][0]);
        $this->assertSame(1, Cliente::count(), 'la vista previa no crea clientes');
    }

    public function test_detecta_duplicados_por_nombre_comercial_sin_importar_acentos_ni_mayusculas(): void
    {
        Cliente::factory()->create(['empresa' => 'Hotel Fratelli']);
        Cliente::factory()->create(['empresa' => '', 'nombre' => 'Café Ñandú']);

        $r = $this->importador()->analizar("hotel  FRATELLI,A\ncafe nandu,B\nNuevo Negocio,C");

        $this->assertSame(['duplicado', 'duplicado', 'nuevo'], array_column($r['filas'], 'estado'));
        $this->assertStringContainsString('nombre comercial', $r['filas'][0]['motivos'][0]);
    }

    public function test_detecta_duplicados_dentro_del_mismo_archivo_por_correo_y_por_nombre(): void
    {
        $r = $this->importador()->analizar(implode("\n", [
            'Alfa,Ana,a@x.com',
            'Beta,Bea,a@x.com',
            'ALFA,Cora,c@x.com',
            'Gamma,Dora,',
        ]));

        $this->assertSame(['nuevo', 'duplicado', 'duplicado', 'nuevo'], array_column($r['filas'], 'estado'));
    }

    public function test_acepta_bom_punto_y_coma_tabulador_y_columnas_en_otro_orden(): void
    {
        $bom = "\xEF\xBB\xBF";
        $conPuntoYComa = $this->importador()->analizar($bom."nombre_comercial;contacto;email\nHotel PyC;Ana;ana@pyc.com");
        $this->assertSame('Hotel PyC', $conPuntoYComa['filas'][0]['datos']['nombre_comercial'], 'el BOM no ensucia el encabezado');
        $this->assertSame('ana@pyc.com', $conPuntoYComa['filas'][0]['datos']['email']);

        $conTab = $this->importador()->analizar("Hotel Tab\tLuis\tluis@tab.com\t449");
        $this->assertFalse($conTab['encabezado']);
        $this->assertSame('449', $conTab['filas'][0]['datos']['telefono']);

        $reordenado = $this->importador()->analizar("ciudad,Correo,Empresa\nLeón,x@y.com,Zapatos León");
        $this->assertTrue($reordenado['encabezado']);
        $this->assertSame('Zapatos León', $reordenado['filas'][0]['datos']['nombre_comercial']);
        $this->assertSame('León', $reordenado['filas'][0]['datos']['ciudad']);
        $this->assertSame('x@y.com', $reordenado['filas'][0]['datos']['email']);
    }

    public function test_sin_encabezado_usa_el_orden_de_las_columnas_y_un_negocio_llamado_cliente_no_se_toma_por_encabezado(): void
    {
        $r = $this->importador()->analizar("Cliente,Pedro,p@c.com");

        $this->assertFalse($r['encabezado']);
        $this->assertSame('Cliente', $r['filas'][0]['datos']['nombre_comercial']);
    }

    public function test_giro_por_clave_o_etiqueta_y_giro_desconocido_es_error(): void
    {
        $r = $this->importador()->analizar(implode("\n", [
            'A,x,,,hotel',
            'B,x,,,E-commerce',
            'C,x,,,B2B / industrial',
            'D,x,,,astronauta',
        ]));

        $this->assertSame(['nuevo', 'nuevo', 'nuevo', 'error'], array_column($r['filas'], 'estado'));
        $this->assertStringContainsString('Giro no reconocido', $r['filas'][3]['motivos'][0]);
    }

    public function test_sitio_web_invalido_es_error_de_la_fila(): void
    {
        $r = $this->importador()->analizar("A,x,,,,no es un sitio\nB,x,,,,https://b.com/ruta");

        $this->assertSame(['error', 'nuevo'], array_column($r['filas'], 'estado'));
    }

    public function test_importar_crea_solo_las_nuevas_y_marca_el_perfil_incompleto(): void
    {
        Cliente::factory()->create(['email' => 'dup@x.com']);

        $r = $this->importador()->importar(implode("\n", [
            self::ENCABEZADO,
            'Hotel Uno,Ana Ruiz,ana@uno.com,4491112222,hotel,hoteluno.com,Aguascalientes',
            'Solo Nombre,,,,,,',
            'Dup,Zeta,dup@x.com,,,,',
            ',Mal,,,,,',
        ]));

        $this->assertSame(2, $r['creados']);
        $this->assertSame(3, Cliente::count());

        $uno = Cliente::where('empresa', 'Hotel Uno')->sole();
        $this->assertSame('Ana Ruiz', $uno->nombre);
        $this->assertSame('hotel', $uno->giro);
        $this->assertSame('https://hoteluno.com', $uno->sitio_web);
        $this->assertSame('Aguascalientes', $uno->ciudad);
        $this->assertSame('activo', $uno->estado->value);
        // El CSV no trae renovación: todo cliente importado queda por completar.
        $this->assertSame(['Fecha de renovación'], array_values($uno->camposFaltantes()));

        $solo = Cliente::where('empresa', 'Solo Nombre')->sole();
        $this->assertSame('Solo Nombre', $solo->nombre, '`nombre` es obligatorio: cae al nombre comercial');
        $this->assertFalse($solo->perfilCompleto());
        $this->assertCount(4, $solo->camposFaltantes());

        $this->assertSame(['nuevo', 'nuevo', 'duplicado', 'error'], array_column($r['filas'], 'estado'));
        $this->assertArrayHasKey('cliente_id', $r['filas'][0]);
    }

    public function test_importar_dos_veces_el_mismo_archivo_no_duplica(): void
    {
        $csv = self::ENCABEZADO."\nHotel Uno,Ana,ana@uno.com,,,,";

        $this->importador()->importar($csv);
        $segunda = $this->importador()->importar($csv);

        $this->assertSame(0, $segunda['creados']);
        $this->assertSame('duplicado', $segunda['filas'][0]['estado']);
        $this->assertSame(1, Cliente::count());
    }

    public function test_el_limite_de_filas_se_informa(): void
    {
        $filas = implode("\n", array_map(fn ($i) => "Negocio $i,x", range(1, ImportadorClientes::MAX_FILAS + 1)));

        $this->expectException(\InvalidArgumentException::class);
        $this->importador()->analizar($filas);
    }

    // ---- HTTP --------------------------------------------------------------------

    public function test_endpoints_vista_previa_e_importar(): void
    {
        $u = User::factory()->create();

        $previa = $this->actingAs($u)->postJson(route('admin.clientes.importar.vista-previa'), ['texto' => self::ENCABEZADO."\nHotel Uno,Ana,ana@uno.com,,,,"])
            ->assertOk()->assertJsonPath('resumen.nuevos', 1);
        $this->assertSame(0, Cliente::count());
        $this->assertArrayNotHasKey('creados', $previa->json());

        $this->actingAs($u)->postJson(route('admin.clientes.importar.store'), ['texto' => self::ENCABEZADO."\nHotel Uno,Ana,ana@uno.com,,,,"])
            ->assertCreated()->assertJsonPath('creados', 1);
        $this->assertSame(1, Cliente::count());
    }

    public function test_importar_sin_ninguna_fila_nueva_responde_422_con_el_detalle(): void
    {
        Cliente::factory()->create(['empresa' => 'Hotel Uno']);

        $this->actingAs(User::factory()->create())->postJson(route('admin.clientes.importar.store'), ['texto' => 'Hotel Uno,Ana'])
            ->assertStatus(422)
            ->assertJsonPath('filas.0.estado', 'duplicado')
            ->assertJsonPath('creados', 0);
    }

    public function test_acepta_un_archivo_csv_y_rechaza_vacio_y_otros_formatos(): void
    {
        $u = User::factory()->create();
        $archivo = UploadedFile::fake()->createWithContent('clientes.csv', "\xEF\xBB\xBF".self::ENCABEZADO."\nHotel Archivo,Ana,,,,,");

        $this->actingAs($u)->post(route('admin.clientes.importar.store'), ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('creados', 1);

        $this->actingAs($u)->postJson(route('admin.clientes.importar.vista-previa'), ['texto' => "  \n "])->assertStatus(422);
        $this->actingAs($u)->post(route('admin.clientes.importar.vista-previa'), ['archivo' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_exige_sesion(): void
    {
        $this->postJson(route('admin.clientes.importar.vista-previa'), ['texto' => 'a'])->assertUnauthorized();
        $this->postJson(route('admin.clientes.importar.store'), ['texto' => 'a'])->assertUnauthorized();
    }
}
