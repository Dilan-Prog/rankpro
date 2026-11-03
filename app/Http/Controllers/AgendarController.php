<?php

namespace App\Http\Controllers;

use App\Enums\EstadoReunion;
use App\Mail\ReunionConfirmadaMail;
use App\Mail\ReunionNuevaMail;
use App\Models\AgendaConfiguracion;
use App\Models\Cliente;
use App\Models\Reunion;
use App\Support\Agenda\CalculadorDisponibilidad;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Página pública /agendar: el prospecto/cliente elige fecha y hora dentro de
 * la disponibilidad que configura el admin (módulo Admin\AgendaController) y
 * agenda sin sesión. Nada de esto pasa por RenderizadorCorreo: los correos de
 * confirmación/aviso son Mailables fijos con vista Blade propia.
 */
class AgendarController extends Controller
{
    private const MENSAJE_CONFIRMADA = 'Reunión confirmada. Te llegó un correo con los detalles.';

    public function mostrar(): View
    {
        return view('agendar.mostrar', [
            'config' => AgendaConfiguracion::actual(),
        ]);
    }

    public function disponibilidad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $horarios = app(CalculadorDisponibilidad::class)
            ->disponiblesEn(CarbonImmutable::parse($data['fecha']));

        return response()->json([
            'horarios' => array_map(fn ($hora) => $hora->format('H:i'), $horarios),
        ]);
    }

    /**
     * Idempotente ante doble clic, reintento del navegador o reenvío del formulario:
     *  1. `clave_idempotencia` (UUID que el servidor pone en el formulario): si ya hay una
     *     reunión con esa clave se responde como la primera vez, sin crear ni enviar correos.
     *     Sin clave se comporta como antes (compatibilidad con clientes viejos); con una clave
     *     mal formada se rechaza con 422 en vez de ignorarla en silencio.
     *  2. Regla natural: mismo correo (en minúsculas) + mismo inicio con una reunión no
     *     cancelada devuelve esa reunión, no un "horario ocupado" ni una segunda reserva.
     *  3. Ante dos peticiones simultáneas con la misma clave manda el índice ÚNICO de BD.
     * Los correos salen después del commit y solo si ESTA petición creó la reunión.
     * La clave no es un secreto ni sustituye al CSRF.
     *
     * Honeypot: `sitio_alterno` está oculto para las personas; si viene lleno es un bot y se
     * responde un éxito falso (sin crear nada ni mandar correos) para no darle pistas.
     * El enlace de cancelación solo se devuelve a quien presenta la clave de la reserva:
     * con la regla natural (correo + horario) cualquiera que conozca el correo de otra
     * persona obtendría el token y podría cancelarle la reunión.
     */
    public function agendar(Request $request): JsonResponse
    {
        if (filled($request->input('sitio_alterno'))) {
            return response()->json(['ok' => true, 'mensaje' => self::MENSAJE_CONFIRMADA]);
        }

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            // Mismo criterio que agendar.js: al menos 8 dígitos (acepta +, espacios y guiones).
            'telefono' => ['required', 'string', 'max:30', function ($atributo, $valor, $fallar) {
                if (preg_match_all('/\d/', (string) $valor) < 8) {
                    $fallar('Escribe un número de WhatsApp válido.');
                }
            }],
            'empresa' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'inicia_en' => ['required', 'date_format:Y-m-d H:i'],
            'clave_idempotencia' => ['nullable', 'uuid'],
        ]);

        $clave = ($data['clave_idempotencia'] ?? null) ?: null;
        $email = mb_strtolower(trim($data['email']));

        // Antes de comprobar disponibilidad: el horario ya lo ocupa la propia reunión
        // del reintento y daría un falso "ya no está disponible".
        [$existente, $porClave] = $this->reunionExistente($clave, $email, $data['inicia_en']);
        if ($existente) {
            return $this->respuestaDeReunion($existente, $porClave);
        }

        $config = AgendaConfiguracion::actual();
        if (! $config || ! $config->activa) {
            return response()->json(['message' => 'La agenda no está disponible por ahora.'], 422);
        }

        try {
            $reunion = DB::transaction(function () use ($data, $config, $clave) {
                // Bloqueo de la fila de configuración (siempre existe aquí): serializa las
                // reservas para que dos prospectos distintos no tomen el mismo hueco a la
                // vez. Se bloquea una fila fija y no un rango de reuniones porque, sin
                // reuniones previas, el bloqueo de rango no impide el INSERT del otro.
                AgendaConfiguracion::whereKey($config->getKey())->lockForUpdate()->first();

                $inicio = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['inicia_en'])->toMutable();
                $fin = $inicio->copy()->addMinutes($config->duracion_minutos);

                if (! app(CalculadorDisponibilidad::class)->estaLibre($inicio, $fin)) {
                    return null;
                }

                $cliente = Cliente::where('email', $data['email'])->first();

                return Reunion::create([
                    'cliente_id' => $cliente?->id,
                    'nombre' => $data['nombre'],
                    'email' => $data['email'],
                    'telefono' => $data['telefono'],
                    'empresa' => $data['empresa'] ?? null,
                    'notas' => $data['notas'] ?? null,
                    'inicia_en' => $inicio,
                    'termina_en' => $fin,
                    'estado' => EstadoReunion::Confirmada,
                    'clave_idempotencia' => $clave,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Carrera: otra petición con la misma clave ganó el INSERT.
            $ganadora = $clave ? Reunion::where('clave_idempotencia', $clave)->first() : null;
            if (! $ganadora) {
                throw $e;
            }

            return $this->respuestaDeReunion($ganadora, true);
        }

        if (! $reunion) {
            return response()->json(['message' => 'Ese horario ya no está disponible, elige otro.'], 422);
        }

        $this->enviarCorreos($reunion, $config);

        return $this->respuestaDeReunion($reunion, true);
    }

    /** @return array{0: ?Reunion, 1: bool} la reunión y si se halló por su clave de idempotencia */
    private function reunionExistente(?string $clave, string $email, string $iniciaEn): array
    {
        if ($clave) {
            $porClave = Reunion::where('clave_idempotencia', $clave)->first();
            if ($porClave) {
                return [$porClave, true];
            }
        }

        $natural = Reunion::whereRaw('LOWER(email) = ?', [$email])
            ->where('inicia_en', CarbonImmutable::createFromFormat('Y-m-d H:i', $iniciaEn)->startOfMinute())
            ->where('estado', '!=', EstadoReunion::Cancelada->value)
            ->first();

        return [$natural, false];
    }

    /** $conClave: la petición presentó la clave de la reserva, única prueba de que es quien la hizo. */
    private function respuestaDeReunion(Reunion $reunion, bool $conClave): JsonResponse
    {
        // Reintento con la clave de una reunión que ya se canceló: no se resucita.
        if ($reunion->estado === EstadoReunion::Cancelada) {
            return response()->json(['message' => 'Esa reunión ya fue cancelada, agenda una nueva.'], 422);
        }

        $respuesta = ['ok' => true, 'mensaje' => self::MENSAJE_CONFIRMADA];
        if ($conClave) {
            $respuesta['cancelar_url'] = route('agendar.cancelar', $reunion->token);
        }

        return response()->json($respuesta);
    }

    private function enviarCorreos(Reunion $reunion, AgendaConfiguracion $config): void
    {
        try {
            Mail::to($reunion->email)->send(new ReunionConfirmadaMail($reunion));

            $destinatario = $config->notificar_email ?: config('mail.from.address');
            if ($destinatario) {
                Mail::to($destinatario)->send(new ReunionNuevaMail($reunion));
            }
        } catch (\Throwable $e) {
            // La reunión ya se guardó: un correo caído no debe revertirla
            // ni tumbar la respuesta al prospecto.
            report($e);
        }
    }

    public function cancelar(string $token): View|RedirectResponse
    {
        $reunion = Reunion::where('token', $token)->first();

        if (! $reunion) {
            return redirect()->route('agendar.mostrar')->with('status', 'Ese enlace ya no es válido.');
        }

        return view('agendar.cancelar', ['reunion' => $reunion]);
    }

    public function confirmarCancelacion(string $token): RedirectResponse
    {
        $reunion = Reunion::where('token', $token)->first();

        if (! $reunion) {
            return redirect()->route('agendar.mostrar')->with('status', 'Ese enlace ya no es válido.');
        }

        // Idempotente: bajo bloqueo se relee el estado, así dos cancelaciones
        // simultáneas o un reenvío no vuelven a tocar la reunión (updated_at
        // es la única huella de cuándo se canceló).
        DB::transaction(function () use ($reunion) {
            $actual = Reunion::whereKey($reunion->id)->lockForUpdate()->first();
            if ($actual && $actual->estado !== EstadoReunion::Cancelada) {
                $actual->update(['estado' => EstadoReunion::Cancelada]);
            }
        });

        return redirect()->route('agendar.cancelar', $token)->with('status', 'Reunión cancelada.');
    }
}
