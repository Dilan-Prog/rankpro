<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\KeywordLista;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KeywordsController extends Controller
{
    public function index(): View
    {
        $listas = KeywordLista::with(['cliente', 'responsable', 'keywords' => fn ($q) => $q->orderBy('keyword')])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (KeywordLista $l) => $l->toRow());

        $keywordsSinLista = Keyword::whereNull('lista_id')
            ->with('cliente')
            ->orderBy('keyword')
            ->get()
            ->map(fn (Keyword $k) => $k->toRow());

        return view('admin.keywords.index', [
            'pageTitle' => 'Banco de Keywords',
            'listas' => $listas,
            'keywordsSinLista' => $keywordsSinLista,
            'totalKeywords' => $listas->sum('keywords_count') + $keywordsSinLista->count(),
            'clientes' => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
            'usuarios' => User::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $keyword = Keyword::create($this->validated($request));

        return response()->json($keyword->fresh(['cliente', 'lista'])->toRow(), 201);
    }

    public function update(Request $request, Keyword $keyword): JsonResponse
    {
        $data = $this->validated($request, $keyword);

        // posicion_anterior is server-only — only advanced when posicion_actual genuinely changed.
        if (array_key_exists('posicion_actual', $data) && (int) ($data['posicion_actual'] ?? 0) !== (int) ($keyword->posicion_actual ?? 0)) {
            $data['posicion_anterior'] = $keyword->posicion_actual;
        }

        $keyword->update($data);

        return response()->json($keyword->fresh(['cliente', 'lista'])->toRow());
    }

    public function destroy(Keyword $keyword): JsonResponse
    {
        $keyword->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request, ?Keyword $keyword = null): array
    {
        $clienteId = $request->input('cliente_id', $keyword?->cliente_id);

        return $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'lista_id' => [
                'nullable',
                'integer',
                Rule::exists('keyword_listas', 'id')->where(fn ($q) => $q->where('cliente_id', $clienteId)),
            ],
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
