<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoClienteServicio;
use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\CorreoAdjunto;
use App\Models\CorreoDestinatario;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Services\Correo\EnviadorCorreo;
use App\Support\Correo\Bloques;
use App\Support\Correo\RenderizadorCorreo;
use App\Support\Correo\Variables;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Enviar correo: redactar un envío a partir de una plantilla, mandarlo ahora
 * o programarlo, y consultar el historial con su medición.
 *
 * El envío real lo hace App\Services\Correo\EnviadorCorreo (síncrono, en la
 * misma petición); aquí solo se valida el payload, se sincronizan los
 * destinatarios y se decide el estado según `accion`.
 */
class CorreoEnviosController extends Controller
{
    public function index(Request $request): View
    {
        $query = CorreoEnvio::with(['plantilla', 'creador', 'destinatarios'])->orderByDesc('updated_at');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('search')) {
            $buscar = trim((string) $request->string('search'));
            $query->where(function ($q) use ($buscar) {
                $q->where('asunto', 'like', "%{$buscar}%")
                    ->orWhereHas('plantilla', fn ($p) => $p->where('nombre', 'like', "%{$buscar}%"))
                    ->orWhereHas('destinatarios', function ($d) use ($buscar) {
                        $d->where('email', 'like', "%{$buscar}%")->orWhere('nombre', 'like', "%{$buscar}%");
                    });
            });
        }

        $envios = $query->get();

        // Los KPIs no dependen del filtro de estado/búsqueda del listado (son el
        // pulso del módulo, no del listado filtrado), pero sí del periodo elegido.
        // Un periodo desconocido cae a "mes" para que un enlace viejo no rompa.
        $periodos = ['mes' => 'Este mes', '30d' => 'Últimos 30 días', 'todo' => 'Todo el historial'];
        $periodo = (string) $request->string('periodo', 'mes');
        if (! array_key_exists($periodo, $periodos)) {
            $periodo = 'mes';
        }

        $desde = match ($periodo) {
            'mes' => now()->startOfMonth(),
            '30d' => now()->subDays(30),
            'todo' => null,
        };

        $enviosPeriodo = CorreoEnvio::where('estado', EstadoEnvioCorreo::Enviado)
            ->when($desde, fn ($q) => $q->where('enviado_en', '>=', $desde));

        // Agregado en SQL: con "todo el historial" cargar las colecciones en
        // memoria escalaría mal. Solo cuentan los destinatarios que sí salieron:
        // los fallidos nunca pudieron abrirse (mismo criterio que toRow()).
        $totales = CorreoDestinatario::whereIn('envio_id', (clone $enviosPeriodo)->select('id'))
            ->where('estado', EstadoDestinatarioCorreo::Enviado)
            ->selectRaw('COUNT(*) AS alcanzados, COALESCE(SUM(aperturas > 0), 0) AS abiertos, COALESCE(SUM(clics), 0) AS clics')
            ->first();

        $alcanzados = (int) ($totales->alcanzados ?? 0);
        $abiertos = (int) ($totales->abiertos ?? 0);

        return view('admin.correo.envios.index', [
            'pageTitle' => 'Enviar correo',
            'envios' => $envios->map(fn (CorreoEnvio $e) => $e->toRow())->values(),
            'filtros' => [
                'estado' => (string) $request->string('estado'),
                'search' => (string) $request->string('search'),
            ],
            'periodos' => $periodos,
            'kpis' => [
                'periodo' => $periodo,
                'enviados' => (clone $enviosPeriodo)->count(),
                'alcanzados' => $alcanzados,
                'abiertos' => $abiertos,
                'no_abiertos' => max($alcanzados - $abiertos, 0),
                'apertura' => $alcanzados > 0 ? (int) round($abiertos / $alcanzados * 100) : null,
                'clics' => (int) ($totales->clics ?? 0),
                'programados' => CorreoEnvio::where('estado', EstadoEnvioCorreo::Programado)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.correo.envios.redactar', $this->datosRedactar(null));
    }

    /** Un envío ya mandado o cancelado no se edita: se consulta en show. */
    public function edit(CorreoEnvio $envio): View|RedirectResponse
    {
        if (! $envio->estado->editable()) {
            return redirect()->route('admin.correo.envios.show', $envio);
        }

        return view('admin.correo.envios.redactar', $this->datosRedactar($envio->load('destinatarios')));
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
            return response()->json(['message' => 'Este envío ya no se puede editar.'], 422);
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($envio, $data) {
            // Vuelve a borrador: si era programado y se guarda como borrador o
            // se manda ahora, la programación anterior deja de valer.
            $envio->update($this->atributos($data) + [
                'estado' => EstadoEnvioCorreo::Borrador->value,
                'programado_para' => null,
            ]);
            $this->sincronizarDestinatarios($envio, $data['destinatarios'] ?? []);
        });

        return $this->ejecutarAccion($envio->fresh(), $data);
    }

    public function show(CorreoEnvio $envio): View
    {
        $envio->load(['plantilla', 'creador', 'destinatarios.cliente', 'destinatarios.eventos', 'adjuntos']);

        $destinatarios = $envio->destinatarios->map(function (CorreoDestinatario $d) {
            return $d->toRow() + [
                'eventos' => $d->eventos->take(5)->map(fn ($ev) => [
                    'tipo' => $ev->tipo,
                    'url' => $ev->url,
                    'fecha' => $ev->created_at?->format('Y-m-d H:i'),
                ])->values()->all(),
            ];
        })->values();

        // Antes de enviar no hay HTML congelado: se enseña la plantilla (o la
        // personalización propia del envío) con las variables del envío (las
        // de persona quedan vacías, es una previa).
        $html = $envio->html_congelado;
        if ($html === null) {
            $contenido = $envio->contenidoEfectivo();
            $html = RenderizadorCorreo::render($contenido['bloques'], $contenido['marca'], $envio->variables ?? [], ['html_libre' => $contenido['html_libre']]);
        }

        return view('admin.correo.envios.show', [
            'pageTitle' => $envio->asunto,
            'envio' => $envio,
            'row' => $envio->toRow(),
            'destinatarios' => $destinatarios,
            'adjuntos' => $envio->adjuntos,
            'html' => $html,
            'variables' => $envio->variables ?? [],
            'catalogo' => Variables::catalogo(),
        ]);
    }

    public function enviar(CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return response()->json(['message' => 'Este envío ya no se puede mandar desde aquí.'], 422);
        }

        if ($envio->destinatarios()->count() === 0) {
            return response()->json(['message' => 'Agrega al menos un destinatario antes de enviar.'], 422);
        }

        return $this->mandar($envio);
    }

    public function programar(Request $request, CorreoEnvio $envio): JsonResponse
    {
        if (! $envio->estado->editable()) {
            return response()->json(['message' => 'Este envío ya no se puede programar.'], 422);
        }

        $this->normalizarFecha($request);

        $data = $request->validate([
            'programado_para' => ['required', 'date_format:Y-m-d H:i', 'after:now'],
        ]);

        if ($envio->destinatarios()->count() === 0) {
            return response()->json(['message' => 'Agrega al menos un destinatario antes de programar.'], 422);
        }

        $envio->update([
            'estado' => EstadoEnvioCorreo::Programado->value,
            'programado_para' => Carbon::createFromFormat('Y-m-d H:i', $data['programado_para']),
        ]);

        return response()->json([
            'ok' => true,
            'row' => $envio->fresh(['plantilla', 'creador', 'destinatarios'])->toRow(),
            'mensaje' => 'Envío programado para el '.$envio->programado_para->format('d/m/Y H:i').'.',
        ]);
    }

    public function cancelar(CorreoEnvio $envio): JsonResponse
    {
        if ($envio->estado !== EstadoEnvioCorreo::Programado) {
            return response()->json(['message' => 'Solo se cancela un envío programado.'], 422);
        }

        $envio->update(['estado' => EstadoEnvioCorreo::Cancelado->value]);

        return response()->json([
            'ok' => true,
            'row' => $envio->fresh(['plantilla', 'creador', 'destinatarios'])->toRow(),
            'mensaje' => 'Programación cancelada.',
        ]);
    }

    /** Lo enviado no se borra: es historial de lo que recibió el cliente. */
    public function destroy(CorreoEnvio $envio): JsonResponse
    {
        $borrables = [EstadoEnvioCorreo::Borrador, EstadoEnvioCorreo::Cancelado, EstadoEnvioCorreo::Fallido];

        if (! in_array($envio->estado, $borrables, true)) {
            return response()->json(['message' => 'Solo se eliminan borradores, cancelados o fallidos.'], 422);
        }

        $envio->delete();

        return response()->json(['ok' => true, 'deleted' => true]);
    }

    /** Correo de prueba al usuario en sesión: no crea envío ni deja rastro. */
    public function prueba(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plantilla_id' => ['required', 'integer', 'exists:correo_plantillas,id'],
            'asunto' => ['required', 'string', 'max:255'],
            'variables' => ['nullable', 'array', 'max:30'],
            'variables.*' => ['nullable', 'string', 'max:2000'],
            'remitente_nombre' => ['nullable', 'string', 'max:255'],
            'remitente_email' => ['nullable', 'email', 'max:255'],
            'personalizar' => ['nullable', 'boolean'],
            'html_personalizado' => ['nullable', 'string', 'max:2000000'],
        ] + Bloques::reglasPersonalizacion());

        $this->validarClavesVariables($data['variables'] ?? [], 'variables');

        $email = (string) Auth::user()?->email;
        if ($email === '') {
            return response()->json(['message' => 'Tu usuario no tiene correo al que mandar la prueba.'], 422);
        }

        $plantilla = CorreoPlantilla::findOrFail($data['plantilla_id']);
        $personalizar = (bool) ($data['personalizar'] ?? false);
        $contenido = $personalizar
            ? [
                'bloques' => $data['bloques'] ?? [],
                'marca' => array_replace(Bloques::marcaPorDefecto(), array_filter($data['marca'] ?? [], fn ($v) => $v !== null)),
                'html_libre' => trim((string) ($data['html_personalizado'] ?? '')) !== '' ? $data['html_personalizado'] : null,
            ]
            : [
                'bloques' => $plantilla->bloques ?? [],
                'marca' => $plantilla->marcaCompleta(),
                'html_libre' => $plantilla->esHtmlLibre() ? $plantilla->html_personalizado : null,
            ];

        try {
            app(EnviadorCorreo::class)->prueba(
                $contenido,
                $data['asunto'],
                $this->limpiarVariables($data['variables'] ?? []),
                $email,
                $data['remitente_nombre'] ?? null,
                $data['remitente_email'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo enviar la prueba: '.$e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'mensaje' => "Prueba enviada a {$email}."]);
    }

    /**
     * Prueba de un envío ya guardado, desde su detalle.
     *
     * La prueba del redactor (prueba()) nace de lo que hay en el formulario y
     * por eso no puede llevar adjuntos: estos cuelgan del envío y solo existen
     * cuando ya se guardó. Aquí sí, así que esta es la única forma de confirmar
     * que el PDF sale y con qué nombre ANTES de mandarlo al cliente.
     *
     * Va al correo del usuario autenticado y no toca nada del envío: ni el
     * estado, ni los destinatarios, ni el HTML congelado, ni las métricas de
     * apertura (la prueba no lleva píxel ni enlaces firmados).
     */
    public function pruebaEnvio(CorreoEnvio $envio): JsonResponse
    {
        $envio->loadMissing(['plantilla', 'adjuntos']);

        if (! $envio->personalizado() && ! $envio->plantilla instanceof CorreoPlantilla) {
            return response()->json(['message' => 'Este envío se quedó sin plantilla; edítalo antes de probarlo.'], 422);
        }

        $email = (string) Auth::user()?->email;
        if ($email === '') {
            return response()->json(['message' => 'Tu usuario no tiene correo al que mandar la prueba.'], 422);
        }

        $adjuntos = $envio->adjuntos
            ->map(fn (CorreoAdjunto $a) => ['disco' => $a->disco, 'ruta' => $a->ruta, 'nombre' => $a->nombre])
            ->all();

        try {
            app(EnviadorCorreo::class)->prueba(
                $envio->contenidoEfectivo(),
                $envio->asunto,
                $this->limpiarVariables($envio->variables ?? []),
                $email,
                $envio->remitente_nombre,
                $envio->remitente_email,
                $adjuntos,
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo enviar la prueba: '.$e->getMessage()], 422);
        }

        $n = count($adjuntos);
        $conAdjuntos = $n === 0
            ? ' El envío no lleva adjuntos.'
            : ($n === 1 ? ' Lleva 1 adjunto.' : " Lleva {$n} adjuntos.");

        return response()->json([
            'ok' => true,
            'adjuntos' => $n,
            'mensaje' => "Prueba enviada a {$email}.".$conAdjuntos,
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Datos compartidos por create y edit. Las plantillas viajan con sus
     * bloques/marca para que la previa se pinte en el navegador sin pedirlos
     * al servidor cada vez que se cambia de plantilla.
     *
     * @return array<string, mixed>
     */
    private function datosRedactar(?CorreoEnvio $envio): array
    {
        $catalogo = Variables::catalogo();
        $bloques = Bloques::catalogo();

        $plantillas = CorreoPlantilla::where('estado', 'activa')
            ->orderBy('nombre')
            ->get()
            ->map(function (CorreoPlantilla $p) use ($catalogo) {
                $usadas = Variables::usadasEn(json_encode($p->bloques ?? []).($p->html_personalizado ?? '').$p->asunto);
                $usadas = array_values(array_intersect($usadas, array_keys($catalogo)));

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'categoria' => $p->categoria->value,
                    'categoria_label' => $p->categoria->label(),
                    'asunto' => $p->asunto,
                    'variables' => $usadas,
                    'variables_envio' => array_values(array_filter($usadas, fn ($k) => $catalogo[$k]['ambito'] === 'envio')),
                    'variables_persona' => array_values(array_filter($usadas, fn ($k) => $catalogo[$k]['ambito'] === 'persona')),
                    'bloques' => $p->bloques ?? [],
                    'marca' => $p->marcaCompleta(),
                    'html_personalizado' => $p->esHtmlLibre() ? $p->html_personalizado : null,
                    'html_libre' => $p->esHtmlLibre(),
                ];
            })
            ->values();

        $clientes = Cliente::where('estado', EstadoClienteServicio::Activo)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('empresa')
            ->orderBy('nombre')
            ->get(['id', 'empresa', 'nombre', 'contacto_nombre', 'email'])
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'empresa' => $c->empresa,
                'nombre' => $c->nombre,
                'contacto_nombre' => $c->contacto_nombre,
                'email' => $c->email,
            ])
            ->values();

        $envioJson = null;
        if ($envio) {
            $envioJson = [
                'id' => $envio->id,
                'plantilla_id' => $envio->plantilla_id,
                'asunto' => $envio->asunto,
                'remitente_nombre' => $envio->remitente_nombre,
                'remitente_email' => $envio->remitente_email,
                'estado' => $envio->estado->value,
                'variables' => $envio->variables ?? [],
                'personalizado' => $envio->personalizado(),
                'bloques' => $envio->bloques,
                'marca' => $envio->marca,
                'html_personalizado' => $envio->html_personalizado,
                'programado_para' => $envio->programado_para?->format('Y-m-d\TH:i'),
                'destinatarios' => $envio->destinatarios->map(fn (CorreoDestinatario $d) => [
                    'cliente_id' => $d->cliente_id,
                    'email' => $d->email,
                    'nombre' => $d->nombre,
                    'variables' => $d->variables ?? [],
                ])->values()->all(),
                'update_url' => route('admin.correo.envios.update', $envio),
                'show_url' => route('admin.correo.envios.show', $envio),
            ];
        }

        return [
            'pageTitle' => $envio ? 'Editar envío' : 'Redactar correo',
            'envio' => $envio,
            'datos' => [
                'plantillas' => $plantillas,
                'clientes' => $clientes,
                'catalogo' => $catalogo,
                // Para el editor de contenido personalizado (mismo motor que Plantillas).
                'catalogoBloques' => $bloques,
                'tiposBloque' => array_keys($bloques),
                'colores' => Bloques::COLORES,
                'logos' => Bloques::LOGOS,
                'remitente' => [
                    'nombre' => (string) config('mail.from.name'),
                    'email' => (string) config('mail.from.address'),
                ],
                'usuario_email' => (string) Auth::user()?->email,
                'envio' => $envioJson,
                'urls' => [
                    'store' => route('admin.correo.envios.store'),
                    'prueba' => route('admin.correo.envios.prueba'),
                    'preview' => route('admin.correo.plantillas.preview'),
                    'index' => route('admin.correo.envios.index'),
                    'plantillas' => route('admin.correo.plantillas.index'),
                ],
            ],
        ];
    }

    /**
     * Payload de store/update (ver "Contrato del payload de envíos").
     *
     * @return array<string, mixed>
     */
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
            // Un borrador puede guardarse sin nadie; enviar o programar, no.
            'destinatarios' => $accion === 'borrador' ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'destinatarios.*.cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'destinatarios.*.email' => ['required', 'email', 'max:255'],
            'destinatarios.*.nombre' => ['nullable', 'string', 'max:255'],
            'destinatarios.*.variables' => ['nullable', 'array'],
            'destinatarios.*.variables.*' => ['nullable', 'string', 'max:500'],
            'accion' => ['required', Rule::in($acciones)],
            'programado_para' => ['required_if:accion,programar', 'nullable', 'date_format:Y-m-d H:i', 'after:now'],
            // Contenido propio del envío (opcional): ver Bloques::reglasPersonalizacion().
            'personalizar' => ['nullable', 'boolean'],
            'html_personalizado' => ['nullable', 'string', 'max:2000000'],
        ] + Bloques::reglasPersonalizacion(), [
            'destinatarios.required' => 'Agrega al menos un destinatario.',
            'destinatarios.min' => 'Agrega al menos un destinatario.',
            'destinatarios.*.email.required' => 'Falta el correo de un destinatario.',
            'destinatarios.*.email.email' => 'Hay un correo de destinatario que no es válido.',
            'programado_para.required_if' => 'Indica la fecha y hora del envío.',
            'programado_para.after' => 'La fecha programada debe ser posterior a ahora.',
        ]);

        $this->validarClavesVariables($data['variables'] ?? [], 'variables');

        foreach ($data['destinatarios'] ?? [] as $i => $d) {
            $this->validarClavesVariables($d['variables'] ?? [], "destinatarios.{$i}.variables", ['contacto', 'cliente']);
        }

        return $data;
    }

    /**
     * El datetime-local del navegador manda 'Y-m-d\TH:i'; la regla y la BD
     * trabajan con 'Y-m-d H:i'. Se normaliza antes de validar para aceptar
     * ambas formas sin que el JS tenga que saberlo.
     */
    private function normalizarFecha(Request $request): void
    {
        $valor = $request->input('programado_para');
        if (is_string($valor) && $valor !== '') {
            $valor = str_replace('T', ' ', trim($valor));
            // Con segundos ('H:i:s') se recortan: el minuto es la unidad del scheduler.
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $valor)) {
                $valor = substr($valor, 0, 16);
            }
            $request->merge(['programado_para' => $valor]);
        } elseif ($valor === '') {
            $request->merge(['programado_para' => null]);
        }
    }

    /**
     * Las claves de un array no las cubre `variables.*` (eso valida valores),
     * así que se contrastan aparte.
     *
     * Sin `$permitidas` (las variables del envío, campo 4 del redactor):
     * acepta el catálogo O cualquier nombre válido, para poder declarar
     * variables personalizadas. Con `$permitidas` (variables por
     * destinatario): solo esa lista exacta, como hasta ahora.
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

    /**
     * Columnas del envío que salen tal cual del payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function atributos(array $data): array
    {
        // "personalizar" en false (o ausente) borra cualquier personalización
        // previa: el envío vuelve a usar la plantilla tal cual.
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
     * Quita las variables vacías: el renderizador deja en blanco lo que falte,
     * y así el envío no arrastra claves sin valor.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
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

    /**
     * Deja los destinatarios del envío exactamente como llegan: colapsa
     * correos repetidos (unique envio_id+email, sin distinguir mayúsculas),
     * conserva las filas que ya existían (y con ellas su token, que puede
     * estar impreso en un píxel si el envío se reprograma) y borra las que
     * ya no vienen.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function sincronizarDestinatarios(CorreoEnvio $envio, array $filas): void
    {
        $porEmail = [];
        foreach ($filas as $fila) {
            $email = mb_strtolower(trim((string) $fila['email']));
            if ($email === '') {
                continue;
            }
            // Si el mismo correo viene dos veces, gana la fila con más datos
            // (cliente vinculado o nombre) para no perder la vinculación.
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

    /**
     * Cierra store/update según `accion`. El envío ya está guardado como
     * borrador con sus destinatarios; aquí solo cambia de estado o se manda.
     *
     * @param  array<string, mixed>  $data
     */
    private function ejecutarAccion(CorreoEnvio $envio, array $data): JsonResponse
    {
        $accion = $data['accion'];

        if ($accion === 'programar') {
            $envio->update([
                'estado' => EstadoEnvioCorreo::Programado->value,
                'programado_para' => Carbon::createFromFormat('Y-m-d H:i', $data['programado_para']),
            ]);

            return $this->respuestaEnvio($envio, 'Envío programado para el '.$envio->programado_para->format('d/m/Y H:i').'.');
        }

        if ($accion === 'enviar') {
            return $this->mandar($envio);
        }

        return $this->respuestaEnvio($envio, 'Borrador guardado.');
    }

    /** Envío síncrono: la petición espera a que salgan todos los correos. */
    private function mandar(CorreoEnvio $envio): JsonResponse
    {
        try {
            $envio = app(EnviadorCorreo::class)->enviar($envio);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $row = $envio->fresh(['plantilla', 'creador', 'destinatarios'])->toRow();
        $mensaje = $row['estado'] === EstadoEnvioCorreo::Fallido->value
            ? 'Ningún correo pudo salir. Revisa los errores por destinatario.'
            : "Correo enviado a {$row['enviados']} de {$row['destinatarios']} destinatarios.";

        return response()->json(['ok' => $row['estado'] !== EstadoEnvioCorreo::Fallido->value, 'row' => $row, 'show_url' => $row['show_url'], 'mensaje' => $mensaje]);
    }

    private function respuestaEnvio(CorreoEnvio $envio, string $mensaje): JsonResponse
    {
        $row = $envio->fresh(['plantilla', 'creador', 'destinatarios'])->toRow();

        return response()->json(['ok' => true, 'row' => $row, 'show_url' => $row['show_url'], 'mensaje' => $mensaje]);
    }
}
