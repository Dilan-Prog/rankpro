<?php

namespace App\Providers;

use App\Events\EventoWebhook;
use App\Listeners\EmitirWebhook;
use App\Models\{AdsClic, AdsConversion, Archivo, Bug, Cliente, CorreoEnvio, CorreoEvento, Finanza, KeywordMedicion, Propuesta, Reporte, Servicio, Tarea};
use App\Observers\{AdsClicObserver, AdsConversionObserver, ArchivoObserver, BugObserver, ClienteObserver, CorreoEnvioObserver, CorreoEventoObserver, FinanzaObserver, KeywordMedicionObserver, PropuestaObserver, ReporteObserver, ServicioObserver, TareaObserver};
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        EventoWebhook::class => [
            EmitirWebhook::class,
        ],
    ];

    /**
     * Modelos observados para emitir webhooks de "creado"/cambios de estado.
     * Ver App\Support\Webhooks\Eventos para el catálogo completo; las
     * acciones de negocio (fase.aprobada, correo.enviado por lote) se emiten
     * explícitamente desde los servicios, no desde un Observer.
     *
     * @var array<class-string, class-string>
     */
    protected $observers = [
        Cliente::class => [ClienteObserver::class],
        Servicio::class => [ServicioObserver::class],
        Finanza::class => [FinanzaObserver::class],
        Propuesta::class => [PropuestaObserver::class],
        Reporte::class => [ReporteObserver::class],
        Tarea::class => [TareaObserver::class],
        Bug::class => [BugObserver::class],
        Archivo::class => [ArchivoObserver::class],
        KeywordMedicion::class => [KeywordMedicionObserver::class],
        AdsClic::class => [AdsClicObserver::class],
        AdsConversion::class => [AdsConversionObserver::class],
        CorreoEvento::class => [CorreoEventoObserver::class],
        CorreoEnvio::class => [CorreoEnvioObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
