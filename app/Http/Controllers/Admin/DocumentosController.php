<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Servicio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentosController extends Controller
{
    public function createContrato(): View
    {
        return view('admin.archivos.contrato-create', [
            'pageTitle' => 'Generar Contrato',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(['id', 'nombre', 'empresa']),
        ]);
    }

    public function previewContrato(Request $request): View
    {
        $data = $this->validatedContrato($request);
        [$cliente, $servicios] = $this->resolveClienteServicios($data);

        $html = view('pdf.contrato', [
            'numero' => 'VISTA PREVIA',
            'cliente' => $cliente,
            'servicios' => $servicios,
            'totalMensual' => $servicios->sum('precio_mensual'),
            'fechaEmision' => now()->format('Y-m-d'),
            'fechaInicio' => $data['fecha_inicio'],
            'fechaFin' => $data['fecha_fin'] ?? null,
            'condiciones' => $data['condiciones'],
        ])->render();

        return view('admin.archivos.documento-preview', [
            'pageTitle' => 'Vista previa — Contrato',
            'documentoHtml' => $html,
            'formAction' => route('admin.archivos.contratos.store'),
            'cancelRoute' => route('admin.archivos.contratos.create'),
            'hidden' => $data,
        ]);
    }

    public function storeContrato(Request $request): StreamedResponse
    {
        $data = $this->validatedContrato($request);
        [$cliente, $servicios] = $this->resolveClienteServicios($data);

        $numero = 'CONT-'.now()->format('Y').'-'.str_pad((string) (Archivo::where('tipo', 'contrato')->count() + 1), 4, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.contrato', [
            'numero' => $numero,
            'cliente' => $cliente,
            'servicios' => $servicios,
            'totalMensual' => $servicios->sum('precio_mensual'),
            'fechaEmision' => now()->format('Y-m-d'),
            'fechaInicio' => $data['fecha_inicio'],
            'fechaFin' => $data['fecha_fin'] ?? null,
            'condiciones' => $data['condiciones'],
        ])->setPaper('letter');

        $filename = "{$numero}.pdf";
        $path = "clientes/{$cliente->id}/contratos/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        Archivo::create([
            'cliente_id' => $cliente->id,
            'nombre' => "Contrato {$numero} — {$cliente->nombre}.pdf",
            'tipo' => 'contrato',
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => 'pdf',
            'subido_por' => Auth::id(),
        ]);

        return Storage::disk('local')->download($path, $filename);
    }

    public function createPropuesta(): View
    {
        return view('admin.archivos.propuesta-create', [
            'pageTitle' => 'Generar Propuesta',
            'clientes' => Cliente::with('servicios')->orderBy('nombre')->get(['id', 'nombre', 'empresa']),
        ]);
    }

    public function previewPropuesta(Request $request): View
    {
        $data = $this->validatedPropuesta($request);
        [$cliente, $servicios] = $this->resolveClienteServicios($data);

        $html = view('pdf.propuesta', [
            'numero' => 'VISTA PREVIA',
            'cliente' => $cliente,
            'servicios' => $servicios,
            'totalMensual' => $servicios->sum('precio_mensual'),
            'fechaEmision' => now()->format('Y-m-d'),
            'validezDias' => $data['validez_dias'],
            'condiciones' => $data['condiciones'],
        ])->render();

        return view('admin.archivos.documento-preview', [
            'pageTitle' => 'Vista previa — Propuesta',
            'documentoHtml' => $html,
            'formAction' => route('admin.archivos.propuestas.store'),
            'cancelRoute' => route('admin.archivos.propuestas.create'),
            'hidden' => $data,
        ]);
    }

    public function storePropuesta(Request $request): StreamedResponse
    {
        $data = $this->validatedPropuesta($request);
        [$cliente, $servicios] = $this->resolveClienteServicios($data);

        $numero = 'PROP-'.now()->format('Y').'-'.str_pad((string) (Archivo::where('tipo', 'propuesta')->count() + 1), 4, '0', STR_PAD_LEFT);

        $pdf = Pdf::loadView('pdf.propuesta', [
            'numero' => $numero,
            'cliente' => $cliente,
            'servicios' => $servicios,
            'totalMensual' => $servicios->sum('precio_mensual'),
            'fechaEmision' => now()->format('Y-m-d'),
            'validezDias' => $data['validez_dias'],
            'condiciones' => $data['condiciones'],
        ])->setPaper('letter');

        $filename = "{$numero}.pdf";
        $path = "clientes/{$cliente->id}/propuestas/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        Archivo::create([
            'cliente_id' => $cliente->id,
            'nombre' => "Propuesta {$numero} — {$cliente->nombre}.pdf",
            'tipo' => 'propuesta',
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => 'pdf',
            'subido_por' => Auth::id(),
        ]);

        return Storage::disk('local')->download($path, $filename);
    }

    private function validatedContrato(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'condiciones' => ['required', 'string', 'max:5000'],
        ]);
    }

    private function validatedPropuesta(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer', 'exists:servicios,id'],
            'validez_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'condiciones' => ['required', 'string', 'max:5000'],
        ]);
    }

    /** @return array{0: Cliente, 1: \Illuminate\Database\Eloquent\Collection<int, Servicio>} */
    private function resolveClienteServicios(array $data): array
    {
        $cliente = Cliente::findOrFail($data['cliente_id']);
        $servicios = Servicio::where('cliente_id', $cliente->id)->whereIn('id', $data['servicios'])->get();

        return [$cliente, $servicios];
    }
}
