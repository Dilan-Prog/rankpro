<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookEntrega;
use App\Services\Webhooks\Despachador;
use App\Support\Webhooks\Eventos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Gestión de webhooks salientes desde el panel: alta/edición/baja, botón
 * "Probar" (dispara un evento 'ping' síncrono vía Despachador::probar) y
 * listado de entregas por webhook con reintento manual. El envío real y los
 * reintentos automáticos ya los resuelve App\Services\Webhooks\Despachador
 * y el comando webhooks:reintentar — aquí solo se arma la UI.
 */
class WebhooksController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $webhooks = Webhook::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (Webhook $w) => $w->toRow());

        return view('admin.integraciones.webhooks', [
            'webhooks' => $webhooks,
            'eventos' => Eventos::lista(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->reglas());

        $secreto = Str::random(48);

        $webhook = Webhook::create([
            'nombre' => $data['nombre'],
            'url' => $data['url'],
            'secreto' => $secreto,
            'eventos' => $data['eventos'],
            'activo' => $data['activo'] ?? true,
            'creado_por' => Auth::id(),
        ]);

        return response()->json([
            'ok' => true,
            'row' => $webhook->toRow(),
            'secreto' => $secreto,
        ], 201);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $data = $request->validate($this->reglas());

        $webhook->update([
            'nombre' => $data['nombre'],
            'url' => $data['url'],
            'eventos' => $data['eventos'],
            'activo' => $data['activo'] ?? $webhook->activo,
        ]);

        return response()->json(['ok' => true, 'row' => $webhook->fresh()->toRow()]);
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(['deleted' => true]);
    }

    public function regenerarSecreto(Webhook $webhook): JsonResponse
    {
        $secreto = Str::random(48);
        $webhook->update(['secreto' => $secreto]);

        return response()->json(['ok' => true, 'row' => $webhook->fresh()->toRow(), 'secreto' => $secreto]);
    }

    public function probar(Webhook $webhook, Despachador $despachador): JsonResponse
    {
        $entrega = $despachador->probar($webhook);

        return response()->json(['data' => $entrega->toRow()]);
    }

    public function entregas(Request $request, Webhook $webhook): JsonResponse
    {
        $paginador = $webhook->entregas()->orderByDesc('id')->paginate(15);

        return response()->json([
            'data' => collect($paginador->items())->map(fn (WebhookEntrega $e) => $e->toRow()),
            'meta' => [
                'current_page' => $paginador->currentPage(),
                'last_page' => $paginador->lastPage(),
                'total' => $paginador->total(),
            ],
        ]);
    }

    public function reintentar(WebhookEntrega $entrega, Despachador $despachador): JsonResponse
    {
        if (! in_array($entrega->estado->value, ['pendiente', 'fallido'], true)) {
            return response()->json(['message' => 'Solo se pueden reintentar entregas pendientes o fallidas.'], 422);
        }

        if ($entrega->estado->value === 'fallido') {
            // Reintento manual forzado: resetea el contador de intentos para
            // que el backoff empiece de cero, igual que si fuera la primera vez.
            $entrega->update(['intentos' => 0, 'estado' => 'pendiente']);
        }

        $entrega = $despachador->entregar($entrega->fresh());

        return response()->json(['data' => $entrega->toRow()]);
    }

    /** @return array<string, mixed> */
    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:500'],
            'eventos' => ['required', 'array', 'min:1'],
            'eventos.*' => [Rule::in(array_merge(['*'], array_keys(Eventos::CATALOGO)))],
            'activo' => ['boolean'],
        ];
    }
}
