<?php

namespace App\Console\Commands;

use App\Models\WebhookEntrega;
use App\Services\Webhooks\Despachador;
use Illuminate\Console\Command;

class ReintentarWebhooks extends Command
{
    protected $signature = 'webhooks:reintentar';

    protected $description = 'Reintenta las entregas de webhook pendientes cuyo próximo intento ya venció';

    public function handle(Despachador $despachador): int
    {
        $entregas = WebhookEntrega::vencidas()->limit(50)->get();

        foreach ($entregas as $entrega) {
            $despachador->entregar($entrega);
        }

        $this->info('Procesados: '.$entregas->count());

        return self::SUCCESS;
    }
}
