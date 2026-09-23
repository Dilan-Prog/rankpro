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

    public function agendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'inicia_en' => ['required', 'date_format:Y-m-d H:i'],
        ]);

        $config = AgendaConfiguracion::actual();
        if (! $config || ! $config->activa) {
            return response()->json(['message' => 'La agenda no está disponible por ahora.'], 422);
        }

        return DB::transaction(function () use ($data, $config) {
            $inicio = CarbonImmutable::createFromFormat('Y-m-d H:i', $data['inicia_en'])->toMutable();
            $fin = $inicio->copy()->addMinutes($config->duracion_minutos);

            if (! app(CalculadorDisponibilidad::class)->estaLibre($inicio, $fin)) {
                return response()->json(['message' => 'Ese horario ya no está disponible, elige otro.'], 422);
            }

            $cliente = Cliente::where('email', $data['email'])->first();

            $reunion = Reunion::create([
                'cliente_id' => $cliente?->id,
                'nombre' => $data['nombre'],
                'email' => $data['email'],
                'telefono' => $data['telefono'] ?? null,
                'notas' => $data['notas'] ?? null,
                'inicia_en' => $inicio,
                'termina_en' => $fin,
                'estado' => EstadoReunion::Confirmada,
            ]);

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

            return response()->json([
                'ok' => true,
                'mensaje' => 'Reunión confirmada. Te llegó un correo con los detalles.',
                'cancelar_url' => route('agendar.cancelar', $reunion->token),
            ]);
        });
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

        if ($reunion->estado !== EstadoReunion::Cancelada) {
            $reunion->update(['estado' => EstadoReunion::Cancelada]);
        }

        return redirect()->route('agendar.cancelar', $token)->with('status', 'Reunión cancelada.');
    }
}
