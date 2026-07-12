<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class IntegracionesController extends Controller
{
    public function index(): View
    {
        $integraciones = [
            ['name' => 'Google Ads API', 'desc' => 'Sincronización automática de campañas', 'icon' => 'fa-google', 'brand' => true],
            ['name' => 'Google Analytics 4', 'desc' => 'Tráfico, conversiones y audiencias', 'icon' => 'fa-chart-line', 'brand' => false],
            ['name' => 'Meta Ads API', 'desc' => 'Facebook e Instagram Ads', 'icon' => 'fa-facebook', 'brand' => true],
            ['name' => 'Google Search Console', 'desc' => 'Posiciones, clics e indexación', 'icon' => 'fa-magnifying-glass', 'brand' => false],
            ['name' => 'Ahrefs', 'desc' => 'Backlinks y análisis de competidores', 'icon' => 'fa-link', 'brand' => false],
            ['name' => 'Semrush', 'desc' => 'Auditoría técnica y keyword research', 'icon' => 'fa-satellite-dish', 'brand' => false],
            ['name' => 'HubSpot CRM', 'desc' => 'Sincronización de contactos y pipeline', 'icon' => 'fa-handshake', 'brand' => false],
            ['name' => 'Slack', 'desc' => 'Notificaciones y alertas en tiempo real', 'icon' => 'fa-slack', 'brand' => true],
        ];

        return view('admin.integraciones.index', [
            'pageTitle' => 'Integraciones',
            'integraciones' => $integraciones,
        ]);
    }
}
