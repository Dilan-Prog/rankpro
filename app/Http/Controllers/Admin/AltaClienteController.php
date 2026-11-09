<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoClienteServicio;
use App\Enums\FormaPago;
use App\Enums\MetodoPago;
use App\Enums\TipoCobro;
use App\Enums\TipoServicio;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\GscConexion;
use App\Models\GscPropiedad;
use App\Models\IdentidadMarca;
use App\Support\Clientes\ResumenServicios;
use App\Support\Clientes\Sitio;
use App\Support\Plantillas\AplicadorPlantilla;
use App\Support\Plantillas\Giros;
use App\Support\Reglas\Identidad as ReglasIdentidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Asistente de alta de cliente (4 pasos) y alta rápida. A diferencia del generador de Audiencia, que no guarda nada,
 * aquí el alta SÍ persiste: cliente, servicios, métricas, keywords semilla, identidad y conexión de Search Console se
 * crean en una sola transacción (o no se crea nada). El correo se valida también por paso para avisar antes del final.
 */
class AltaClienteController extends Controller
{
    /** Valida los pasos 1..N sin guardar nada (el asistente lo llama al pulsar "Siguiente"). */
    public function validarPaso(Request $request): JsonResponse
    {
        $paso = (int) $request->input('paso', 1);
        abort_unless($paso >= 1 && $paso <= 4, 422, 'Paso no válido.');

        $request->validate($this->reglasHasta($paso), $this->mensajes());

        return response()->json(['ok' => true, 'paso' => $paso]);
    }

    /** MRR y totales del paso 3 con la fuente única del sistema; el asistente los pide mientras se captura. */
    public function resumenServicios(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'servicios' => ['nullable', 'array', 'max:20'],
            'servicios.*.tipo_cobro' => ['nullable', Rule::enum(TipoCobro::class)],
            'servicios.*.precio_mensual' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(ResumenServicios::calcular($datos['servicios'] ?? []));
    }

    public function store(Request $request, AplicadorPlantilla $aplicador): JsonResponse
    {
        $rapido = $request->input('modo') === 'rapido';
        $datos = $request->validate($rapido ? $this->reglasRapidas() : $this->reglasHasta(4), $this->mensajes());

        $rutaLogo = null;

        try {
            $resultado = DB::transaction(function () use ($request, $datos, $rapido, $aplicador, &$rutaLogo) {
                $contacto = trim($datos['contacto_nombre']);
                $cliente = Cliente::create([
                    // `nombre` es obligatorio en la tabla y en la práctica guarda a la persona; `empresa` es el nombre comercial.
                    'nombre' => $contacto,
                    'empresa' => trim($datos['nombre_comercial']),
                    'contacto_nombre' => $contacto,
                    'giro' => $datos['giro'] ?? null,
                    'sitio_web' => Sitio::normalizar($datos['sitio_web'] ?? null),
                    'ciudad' => $datos['ciudad'] ?? null,
                    'email' => $datos['email'] ?? null,
                    'telefono' => $datos['telefono'] ?? null,
                    'estado' => EstadoClienteServicio::Activo,
                    'fecha_inicio_contrato' => $datos['fecha_inicio_contrato'] ?? null,
                    'fecha_renovacion_contrato' => $datos['fecha_renovacion_contrato'] ?? null,
                    'forma_pago' => $datos['forma_pago'] ?? null,
                    'metodo_pago' => $datos['metodo_pago'] ?? null,
                ]);

                $resumen = null;
                $conexiones = ['gsc' => 'omitida'];

                if (! $rapido) {
                    // Sin la clave, la plantilla es la del giro; con la clave vacía el usuario eligió "Sin plantilla".
                    $plantilla = $request->has('plantilla') ? ($datos['plantilla'] ?? null) : ($datos['giro'] ?? null);
                    $resumen = $aplicador->aplicar($cliente, $plantilla, $datos['servicios'] ?? [], $datos['tema'] ?? null, $request->user()?->id);
                    $this->guardarIdentidad($request, $cliente, $datos, $rutaLogo);
                    $conexiones['gsc'] = $this->conectarSearchConsole($cliente, (bool) ($datos['conexiones']['gsc'] ?? false));
                }

                return compact('cliente', 'resumen', 'conexiones');
            });
        } catch (\Throwable $e) {
            // La transacción ya deshizo la base; el archivo del logo vive fuera de ella y se borra a mano.
            if ($rutaLogo && Storage::disk('local')->exists($rutaLogo)) {
                Storage::disk('local')->delete($rutaLogo);
            }
            throw $e;
        }

        /** @var Cliente $cliente */
        $cliente = $resultado['cliente'];
        $servicios = $cliente->servicios()->get();

        return response()->json([
            'cliente' => ['id' => $cliente->id, 'nombre_comercial' => $cliente->nombre_comercial],
            'modo' => $rapido ? 'rapido' : 'completo',
            'perfil_completo' => $cliente->perfilCompleto(),
            'faltantes' => array_values($cliente->camposFaltantes()),
            'mrr' => \App\Support\FinanzasMetrics::mrrDeServicios($servicios),
            'resumen' => $resultado['resumen'],
            'conexiones' => $resultado['conexiones'],
            'url_ficha' => route('admin.clientes.show', $cliente),
            'url_integraciones' => route('admin.clientes.integraciones', $cliente),
        ], 201);
    }

    /** El logo se guarda al final de la transacción y su ruta se devuelve por referencia para poder limpiarlo si algo falla después. */
    private function guardarIdentidad(Request $request, Cliente $cliente, array $datos, ?string &$rutaLogo): void
    {
        $hayMarca = $request->hasFile('logo') || ! empty($datos['colores']) || ! empty($datos['tonos']);
        if (! $hayMarca) {
            return;
        }

        $identidad = new IdentidadMarca(['cliente_id' => $cliente->id]);
        if ($request->hasFile('logo')) {
            $rutaLogo = $request->file('logo')->store("clientes/{$cliente->id}/identidad", 'local');
            $identidad->logo_ruta = $rutaLogo;
        }
        $identidad->fill([
            'paleta' => ReglasIdentidad::paleta($datos['colores'] ?? []),
            'tonos' => array_values(array_unique($datos['tonos'] ?? [])),
        ])->save();
    }

    /**
     * Crea la propiedad de Search Console solo si ya hay una llave de Google guardada; sin ella no se finge nada
     * (el asistente enlaza a Integraciones). Devuelve el resultado para mostrarlo tal cual.
     */
    private function conectarSearchConsole(Cliente $cliente, bool $solicitada): string
    {
        if (! $solicitada) {
            return 'omitida';
        }
        if (blank($cliente->sitio_web)) {
            return 'sin_sitio';
        }
        if (! GscConexion::actual()?->tieneLlave()) {
            return 'sin_conexion_google';
        }

        GscPropiedad::firstOrCreate(
            ['cliente_id' => $cliente->id],
            ['site_url' => Sitio::urlSearchConsole($cliente->sitio_web), 'activa' => true]
        );

        return 'creada';
    }

    /** Alta rápida: nombre comercial y contacto; lo demás es opcional y el perfil queda marcado como incompleto. */
    private function reglasRapidas(): array
    {
        return $this->reglasPaso1() + $this->reglasPaso2();
    }

    /** @return array<string, mixed> las reglas de los pasos 1..$paso juntas */
    private function reglasHasta(int $paso): array
    {
        $reglas = $this->reglasPaso1();
        if ($paso >= 2) {
            $reglas += $this->reglasPaso2();
        }
        if ($paso >= 3) {
            $reglas += $this->reglasPaso3();
        }
        if ($paso >= 4) {
            $reglas += $this->reglasPaso4();
        }

        return $reglas;
    }

    private function reglasPaso1(): array
    {
        return [
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'giro' => ['nullable', 'string', Rule::in(Giros::claves())],
            'sitio_web' => ['nullable', 'string', 'max:255', fn ($atributo, $valor, $fallo) => Sitio::esValido($valor) ?: $fallo('El sitio web no parece una dirección válida (por ejemplo hotelejemplo.com).')],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'plantilla' => ['nullable', 'string', Rule::in(Giros::claves())],
            'tema' => ['nullable', 'string', 'max:120'],
        ];
    }

    private function reglasPaso2(): array
    {
        return [
            'contacto_nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->where(fn ($q) => $q->whereNull('deleted_at'))],
            'fecha_inicio_contrato' => ['nullable', 'date'],
            'fecha_renovacion_contrato' => ['nullable', 'date', 'after_or_equal:fecha_inicio_contrato'],
            'forma_pago' => ['nullable', Rule::enum(FormaPago::class)],
            'metodo_pago' => ['nullable', Rule::enum(MetodoPago::class)],
        ];
    }

    private function reglasPaso3(): array
    {
        return [
            'servicios' => ['nullable', 'array', 'max:20'],
            'servicios.*.tipo' => ['required', Rule::enum(TipoServicio::class)],
            'servicios.*.nombre' => ['required', 'string', 'max:255'],
            'servicios.*.tipo_cobro' => ['required', Rule::enum(TipoCobro::class)],
            // A propósito opcional: la plantilla no trae precios y el usuario puede completarlos después en Servicios.
            'servicios.*.precio_mensual' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function reglasPaso4(): array
    {
        return [
            'logo' => ReglasIdentidad::guardar()['logo'],
            'colores' => ReglasIdentidad::guardar()['colores'],
            'colores.*.nombre' => ReglasIdentidad::guardar()['colores.*.nombre'],
            'colores.*.hex' => ReglasIdentidad::guardar()['colores.*.hex'],
            'tonos' => ReglasIdentidad::guardar()['tonos'],
            'tonos.*' => ReglasIdentidad::guardar()['tonos.*'],
            'conexiones' => ['nullable', 'array'],
            'conexiones.gsc' => ['nullable', 'boolean'],
        ];
    }

    private function mensajes(): array
    {
        return ReglasIdentidad::mensajes() + [
            'nombre_comercial.required' => 'Escribe el nombre comercial.',
            'contacto_nombre.required' => 'Escribe la persona de contacto.',
            'email.unique' => 'Ya existe un cliente con ese correo.',
            'fecha_renovacion_contrato.after_or_equal' => 'El vencimiento no puede ser anterior al inicio.',
            'servicios.*.nombre.required' => 'Cada servicio necesita un nombre.',
        ];
    }
}
