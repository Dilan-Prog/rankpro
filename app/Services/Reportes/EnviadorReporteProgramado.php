<?php

namespace App\Services\Reportes;

use App\Enums\EstadoDestinatarioCorreo;
use App\Enums\EstadoEnvioCorreo;
use App\Enums\EstadoReporte;
use App\Models\CorreoAdjunto;
use App\Models\CorreoEnvio;
use App\Models\CorreoPlantilla;
use App\Models\Reporte;
use App\Models\ReporteProgramacion;
use App\Models\User;
use App\Services\Correo\EnviadorCorreo;
use App\Support\Correo\Variables;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Manda por correo el reporte del mes anterior de una programación, reutilizando
 * PublicadorReporte (PDF) y EnviadorCorreo (envío con adjuntos): aquí solo se
 * pegan las dos piezas y se deja el resultado persistido en la programación.
 */
class EnviadorReporteProgramado
{
    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public const MENSAJE_EN_CURSO = 'Este reporte ya se está enviando. Espera a que termine y revisa el resultado.';

    public function __construct(
        private PublicadorReporte $publicador,
        private EnviadorCorreo $enviador,
    ) {
    }

    /**
     * Ejecuta la programación y guarda el resultado. Nunca lanza: un fallo de
     * un cliente no debe frenar a los demás del comando.
     *
     * $manual = "Enviar ahora": no mueve proximo_envio_en, no se salta el
     * duplicado y el periodo se toma de hoy.
     *
     * @return bool false si no se procesó porque otro proceso ya la tiene (lock) o ya la reclamó.
     */
    public function ejecutar(ReporteProgramacion $prog, bool $manual = false): bool
    {
        // Lock con TTL: comando y "Enviar ahora" no pueden mandar el mismo correo a la vez, y si el proceso muere el lock expira solo.
        $lock = Cache::lock("repprog:{$prog->id}", 600);

        if (! $lock->get()) {
            return false;
        }

        try {
            return $this->procesar($prog, $manual);
        } finally {
            $lock->release();
        }
    }

    private function procesar(ReporteProgramacion $prog, bool $manual): bool
    {
        // Con el día programado como referencia, una corrida tardía sigue mandando el mes que tocaba.
        $referencia = $manual ? now() : ($prog->proximo_envio_en ?? now());
        $resultadoPrevio = $prog->ultimo_resultado;
        $avanzar = ! $manual;
        // [evento, mensaje de error|null, reporte|null, envío|null]; se emite al final, ya guardada la programación.
        $aviso = null;

        try {
            // Un cliente pausado, cancelado o eliminado no debe recibir reportes aunque la programación siga activa.
            if (! $prog->clienteActivo()) {
                throw new RuntimeException('El cliente no está activo (pausado, cancelado o eliminado): no se envían reportes.');
            }

            $reporte = $this->reporteDelPeriodo($prog, $referencia);

            // Si el comando corre dos veces el mismo mes, el reporte ya enviado no se repite.
            if (! $manual && $prog->ultimo_ok && $prog->ultimo_reporte_id === $reporte->id) {
                $prog->recalcularProximo(now());
                $prog->save();

                return true;
            }

            // Se avanza el calendario ANTES del SMTP: si el proceso muere tras enviar, no se repite el correo.
            if (! $manual) {
                if (! $this->reclamar($prog)) {
                    return false;
                }
                $avanzar = false;
            }

            $envio = $this->enviarReporte($prog, $reporte);
            $fallidos = $envio->destinatarios->where('estado', EstadoDestinatarioCorreo::Fallido);
            $total = $envio->destinatarios->count();

            if ($fallidos->isEmpty()) {
                $mensaje = 'ok';
                $aviso = ['reporte_programado.enviado', null, $reporte, $envio];
            } else {
                $detalle = $fallidos->map(fn ($d) => "{$d->email}: {$d->error}")->implode('; ');
                $mensaje = self::sanear($fallidos->count() === $total
                    ? "No salió ningún correo. {$detalle}"
                    : 'Salió a '.($total - $fallidos->count())." de {$total}. Falló: {$detalle}");
                // Los correos ya constan en el envío: el log lleva solo conteos y motivos para no duplicarlos.
                Log::error('Reportes programados: envío con fallos', [
                    'programacion_id' => $prog->id,
                    'fallidos' => $fallidos->count(),
                    'total' => $total,
                    'motivos' => $fallidos->pluck('error')->unique()->implode('; '),
                ]);
                $aviso = ['reporte_programado.fallido', $mensaje, $reporte, $envio];
            }

            if ($envio->estado === EstadoEnvioCorreo::Enviado && $reporte->estado !== EstadoReporte::Entregado) {
                $reporte->update(['estado' => EstadoReporte::Entregado]);
            }

            $prog->forceFill([
                'ultimo_intento_en' => now(),
                'ultimo_envio_en' => $envio->estado === EstadoEnvioCorreo::Enviado ? now() : $prog->ultimo_envio_en,
                'ultimo_ok' => $fallidos->isEmpty(),
                'ultimo_resultado' => $mensaje,
                'ultimo_reporte_id' => $reporte->id,
                'ultimo_correo_envio_id' => $envio->id,
            ]);
        } catch (ReporteNoListoException $e) {
            // Único fallo que no avanza el calendario: se reintenta en la
            // siguiente corrida hasta que el reporte esté listo. Se loguea solo
            // la primera vez para no llenar el log con un error por corrida.
            $avanzar = false;
            $nuevo = self::sanear($e->getMessage()) !== $resultadoPrevio;
            if ($nuevo) {
                Log::error('Reportes programados: reporte no listo', ['programacion_id' => $prog->id, 'error' => $e->getMessage()]);
            }
            $this->registrarFallo($prog, $e);
            // Mismo criterio que el log: un reporte que sigue sin estar listo no repite el aviso cada 15 min.
            if ($nuevo) {
                $aviso = ['reporte_programado.fallido', self::sanear($e->getMessage()), null, null];
            }
        } catch (Throwable $e) {
            // El log conserva el mensaje completo (útil para depurar); lo que se expone por API va saneado.
            Log::error('Reportes programados: fallo al enviar', ['programacion_id' => $prog->id, 'error' => $e->getMessage()]);
            $this->registrarFallo($prog, $e);
            $aviso = ['reporte_programado.fallido', self::sanear($e->getMessage()), null, null];
        }

        if ($avanzar) {
            $prog->recalcularProximo(now());
        }

        $prog->save();

        if ($aviso) {
            $this->avisar($prog, self::periodoAnterior($referencia)[0]->format('Y-m'), ...$aviso);
        }

        return true;
    }

    /**
     * Adelanta proximo_envio_en solo si nadie lo movió desde que se leyó la fila:
     * el UPDATE condicionado garantiza un único ganador aunque el lock no baste.
     */
    private function reclamar(ReporteProgramacion $prog): bool
    {
        $siguiente = ReporteProgramacion::calcularProximo($prog->dia_mes, (string) $prog->hora, now());
        $original = $prog->getRawOriginal('proximo_envio_en');

        $consulta = ReporteProgramacion::where('id', $prog->id)->where('activo', true);
        $original === null ? $consulta->whereNull('proximo_envio_en') : $consulta->where('proximo_envio_en', $original);

        if ($consulta->update(['proximo_envio_en' => $siguiente]) === 0) {
            return false;
        }

        $prog->proximo_envio_en = $siguiente;
        $prog->syncOriginalAttribute('proximo_envio_en');

        return true;
    }

    /**
     * Mensaje apto para guardar y exponer por API: sin SQL, sin rutas del
     * servidor y acotado, pero con el motivo legible (p. ej. host:puerto del SMTP).
     */
    public static function sanear(string $mensaje): string
    {
        $mensaje = str_replace([base_path(), str_replace('\\', '/', base_path())], '[app]', $mensaje);
        // La consulta y sus valores no le sirven a quien lee el panel.
        $mensaje = preg_replace('/\s*\(SQL:.*$/s', '', $mensaje);
        $mensaje = preg_replace('~\b[A-Za-z]:[\\\\/][^\s\'")]+~', '[ruta]', $mensaje);
        $mensaje = preg_replace('~(?<![\w.:/])/(?:[\w.\-]+/)+[\w.\-]*~', '[ruta]', $mensaje);

        return mb_substr(trim(preg_replace('/\s+/', ' ', $mensaje)), 0, 300);
    }

    private function avisar(ReporteProgramacion $prog, string $periodo, string $evento, ?string $error, ?Reporte $reporte, ?CorreoEnvio $envio): void
    {
        $destinatarios = array_values($prog->destinatarios ?? []);
        $datos = [
            'programacion_id' => $prog->id,
            'cliente_id' => $prog->cliente_id,
            'cliente' => $prog->cliente?->nombre,
            'area' => $prog->area->value,
            'reporte_id' => $reporte?->id,
            'periodo' => $periodo,
            // Sin convención de privacidad en otros eventos: se mandan los correos para que n8n pueda avisar a quién salió.
            'destinatarios' => $destinatarios,
            'total_destinatarios' => count($destinatarios),
            'correo_envio_id' => $envio?->id,
        ];

        webhook($evento, $error === null ? $datos : $datos + ['error' => $error]);
    }

    /**
     * Manda el reporte solo al correo del usuario, sin crear envío ni tocar el
     * último envío. Lanza si algo falla; el controlador persiste el resultado.
     *
     * @return array{reporte: string, email: string}
     */
    public function probar(ReporteProgramacion $prog, User $usuario): array
    {
        $reporte = $this->reporteDelPeriodo($prog, now());
        $plantilla = $this->plantillaValida($prog);
        $archivo = $this->publicador->publicar($reporte, 'pdf');

        // Envío sin guardar: solo para reutilizar contenidoEfectivo() y no duplicar la lectura de la plantilla.
        $envio = (new CorreoEnvio())->setRelation('plantilla', $plantilla);

        $this->enviador->prueba(
            $envio->contenidoEfectivo(),
            $prog->asunto,
            $this->variables($prog, $reporte, $usuario->name),
            $usuario->email,
            null,
            null,
            [['disco' => 'local', 'ruta' => $archivo->ruta_archivo, 'nombre' => $archivo->nombre]],
        );

        return ['reporte' => (string) $reporte->numero, 'email' => $usuario->email];
    }

    /**
     * Mes anterior al de $referencia, del primer al último día.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodoAnterior(Carbon $referencia): array
    {
        $inicio = $referencia->copy()->startOfMonth()->subMonthNoOverflow();

        return [$inicio, $inicio->copy()->endOfMonth()];
    }

    public function reporteDelPeriodo(ReporteProgramacion $prog, Carbon $referencia): Reporte
    {
        [$inicio, $fin] = self::periodoAnterior($referencia);

        // Solo reportes ya revisados: un borrador puede tener datos a medias.
        $reporte = Reporte::where('cliente_id', $prog->cliente_id)
            ->where('area', $prog->area->value)
            ->whereIn('estado', [EstadoReporte::Listo->value, EstadoReporte::Entregado->value])
            ->whereBetween('periodo_fin', [$inicio->toDateString(), $fin->toDateString()])
            ->orderByDesc('periodo_fin')
            ->orderByDesc('id')
            ->first();

        if (! $reporte) {
            throw new ReporteNoListoException(
                "No hay un reporte de {$prog->area->label()} en estado listo para {$this->mes($inicio)}. Márcalo como listo y se enviará solo."
            );
        }

        return $reporte;
    }

    private function enviarReporte(ReporteProgramacion $prog, Reporte $reporte): CorreoEnvio
    {
        $plantilla = $this->plantillaValida($prog);
        $emails = collect($prog->destinatarios ?? [])
            ->map(fn ($e) => Str::lower(trim((string) $e)))
            ->filter()->unique()->values();

        if ($emails->isEmpty()) {
            throw new RuntimeException('La programación no tiene destinatarios.');
        }

        $archivo = $this->publicador->publicar($reporte, 'pdf');

        $copia = null;
        try {
            $envio = DB::transaction(function () use ($prog, $reporte, $plantilla, $emails, $archivo, &$copia) {
                $envio = CorreoEnvio::create([
                    'plantilla_id' => $plantilla->id,
                    'asunto' => $prog->asunto,
                    'estado' => EstadoEnvioCorreo::Borrador,
                    'variables' => $this->variables($prog, $reporte, null),
                    'creado_por' => null,
                ]);

                foreach ($emails as $email) {
                    $envio->destinatarios()->create([
                        'cliente_id' => $prog->cliente_id,
                        'email' => $email,
                        'estado' => EstadoDestinatarioCorreo::Pendiente,
                    ]);
                }

                // Copia, no referencia: el adjunto queda congelado aunque el Archivo se borre.
                $copia = "correo/envios/{$envio->id}/adjuntos/".Str::uuid().'.pdf';
                Storage::disk('local')->copy($archivo->ruta_archivo, $copia);

                CorreoAdjunto::create([
                    'envio_id' => $envio->id,
                    'nombre' => $archivo->nombre,
                    'ruta' => $copia,
                    'disco' => 'local',
                    'mime' => 'application/pdf',
                    'tamano' => Storage::disk('local')->size($copia),
                    'subido_por' => null,
                ]);

                return $envio;
            });
        } catch (Throwable $e) {
            if ($copia) {
                Storage::disk('local')->delete($copia);
            }

            throw $e;
        }

        return $this->enviador->enviar($envio);
    }

    private function plantillaValida(ReporteProgramacion $prog): CorreoPlantilla
    {
        $plantilla = $prog->plantilla;

        if (! $plantilla) {
            throw new RuntimeException('La plantilla de correo de esta programación ya no existe. Elige otra.');
        }

        return $plantilla;
    }

    /** @return array<string, string> */
    private function variables(ReporteProgramacion $prog, Reporte $reporte, ?string $responsable): array
    {
        $vars = [
            'mes' => $this->mes($reporte->periodo_fin),
            'servicio' => $prog->area->label(),
            'responsable' => $responsable ?: (string) config('app.name'),
        ];

        // En el envío real las de persona las resuelve EnviadorCorreo por destinatario; aquí sirven a la prueba.
        if ($prog->cliente) {
            $vars += Variables::desdeCliente($prog->cliente);
        }

        return $vars;
    }

    private function mes(Carbon $fecha): string
    {
        return self::MESES[$fecha->month].' '.$fecha->year;
    }

    private function registrarFallo(ReporteProgramacion $prog, Throwable $e): void
    {
        $prog->forceFill([
            'ultimo_intento_en' => now(),
            'ultimo_ok' => false,
            'ultimo_resultado' => self::sanear($e->getMessage()),
        ]);
    }
}
