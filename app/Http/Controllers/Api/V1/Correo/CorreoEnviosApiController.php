<?php

namespace App\Http\Controllers\Api\V1\Correo;

use App\Enums\EstadoEnvioCorreo;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Services\Correo\EnviadorCorreo;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Correo\Bloques;
use App\Support\Correo\Variables;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Envíos de correo. Mismo contrato de payload que Admin\CorreoEnviosController
 * (plantilla_id, asunto, destinatarios[], variables, accion, programado_para),
 * con las mismas reglas (ver ese controlador). `enviar` es SÍNCRONO — ver el
 * aviso en routes/api/v1/correo.php: para no bloquear la petición, usa
 * `programar` con `programado_para` cercano y deja que el scheduler
 * `correo:procesar-programados` (cada minuto) lo mande.
 */
class CorreoEnviosApiController extends ControladorApi
{
    public function index(Request $request): JsonResponse
    {
        return $this->listar(CorreoEnvio::query(), $request, new ConsultaOpciones(
            buscarEn: ['asunto'],
            filtrosExactos: ['estado', 'plantilla_id'],
            ordenables: ['id', 'asunto', 'enviado_en', 'programado_para', 'created_at', 'updated_at'],
            incluibles: ['plantilla', 'creador', 'destinatarios'],
        ));
    }

    /** `?incluir=destinatarios` (o `plantilla`, `creador`). */
    public function show(Request $request, CorreoEnvio $envio): JsonResponse
    {
        $incluir = array_values(array_intersect(
            array_filter(explode(',', (string) $request->string('incluir'))),
            ['destinatarios', 'plantilla', 'creador']
        ));

        if ($incluir !== []) {
            $envio->load($incluir);
        }

        return Respuesta::recurso(Serializador::modelo($envio, $incluir));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $envio = DB::transaction(function () use ($data) {
            $envio = CorreoEnvio::create($this->atributos($data) + [
                'estado' => EstadoEnvioCorreo::Borrador->value,
                'creado_por' => Auth::id(),
            ]);
            $this->sincronizarDestinatarios($envio, $data['destinatarios'] ?? []);

            return $envio;
        });

        return $this->ejecutarAccion($envio, $data);
    }

    public function update(Request $request, CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return Respuesta::mensaje('Este envío ya no se puede editar.', 422);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($envio, $data) {
            $envio->update($this->atributos($data) + [
                'estado' => EstadoEnvioCorreo::Borrador->value,
                'programado_para' => null,
            ]);
            $this->sincronizarDestinatarios($envio, $data['destinatarios'] ?? []);
        });

        return $this->ejecutarAccion($envio->fresh(), $data);
    }

    public function destroy(CorreoEnvio $envio): JsonResponse
    {
        $borrables = [EstadoEnvioCorreo::Borrador, EstadoEnvioCorreo::Cancelado, EstadoEnvioCorreo::Fallido];

        if (! in_array($envio->estado, $borrables, true)) {
            return Respuesta::mensaje('Solo se eliminan borradores, cancelados o fallidos.', 422);
        }

        $envio->delete();

        return Respuesta::eliminado();
    }

    public function enviar(CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return Respuesta::mensaje('Este envío ya no se puede mandar desde aquí.', 422);
        }

        if ($envio->destinatarios()->count() === 0) {
            return Respuesta::mensaje('Agrega al menos un destinatario antes de enviar.', 422);
        }

        return $this->mandar($envio);
    }

    public function programar(Request $request, CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return Respuesta::mensaje('Este envío ya no se puede programar.', 422);
        }

        $this->normalizarFecha($request);

        $data = $request->validate([
            'programado_para' => ['required', 'date_format:Y-m-d H:i', 'after:now'],
        ]);

        if ($envio->destinatarios()->count() === 0) {
            return Respuesta::mensaje('Agrega al menos un destinatario antes de programar.', 422);
        }

        $envio->update([
            'estado' => EstadoEnvioCorreo::Programado->value,
            'programado_para' => Carbon::createFromFormat('Y-m-d H:i', $data['programado_para']),
        ]);

        return Respuesta::recurso(Serializador::modelo($envio->fresh(['plantilla', 'creador', 'destinatarios'])));
    }

    public function cancelar(CorreoEnvio $envio): JsonResponse
    {
        if ($envio->estado !== EstadoEnvioCorreo::Programado) {
            return Respuesta::mensaje('Solo se cancela un envío programado.', 422);
        }

        $envio->update(['estado' => EstadoEnvioCorreo::Cancelado->value]);

        return Respuesta::recurso(Serializador::modelo($envio->fresh(['plantilla', 'creador', 'destinatarios'])));
    }

    /** Correo de prueba al usuario del token: no crea envío ni deja rastro. */
    public function prueba(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plantilla_id' => ['required', 'integer', 'exists:correo_plantillas,id'],
            'asunto' => ['required', 'string', 'max:255'],
            'variables' => ['nullable', 'array', 'max:30'],
            'variables.*' => ['nullable', 'string', 'max:2000'],
            'email' => ['required', 'email', 'max:255'],
            'remitente_nombre' => ['nullable', 'string', 'max:255'],
            'remitente_email' => ['nullable', 'email', 'max:255'],
            'personalizar' => ['nullable', 'boolean'],
            'html_personalizado' => ['nullable', 'string', 'max:2000000'],
        ] + Bloques::reglasPersonalizacion());

        $this->validarClavesVariables($data['variables'] ?? [], 'variables');

        $plantilla = CorreoPlantilla::findOrFail($data['plantilla_id']);
        $contenido = $this->contenidoDesdePayload($plantilla, $data);

        try {
            app(EnviadorCorreo::class)->prueba(
                $contenido,
                $data['asunto'],
                $this->limpiarVariables($data['variables'] ?? []),
                $data['email'],
                $data['remitente_nombre'] ?? null,
                $data['remitente_email'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return Respuesta::mensaje('No se pudo enviar la prueba: '.$e->getMessage(), 422);
        }

        return Respuesta::recurso(['enviado_a' => $data['email']]);
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $this->normalizarFecha($request);

        $acciones = ['borrador', 'enviar', 'programar'];
        $accion = (string) $request->input('accion', 'borrador');

        $data = $request->validate([
            'plantilla_id' => ['required', 'integer', 'exists:correo_plantillas,id'],
            'asunto' => ['required', 'string', 'max:255'],
            'remitente_nombre' => ['nullable', 'string', 'max:255'],
            'remitente_email' => ['nullable', 'email', 'max:255'],
            'variables' => ['nullable', 'array', 'max:30'],
            'variables.*' => ['nullable', 'string', 'max:2000'],
            'destinatarios' => $accion === 'borrador' ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'destinatarios.*.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'destinatarios.*.email' => ['required', 'email', 'max:255'],
            'destinatarios.*.nombre' => ['nullable', 'string', 'max:255'],
            'destinatarios.*.variables' => ['nullable', 'array'],
            'destinatarios.*.variables.*' => ['nullable', 'string', 'max:500'],
            'accion' => ['required', Rule::in($acciones)],
            'programado_para' => ['required_if:accion,programar', 'nullable', 'date_format:Y-m-d H:i', 'after:now'],
            'personalizar' => ['nullable', 'boolean'],
            'html_personalizado' => ['nullable', 'string', 'max:2000000'],
        ] + Bloques::reglasPersonalizacion(), [
            'destinatarios.required' => 'Agrega al menos un destinatario.',
            'destinatarios.min' => 'Agrega al menos un destinatario.',
            'programado_para.required_if' => 'Indica la fecha y hora del envío.',
            'programado_para.after' => 'La fecha programada debe ser posterior a ahora.',
        ]);

        $this->validarClavesVariables($data['variables'] ?? [], 'variables');

        foreach ($data['destinatarios'] ?? [] as $i => $d) {
            $this->validarClavesVariables($d['variables'] ?? [], "destinatarios.{$i}.variables", ['contacto', 'cliente']);
        }

        return $data;
    }

    /** Acepta 'Y-m-d H:i' y 'Y-m-d\TH:i' (ISO, típico de un payload JSON). */
    private function normalizarFecha(Request $request): void
    {
        $valor = $request->input('programado_para');
        if (is_string($valor) && $valor !== '') {
            $valor = str_replace('T', ' ', trim($valor));
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $valor)) {
                $valor = substr($valor, 0, 16);
            }
            $request->merge(['programado_para' => $valor]);
        } elseif ($valor === '') {
            $request->merge(['programado_para' => null]);
        }
    }

    /**
     * Sin `$permitidas` (variables del envío): catálogo O cualquier nombre
     * válido (personalizada). Con `$permitidas` (por destinatario): solo esa
     * lista exacta.
     */
    private function validarClavesVariables(array $variables, string $campo, ?array $permitidas = null): void
    {
        $desconocidas = array_filter(array_keys($variables), function ($clave) use ($permitidas) {
            if ($permitidas !== null) {
                return ! in_array($clave, $permitidas, true);
            }

            return ! in_array($clave, Variables::claves(), true) && ! Variables::nombreValido($clave);
        });

        if ($desconocidas) {
            throw ValidationException::withMessages([
                $campo => 'Variable inválida: '.implode(', ', $desconocidas).'. Usa solo minúsculas y guiones bajos.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function atributos(array $data): array
    {
        $personalizar = (bool) ($data['personalizar'] ?? false);

        return [
            'plantilla_id' => $data['plantilla_id'],
            'asunto' => $data['asunto'],
            'remitente_nombre' => $data['remitente_nombre'] ?? null,
            'remitente_email' => $data['remitente_email'] ?? null,
            'variables' => $this->limpiarVariables($data['variables'] ?? []),
            'bloques' => $personalizar ? ($data['bloques'] ?? []) : null,
            'marca' => $personalizar ? ($data['marca'] ?? []) : null,
            'html_personalizado' => $personalizar && trim((string) ($data['html_personalizado'] ?? '')) !== ''
                ? $data['html_personalizado']
                : null,
        ];
    }

    /**
     * Contenido con el que probar/renderizar antes de guardar (p. ej. una
     * prueba desde un envío que aún no existe): mismo criterio que
     * CorreoEnvio::contenidoEfectivo(), armado desde el payload en vez de
     * desde un registro guardado.
     *
     * @param  array<string, mixed>  $data
     * @return array{bloques: array, marca: array, html_libre: ?string}
     */
    private function contenidoDesdePayload(CorreoPlantilla $plantilla, array $data): array
    {
        if (! empty($data['personalizar'])) {
            return [
                'bloques' => $data['bloques'] ?? [],
                'marca' => array_replace(Bloques::marcaPorDefecto(), array_filter($data['marca'] ?? [], fn ($v) => $v !== null)),
                'html_libre' => trim((string) ($data['html_personalizado'] ?? '')) !== '' ? $data['html_personalizado'] : null,
            ];
        }

        return [
            'bloques' => $plantilla->bloques ?? [],
            'marca' => $plantilla->marcaCompleta(),
            'html_libre' => $plantilla->esHtmlLibre() ? $plantilla->html_personalizado : null,
        ];
    }

    /** @return array<string, string> */
    private function limpiarVariables(array $variables): array
    {
        $limpias = [];
        foreach ($variables as $clave => $valor) {
            $valor = trim((string) $valor);
            if ($valor !== '') {
                $limpias[$clave] = $valor;
            }
        }

        return $limpias;
    }

    /** Mismo comportamiento que Admin\CorreoEnviosController::sincronizarDestinatarios(). */
    private function sincronizarDestinatarios(CorreoEnvio $envio, array $filas): void
    {
        $porEmail = [];
        foreach ($filas as $fila) {
            $email = mb_strtolower(trim((string) $fila['email']));
            if ($email === '') {
                continue;
            }
            $candidata = [
                'cliente_id' => $fila['cliente_id'] ?? null,
                'nombre' => isset($fila['nombre']) && trim((string) $fila['nombre']) !== '' ? trim((string) $fila['nombre']) : null,
                'variables' => $this->limpiarVariables($fila['variables'] ?? []) ?: null,
            ];
            if (! isset($porEmail[$email])) {
                $porEmail[$email] = $candidata;
                continue;
            }
            foreach (['cliente_id', 'nombre', 'variables'] as $k) {
                if ($porEmail[$email][$k] === null && $candidata[$k] !== null) {
                    $porEmail[$email][$k] = $candidata[$k];
                }
            }
        }

        $existentes = $envio->destinatarios()->get()->keyBy(fn (CorreoDestinatario $d) => mb_strtolower($d->email));

        foreach ($porEmail as $email => $attrs) {
            if ($existentes->has($email)) {
                $existentes[$email]->update($attrs);
            } else {
                $envio->destinatarios()->create($attrs + ['email' => $email]);
            }
        }

        $sobrantes = $existentes->keys()->diff(array_keys($porEmail));
        if ($sobrantes->isNotEmpty()) {
            $envio->destinatarios()->whereIn('email', $sobrantes->all())->delete();
        }
    }

    /** @param  array<string, mixed>  $data */
    private function ejecutarAccion(CorreoEnvio $envio, array $data): JsonResponse
    {
        $accion = $data['accion'];

        if ($accion === 'programar') {
            $envio->update([
                'estado' => EstadoEnvioCorreo::Programado->value,
                'programado_para' => Carbon::createFromFormat('Y-m-d H:i', $data['programado_para']),
            ]);

            return Respuesta::recurso(Serializador::modelo($envio->fresh(['plantilla', 'creador', 'destinatarios'])), 201);
        }

        if ($accion === 'enviar') {
            return $this->mandar($envio);
        }

        return Respuesta::recurso(Serializador::modelo($envio->fresh(['plantilla', 'creador', 'destinatarios'])), 201);
    }

    /** Envío síncrono: la petición espera a que salgan todos los correos (ver aviso en routes/api/v1/correo.php). */
    private function mandar(CorreoEnvio $envio): JsonResponse
    {
        try {
            $envio = app(EnviadorCorreo::class)->enviar($envio);
        } catch (\RuntimeException $e) {
            return Respuesta::mensaje($e->getMessage(), 422);
        }

        $row = Serializador::modelo($envio->fresh(['plantilla', 'creador', 'destinatarios']));

        return Respuesta::recurso($row, $row['estado'] === EstadoEnvioCorreo::Fallido->value ? 422 : 200);
    }
}
