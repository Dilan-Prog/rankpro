<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoArchivo;
use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Support\Labels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivosController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::withCount('archivos')->withSum('archivos', 'tamano')->orderBy('nombre')->get();
        $clienteId = $request->integer('cliente') ?: $clientes->first()?->id;

        $archivosCliente = $clienteId
            ? Archivo::with('usuario')->where('cliente_id', $clienteId)->orderBy('created_at', 'desc')->get()
            : collect();

        $tamanoTotal = (int) $archivosCliente->sum('tamano');
        $contratos = $archivosCliente->filter(fn (Archivo $a) => $a->tipo === TipoArchivo::Contrato)->count();
        $ultimoMovimiento = $archivosCliente->max('created_at');

        return view('admin.archivos.index', [
            'pageTitle' => 'Archivos y Documentos',
            'clientes' => $clientes->map(fn (Cliente $c) => $this->toClientePickerRow($c)),
            'clienteSeleccionado' => $clienteId,
            'archivos' => $archivosCliente->map(fn (Archivo $a) => $this->toArchivoRow($a)),
            'categorias' => collect(TipoArchivo::cases())->map(fn (TipoArchivo $c) => ['value' => $c->value, 'label' => Labels::tipoArchivo($c->value)]),
            'archivosCount' => $archivosCliente->count(),
            'pesoTotalMb' => round($tamanoTotal / 1048576, 1),
            'contratosCount' => $contratos,
            'ultimoMovimiento' => $ultimoMovimiento?->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'tipo' => ['required', Rule::enum(TipoArchivo::class)],
            'nombre' => ['nullable', 'string', 'max:255'],
            // 100 MB app-level cap — the practical ceiling on a given deployment
            // is also bound by php.ini's upload_max_filesize/post_max_size,
            // which this validation rule has no control over.
            'archivo' => ['required', 'file', 'max:102400', 'mimes:pdf,zip,rar,doc,docx,xls,xlsx,csv,ppt,pptx,png,jpg,jpeg,gif,svg,fig,txt'],
        ]);

        $file = $request->file('archivo');
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?? ''));
        $nombre = filled($data['nombre'] ?? null) ? $data['nombre'] : $file->getClientOriginalName();

        $path = $file->store("clientes/{$data['cliente_id']}/archivos", 'local');

        $archivo = Archivo::create([
            'cliente_id' => $data['cliente_id'],
            'nombre' => $nombre,
            'tipo' => $data['tipo'],
            'ruta_archivo' => $path,
            'tamano' => Storage::disk('local')->size($path),
            'extension' => $extension,
            'subido_por' => $request->user()->id,
        ]);

        return response()->json($this->toArchivoRow($archivo->fresh('usuario')), 201);
    }

    public function download(Archivo $archivo): StreamedResponse|RedirectResponse
    {
        if (! Storage::disk('local')->exists($archivo->ruta_archivo)) {
            return back()->withErrors(['archivo' => 'Este archivo no tiene un documento real disponible para descargar (registro de ejemplo).']);
        }

        return Storage::disk('local')->download($archivo->ruta_archivo, $archivo->nombre);
    }

    public function destroy(Archivo $archivo): JsonResponse
    {
        if (Storage::disk('local')->exists($archivo->ruta_archivo)) {
            Storage::disk('local')->delete($archivo->ruta_archivo);
        }

        $archivo->delete();

        return response()->json(['deleted' => true]);
    }

    private function toClientePickerRow(Cliente $cliente): array
    {
        return [
            'cliente_id' => $cliente->id,
            'cliente' => $cliente->nombre,
            'estado' => $cliente->estado->value,
            'archivos_count' => (int) $cliente->archivos_count,
            'peso_mb' => round((float) ($cliente->archivos_sum_tamano ?? 0) / 1048576, 1),
        ];
    }

    /** Shared shape for index()'s server-rendered rows and store()'s AJAX response. */
    private function toArchivoRow(Archivo $archivo): array
    {
        return [
            'id' => $archivo->id,
            'cliente_id' => $archivo->cliente_id,
            'nombre' => $archivo->nombre,
            'tipo' => $archivo->tipo->value,
            'tipo_label' => Labels::tipoArchivo($archivo->tipo->value),
            'extension' => $archivo->extension,
            'tamano' => $archivo->tamano,
            'tamano_label' => $archivo->tamano ? number_format($archivo->tamano / 1048576, 1) . ' MB' : '—',
            'subido_por' => $archivo->usuario?->name,
            'fecha' => $archivo->created_at->format('Y-m-d'),
            'download_url' => route('admin.archivos.download', $archivo->id),
        ];
    }
}
