<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Archivo;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchivosController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::orderBy('nombre')->get(['id', 'nombre']);
        $clienteId = $request->integer('cliente') ?: $clientes->first()?->id;

        $archivos = $clienteId
            ? Archivo::with('usuario')
                ->where('cliente_id', $clienteId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('tipo')
            : collect();

        return view('admin.archivos.index', [
            'pageTitle' => 'Archivos y Documentos',
            'clientes' => $clientes,
            'clienteSeleccionado' => $clienteId,
            'archivosPorTipo' => $archivos,
        ]);
    }

    public function download(Archivo $archivo): StreamedResponse|RedirectResponse
    {
        if (! Storage::disk('local')->exists($archivo->ruta_archivo)) {
            return back()->withErrors(['archivo' => 'Este archivo no tiene un documento real disponible para descargar (registro de ejemplo).']);
        }

        return Storage::disk('local')->download($archivo->ruta_archivo, $archivo->nombre);
    }

    public function destroy(Archivo $archivo): RedirectResponse
    {
        if (Storage::disk('local')->exists($archivo->ruta_archivo)) {
            Storage::disk('local')->delete($archivo->ruta_archivo);
        }

        $clienteId = $archivo->cliente_id;
        $archivo->delete();

        return redirect()->route('admin.archivos.index', ['cliente' => $clienteId])->with('status', 'Archivo eliminado.');
    }
}
