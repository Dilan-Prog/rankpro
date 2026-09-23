<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoReunion;
use App\Http\Controllers\Controller;
use App\Models\AgendaConfiguracion;
use App\Models\Cliente;
use App\Models\DisponibilidadBloqueo;
use App\Models\DisponibilidadHorario;
use App\Models\Reunion;
use App\Support\Agenda\CalculadorDisponibilidad;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel de administración de la agenda pública: la configuración general
 * (AgendaConfiguracion), el horario semanal (DisponibilidadHorario), los
 * bloqueos puntuales (DisponibilidadBloqueo) y las reuniones agendadas.
 */
class AgendaController extends Controller
{
    public function index(): View
    {
        $horariosPorDia = DisponibilidadHorario::query()->get()->keyBy('dia_semana');

        // La vista pinta las 7 filas siempre (0=domingo..6=sábado), aunque
        // nunca se haya guardado horario para algún día: arrays planos, no
        // el modelo, para que la vista no tenga que lidiar con null.
        $horarios = [];
        for ($dia = 0; $dia <= 6; $dia++) {
            $horario = $horariosPorDia->get($dia);
            $horarios[$dia] = [
                'dia_semana' => $dia,
                'hora_inicio' => $horario?->hora_inicio,
                'hora_fin' => $horario?->hora_fin,
                'activo' => (bool) $horario?->activo,
            ];
        }

        return view('admin.agenda.index', [
            'pageTitle' => 'Agenda',
            'config' => AgendaConfiguracion::actual(),
            'horarios' => $horarios,
            'bloqueos' => DisponibilidadBloqueo::orderBy('fecha')->get(),
            // Historial + próximas, no solo futuras confirmadas: la pestaña
            // "Citas" necesita poder filtrar por cualquier estado y ver lo
            // que ya pasó (completada/no_show/cancelada), no solo lo próximo.
            'reuniones' => Reunion::query()
                ->where('inicia_en', '>=', now()->subDays(90))
                ->orderByDesc('inicia_en')
                ->with('cliente')
                ->limit(300)
                ->get(),
        ]);
    }

    /** Cita creada a mano desde el panel (llamada telefónica, walk-in, etc.): misma validación de disponibilidad que la pública. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validarDatosReunion($request);

        $config = AgendaConfiguracion::actual();
        if (! $config) {
            return redirect()->route('admin.agenda.index')->with('status', 'Configura la agenda antes de crear una cita.');
        }

        $inicio = Carbon::createFromFormat('Y-m-d H:i', $data['inicia_en']);
        $fin = $inicio->copy()->addMinutes($config->duracion_minutos);

        if (! app(CalculadorDisponibilidad::class)->estaLibre($inicio, $fin)) {
            return back()->withInput()->withErrors(['inicia_en' => 'Ese horario ya no está disponible.']);
        }

        $cliente = Cliente::where('email', $data['email'])->first();

        Reunion::create([
            'cliente_id' => $cliente?->id,
            'nombre' => $data['nombre'],
            'email' => $data['email'],
            'telefono' => $data['telefono'] ?? null,
            'notas' => $data['notas'] ?? null,
            'inicia_en' => $inicio,
            'termina_en' => $fin,
            'estado' => EstadoReunion::Confirmada,
        ]);

        return redirect()->route('admin.agenda.index')->with('status', 'Cita creada.');
    }

    /** Mueve una reunión existente a otro horario, validando disponibilidad (sin chocar contra su propio hueco actual). */
    public function reagendar(Request $request, Reunion $reunion): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
        ]);

        $config = AgendaConfiguracion::actual();
        if (! $config) {
            return redirect()->route('admin.agenda.index')->with('status', 'Configura la agenda antes de reagendar.');
        }

        $inicio = Carbon::parse("{$data['fecha']} {$data['hora']}");
        $fin = $inicio->copy()->addMinutes($config->duracion_minutos);

        if (! app(CalculadorDisponibilidad::class)->estaLibre($inicio, $fin, $reunion->id)) {
            return back()->withErrors(['reagendar' => 'Ese horario ya no está disponible.']);
        }

        $reunion->update(['inicia_en' => $inicio, 'termina_en' => $fin]);

        return redirect()->route('admin.agenda.index')->with('status', 'Reunión reagendada.');
    }

    /** Cambia el estado de una reunión (pendiente/completada/no asistió/confirmada). Cancelar sigue siendo cancelarReunion(). */
    public function actualizarEstado(Request $request, Reunion $reunion): RedirectResponse
    {
        $data = $request->validate([
            'estado' => ['required', Rule::enum(EstadoReunion::class)->except(EstadoReunion::Cancelada)],
        ]);

        $reunion->update(['estado' => $data['estado']]);

        return redirect()->route('admin.agenda.index')->with('status', 'Estado actualizado.');
    }

    /** @return array<string, mixed> */
    private function validarDatosReunion(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'inicia_en' => ['required', 'date_format:Y-m-d H:i'],
        ]);
    }

    public function actualizarConfiguracion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duracion_minutos' => ['required', 'integer', 'min:5', 'max:240'],
            'anticipacion_minima_horas' => ['required', 'integer', 'min:0', 'max:168'],
            'dias_visibles' => ['required', 'integer', 'min:1', 'max:90'],
            'notificar_email' => ['nullable', 'email'],
        ]);

        // Checkbox: si no viene marcado, el navegador ni siquiera manda el
        // campo — sin esto, desactivar la agenda y guardar la dejaría activa.
        $data['activa'] = $request->boolean('activa');

        $config = AgendaConfiguracion::query()->find(1) ?? new AgendaConfiguracion();
        $config->forceFill($data + ['id' => 1])->save();

        return redirect()->route('admin.agenda.index')->with('status', 'Configuración de la agenda guardada.');
    }

    public function actualizarHorarios(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dias' => ['required', 'array', 'size:7'],
            'dias.*.dia_semana' => ['required', 'integer', 'min:0', 'max:6'],
            'dias.*.hora_inicio' => ['required', 'date_format:H:i'],
            'dias.*.hora_fin' => ['required', 'date_format:H:i', 'after:dias.*.hora_inicio'],
            'dias.*.activo' => ['boolean'],
        ]);

        DB::transaction(function () use ($data) {
            DisponibilidadHorario::query()->delete();

            foreach ($data['dias'] as $dia) {
                DisponibilidadHorario::create([
                    'dia_semana' => $dia['dia_semana'],
                    'hora_inicio' => $dia['hora_inicio'],
                    'hora_fin' => $dia['hora_fin'],
                    'activo' => (bool) ($dia['activo'] ?? false),
                ]);
            }
        });

        return redirect()->route('admin.agenda.index')->with('status', 'Horario semanal actualizado.');
    }

    public function storeBloqueo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        DisponibilidadBloqueo::firstOrCreate(
            ['fecha' => $data['fecha']],
            ['motivo' => $data['motivo'] ?? null]
        );

        return redirect()->route('admin.agenda.index')->with('status', 'Día bloqueado.');
    }

    public function destroyBloqueo(DisponibilidadBloqueo $bloqueo): RedirectResponse
    {
        $bloqueo->delete();

        return redirect()->route('admin.agenda.index')->with('status', 'Bloqueo eliminado.');
    }

    public function cancelarReunion(Reunion $reunion): RedirectResponse
    {
        if ($reunion->estado !== EstadoReunion::Cancelada) {
            // TODO futuro: avisar por correo al cliente si el admin cancela.
            $reunion->update(['estado' => EstadoReunion::Cancelada]);
        }

        return redirect()->route('admin.agenda.index')->with('status', 'Reunión cancelada.');
    }
}
