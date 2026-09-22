<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FaseAutomatizacion;
use App\Http\Controllers\Controller;
use App\Models\AutomatizacionFaseDiagnostico;
use App\Models\AutomatizacionProyecto;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Support\Reglas\Automatizaciones as ReglasAutomatizaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AutomatizacionController extends Controller
{
    public function index(): View
    {
        $proyectos = AutomatizacionProyecto::with('cliente', 'faseDiagnostico', 'reporteActual')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (AutomatizacionProyecto $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'cliente' => $p->cliente?->nombre ?? '—',
                'estado' => $p->estado->value,
                'fase_actual' => $p->fase_actual->value,
                'ciclo_actual' => $p->ciclo_actual,
                'flujos_activos_total' => $p->reporteActual?->flujos_activos_total,
                'horas_ahorradas_mes' => $p->reporteActual?->horas_ahorradas_mes,
            ]);

        return view('admin.automatizaciones.index', [
            'pageTitle' => 'Automatizaciones',
            'proyectos' => $proyectos,
            'enProceso' => $proyectos->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.automatizaciones.create', [
            'pageTitle' => 'Nuevo Proyecto de Automatización',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(),
            'checklistDiagnostico' => AutomatizacionFaseDiagnostico::CHECKLIST,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(ReglasAutomatizaciones::crear());
        $data['viable'] = $request->boolean('viable');

        $proyecto = DB::transaction(function () use ($data) {
            $proyecto = AutomatizacionProyecto::create([
                'cliente_id' => $data['cliente_id'],
                'servicio_id' => $data['servicio_id'],
                'nombre' => $data['nombre'],
                'estado' => 'activa',
                'fase_actual' => FaseAutomatizacion::Diagnostico->value,
                'ciclo_actual' => 1,
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
            ]);

            ReglasAutomatizaciones::guardar($proyecto, $data);

            return $proyecto;
        });

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', "Proyecto \"{$proyecto->nombre}\" creado. Comienza en fase de Diagnóstico.");
    }

    public function show(AutomatizacionProyecto $proyecto): View
    {
        return view('admin.automatizaciones.show', [
            'pageTitle' => $proyecto->nombre,
            'proyecto' => $proyecto->load(
                'cliente',
                'servicio',
                'faseDiagnostico',
                'faseDiseno',
                'faseImplementacion',
                'reporteActual',
                'reportes',
                'flujos'
            ),
        ]);
    }

    public function edit(AutomatizacionProyecto $proyecto): View
    {
        return view('admin.automatizaciones.edit', [
            'pageTitle' => 'Editar Proyecto de Automatización',
            'proyecto' => $proyecto,
            'clientes' => Cliente::orderBy('nombre')->get(['id', 'nombre']),
            'serviciosAutomatizacion' => Servicio::where('cliente_id', $proyecto->cliente_id)->where('tipo', 'automatizacion')->get(),
        ]);
    }

    public function update(Request $request, AutomatizacionProyecto $proyecto): RedirectResponse
    {
        $data = $request->validate(ReglasAutomatizaciones::actualizar());

        $proyecto->update($data);

        return redirect()->route('admin.automatizaciones.show', $proyecto)->with('status', "Proyecto \"{$proyecto->nombre}\" actualizado correctamente.");
    }

    public function destroy(AutomatizacionProyecto $proyecto): RedirectResponse
    {
        $proyecto->delete();

        return redirect()->route('admin.automatizaciones.index')->with('status', 'Proyecto de automatización eliminado.');
    }
}
