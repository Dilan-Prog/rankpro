<?php

namespace App\Http\Controllers\Api\V1\Conversiones;

use App\Enums\TipoConversion;
use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\AdsConversion;
use App\Models\Cliente;
use App\Support\Api\{ConsultaOpciones, Respuesta, Serializador};
use App\Support\Conversiones\RegistradorConversion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API de conversiones para RankPro (n8n, etc.) — a diferencia de
 * /api/tracking/conversion (público, token de cliente en el sitio), esta va
 * tras auth:sanctum y recibe cliente_id explícito en el payload.
 */
class ConversionesApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(AdsConversion::query(), $request, new ConsultaOpciones(
            filtrosExactos: ['cliente_id', 'estado', 'tipo', 'ads_embudo_etapa_id'],
            ordenables: ['id', 'created_at', 'updated_at'],
            incluibles: ['cliente', 'adsClic', 'etapa'],
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'visitor_id' => ['required', 'string', 'max:64'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoConversion::class)],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $cliente = Cliente::findOrFail($data['cliente_id']);
        unset($data['cliente_id']);

        $conversion = RegistradorConversion::registrarConversion($cliente, $data);

        return Respuesta::recurso(Serializador::modelo($conversion), 201);
    }

    public function update(Request $request, AdsConversion $conversion)
    {
        $data = $request->validate([
            'valor' => ['nullable', 'numeric', 'min:0'],
            'datos_personalizados' => ['nullable', 'array'],
            'datos_personalizados.*' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->has('valor')) {
            $conversion->valor = $data['valor'] ?? null;
        }

        if ($request->has('datos_personalizados')) {
            // array_replace (no array_merge) — preserva las claves numéricas (IDs de columna) tal cual.
            $conversion->datos_personalizados = array_replace(
                $conversion->datos_personalizados ?? [],
                $data['datos_personalizados']
            );
        }

        $conversion->save();

        return Respuesta::recurso(Serializador::modelo($conversion->fresh()));
    }

    /** La etapa elegida debe pertenecer al mismo cliente de la conversión — igual que ConversionesController::asignarEtapa. */
    public function asignarEtapa(Request $request, AdsConversion $conversion)
    {
        $data = $request->validate([
            'ads_embudo_etapa_id' => ['nullable', 'exists:ads_embudo_etapas,id'],
        ]);

        if (! empty($data['ads_embudo_etapa_id'])) {
            $perteneceAlCliente = $conversion->cliente->embudoEtapas()->where('id', $data['ads_embudo_etapa_id'])->exists();
            if (! $perteneceAlCliente) {
                throw new \App\Exceptions\ErrorDeDominio('La etapa seleccionada no pertenece a este cliente.', 'ads_embudo_etapa_id', 422);
            }
        }

        $conversion->update(['ads_embudo_etapa_id' => $data['ads_embudo_etapa_id'] ?? null]);

        return Respuesta::recurso(Serializador::modelo($conversion->fresh()));
    }
}
