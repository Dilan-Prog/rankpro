<?php

namespace App\Support\Inicio;

use App\Models\CorreoEvento;
use App\Models\ServicioEvento;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Línea de tiempo de solo lectura para Inicio: junta las bitácoras que ya existen
 * (ciclo de vida de Servicios y aperturas/clics de Correo) sin crear una tabla de
 * auditoría paralela. Lo que ninguna bitácora registra (p. ej. altas de clientes)
 * no aparece: no se infiere.
 *
 * Cada fuente se consulta con tope propio y solo si el usuario tiene el permiso del
 * módulo, para no filtrar datos de módulos vetados.
 */
class ActividadReciente
{
    /**
     * @return Collection<int, array{icono: string, texto: string, detalle: string|null, cuando: CarbonInterface, href: string|null}>
     */
    public function ultimos(User $usuario, int $limite = 8): Collection
    {
        return collect()
            ->merge($usuario->tienePermiso('servicios') ? $this->deServicios($limite) : [])
            ->merge($usuario->tienePermiso('correo') ? $this->deCorreo($limite) : [])
            ->sortByDesc(fn (array $e) => $e['cuando']->getTimestamp())
            ->take($limite)
            ->values();
    }

    private function deServicios(int $limite): Collection
    {
        return ServicioEvento::query()
            ->with(['servicio:id,nombre,cliente_id', 'servicio.cliente:id,nombre,empresa', 'usuario:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit($limite)
            ->get()
            ->map(fn (ServicioEvento $e) => [
                'icono' => 'fa-briefcase',
                'texto' => $e->descripcion,
                'detalle' => collect([$e->servicio?->nombre, $e->servicio?->cliente?->nombre_comercial, $e->usuario?->name])->filter()->implode(' · ') ?: null,
                'cuando' => $e->created_at,
                'href' => \Illuminate\Support\Facades\Route::has('admin.servicios.index') ? route('admin.servicios.index') : null,
            ]);
    }

    private function deCorreo(int $limite): Collection
    {
        return CorreoEvento::query()
            ->with(['destinatario:id,envio_id,email,nombre', 'destinatario.envio:id,asunto'])
            ->latest('created_at')
            ->latest('id')
            ->limit($limite)
            ->get()
            ->map(fn (CorreoEvento $e) => [
                'icono' => $e->tipo === 'clic' ? 'fa-hand-pointer' : 'fa-envelope-open',
                'texto' => ($e->tipo === 'clic' ? 'Clic en un correo' : 'Apertura de un correo').': '.($e->destinatario?->nombre ?: $e->destinatario?->email ?: 'destinatario eliminado'),
                'detalle' => $e->destinatario?->envio?->asunto,
                'cuando' => $e->created_at,
                'href' => $e->destinatario?->envio_id ? route('admin.correo.envios.show', $e->destinatario->envio_id) : null,
            ]);
    }
}
