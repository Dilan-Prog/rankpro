<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Keyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KeywordsController extends Controller
{
    public function index(): View
    {
        $keywords = Keyword::with('cliente')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Keyword $k) => [
                'id' => $k->id,
                'keyword' => $k->keyword,
                'tipo' => $k->tipo,
                'volumen_busqueda' => $k->volumen_busqueda,
                'dificultad' => $k->dificultad,
                'cpc_estimado' => (float) $k->cpc_estimado,
                'intencion' => $k->intencion,
                'url_asignada' => $k->url_asignada,
                'posicion_actual' => $k->posicion_actual,
                'estado' => $k->estado->value,
                'herramienta_origen' => $k->herramienta_origen,
                'cliente' => $k->cliente?->nombre ?? '—',
                'cliente_id' => $k->cliente_id,
            ]);

        $clientes = Cliente::orderBy('nombre')->pluck('nombre', 'id');

        return view('admin.keywords.index', [
            'pageTitle' => 'Banco de Keywords',
            'keywords' => $keywords,
            'clientes' => $clientes,
        ]);
    }

    public function create(): View
    {
        return view('admin.keywords.create', [
            'pageTitle' => 'Nueva Keyword',
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $keyword = Keyword::create($data);

        return redirect()->route('admin.keywords.index')->with('status', "Keyword \"{$keyword->keyword}\" añadida correctamente.");
    }

    public function edit(Keyword $keyword): View
    {
        return view('admin.keywords.edit', [
            'pageTitle' => 'Editar Keyword',
            'keyword' => $keyword,
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function update(Request $request, Keyword $keyword): RedirectResponse
    {
        $data = $this->validated($request);

        $keyword->update($data);

        return redirect()->route('admin.keywords.index')->with('status', "Keyword \"{$keyword->keyword}\" actualizada correctamente.");
    }

    public function destroy(Keyword $keyword): RedirectResponse
    {
        $keyword->delete();

        return redirect()->route('admin.keywords.index')->with('status', 'Keyword eliminada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'keyword' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:principal,secundaria,long_tail,lsi'],
            'volumen_busqueda' => ['nullable', 'integer', 'min:0'],
            'dificultad' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cpc_estimado' => ['nullable', 'numeric', 'min:0'],
            'intencion' => ['nullable', 'in:informacional,transaccional,navegacional'],
            'idioma' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:10'],
            'herramienta_origen' => ['nullable', 'in:semrush,ahrefs,google_kp,otro'],
            'url_asignada' => ['nullable', 'string', 'max:255'],
            'posicion_actual' => ['nullable', 'integer', 'min:0'],
            'estado' => ['required', 'in:en_uso,seguimiento,descartada'],
            'fecha_incorporacion' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
