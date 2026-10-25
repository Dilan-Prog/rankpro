<?php

namespace App\Services\Reportes;

use App\Models\ReporteProgramacion;
use App\Models\User;
use Throwable;

/**
 * Operaciones sobre una programación compartidas por el panel y la API, para
 * que reglas de calendario y el registro de la prueba no diverjan entre ambos.
 */
class GestorReporteProgramaciones
{
    public function __construct(private EnviadorReporteProgramado $enviador)
    {
    }

    /** @param array<string, mixed> $data datos ya validados */
    public function crear(array $data, ?int $usuarioId): ReporteProgramacion
    {
        $prog = new ReporteProgramacion($data + ['activo' => true, 'creado_por' => $usuarioId]);
        $prog->recalcularProximo();
        $prog->save();

        return $prog;
    }

    /** @param array<string, mixed> $data datos ya validados */
    public function actualizar(ReporteProgramacion $prog, array $data): ReporteProgramacion
    {
        // La BD devuelve la hora como H:i:s y el formulario manda H:i: se compara recortada.
        $calendarioCambio = $prog->dia_mes !== (int) $data['dia_mes']
            || substr((string) $prog->hora, 0, 5) !== $data['hora'];

        $prog->fill($data);
        // Si cambió el calendario, el próximo envío se recalcula desde ahora.
        if ($calendarioCambio || ! $prog->proximo_envio_en) {
            $prog->recalcularProximo();
        }
        $prog->save();

        return $prog;
    }

    public function pausar(ReporteProgramacion $prog): ReporteProgramacion
    {
        $prog->activo = false;
        $prog->save();

        return $prog;
    }

    public function activar(ReporteProgramacion $prog): ReporteProgramacion
    {
        // Solo al reactivar una pausada, y desde hoy: una fecha vieja dispararía de golpe un envío del que nadie se acuerda.
        if (! $prog->activo) {
            $prog->activo = true;
            $prog->recalcularProximo();
            $prog->save();
        }

        return $prog;
    }

    /** @return array{ok: bool, mensaje: string} */
    public function probar(ReporteProgramacion $prog, User $usuario): array
    {
        try {
            $r = $this->enviador->probar($prog, $usuario);
            $mensaje = "Prueba enviada a {$r['email']} con el reporte {$r['reporte']}.";
            $ok = true;
        } catch (Throwable $e) {
            $mensaje = EnviadorReporteProgramado::sanear($e->getMessage());
            $ok = false;
        }

        // Persistente: el toast desaparece solo y un fallo de prueba pasaba inadvertido.
        $prog->forceFill(['ultima_prueba' => now()->format('Y-m-d H:i').' · '.($ok ? '' : 'ERROR: ').$mensaje])->save();

        return ['ok' => $ok, 'mensaje' => $mensaje];
    }
}
