<?php

namespace App\Support\Webhooks;

/**
 * Catálogo cerrado de eventos de webhook. Un evento que no está aquí no se
 * puede suscribir desde el panel ni emitir (Despachador::emitir lo valida).
 */
class Eventos
{
    /** @var array<string, string> evento => descripción corta */
    public const CATALOGO = [
        'ping' => 'Prueba manual desde el panel.',
        'cliente.creado' => 'Se dio de alta un cliente.',
        'cliente.actualizado' => 'Se editó un cliente.',
        'servicio.creado' => 'Se contrató un servicio a un cliente.',
        'servicio.estado_cambiado' => 'Un servicio cambió de estado (activo/pausado/cancelado).',
        'conversion.registrada' => 'Se registró una conversión (formulario, WhatsApp, llamada, compra).',
        'clic.registrado' => 'Se registró un clic de Google/Meta Ads.',
        'correo.enviado' => 'Un envío de correo terminó de mandarse.',
        'correo.abierto' => 'Un destinatario abrió un correo (estimado, vía píxel).',
        'correo.clic' => 'Un destinatario hizo clic en un enlace de un correo.',
        'finanza.creada' => 'Se registró un cargo o ingreso.',
        'finanza.pagada' => 'Un cargo se marcó como pagado.',
        'finanza.vencida' => 'Un cargo venció sin pagarse.',
        'propuesta.estado_cambiado' => 'Una propuesta cambió de estado (enviada/aprobada/rechazada).',
        'reporte.entregado' => 'Un reporte se marcó como entregado al cliente.',
        'keyword.medicion_registrada' => 'Se registró una medición de posición de una keyword.',
        'tarea.creada' => 'Se creó una tarea de un proyecto de Desarrollo.',
        'tarea.completada' => 'Una tarea se marcó como completada.',
        'bug.creado' => 'Se reportó un bug.',
        'bug.resuelto' => 'Un bug se marcó como resuelto.',
        'fase.aprobada' => 'Se aprobó una fase de SEO, Ads, Automatizaciones o Desarrollo.',
        'archivo.subido' => 'Se subió un archivo al expediente de un cliente.',
        'envio.programado' => 'Un envío de correo quedó programado para una fecha.',
    ];

    public static function existe(string $evento): bool
    {
        return array_key_exists($evento, self::CATALOGO);
    }

    /** @return array<int, array{evento: string, descripcion: string}> */
    public static function lista(): array
    {
        $out = [];
        foreach (self::CATALOGO as $evento => $descripcion) {
            $out[] = ['evento' => $evento, 'descripcion' => $descripcion];
        }

        return $out;
    }
}
