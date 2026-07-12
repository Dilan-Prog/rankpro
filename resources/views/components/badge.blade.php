{{--
    Status badge. Usage: <x-badge status="active" /> or <x-badge :status="$cliente->estado" />
    (accepts either a plain string or a backed enum — both resolve via ->value).
    The status => [label, class] map below is the single source of truth
    for status labels/colors across every module. Includes the English
    keys used by the Dashboard's placeholder data and the Spanish enum
    values used by the real database schema (clientes, proyectos, ...).
--}}
@props(['status'])
@php
    $key = $status instanceof \BackedEnum ? $status->value : $status;

    $map = [
        // English (legacy dashboard placeholder data)
        'active'      => ['Activo',      'badge--success'],
        'paused'      => ['Pausado',     'badge--warning'],
        'cancelled'   => ['Cancelado',   'badge--danger'],
        'in_progress' => ['En Progreso', 'badge--info'],
        'completed'   => ['Completado',  'badge--success'],
        'pending'     => ['Pendiente',   'badge--warning'],
        'paid'        => ['Pagado',      'badge--success'],
        'overdue'     => ['Vencido',     'badge--danger'],
        'open'        => ['Abierto',     'badge--orange'],
        'resolved'    => ['Resuelto',    'badge--success'],
        'in_use'      => ['En Uso',      'badge--primary'],
        'tracking'    => ['Tracking',    'badge--info'],
        'discarded'   => ['Descartado',  'badge--neutral'],
        'high'        => ['Alta',        'badge--danger'],
        'medium'      => ['Media',       'badge--warning'],
        'low'         => ['Baja',        'badge--success'],

        // Spanish (clientes / servicios)
        'activo'      => ['Activo',      'badge--success'],
        'pausado'     => ['Pausado',     'badge--warning'],
        'cancelado'   => ['Cancelado',   'badge--danger'],

        // Spanish (campañas: seo_campanas / ads_campanas)
        'activa'      => ['Activa',      'badge--success'],
        'pausada'     => ['Pausada',     'badge--warning'],
        'finalizada'  => ['Finalizada',  'badge--neutral'],

        // Spanish (tareas)
        'pendiente'   => ['Pendiente',   'badge--warning'],
        'en_progreso' => ['En Progreso', 'badge--info'],
        'completada'  => ['Completada',  'badge--success'],

        // Spanish (bugs)
        'abierto'     => ['Abierto',     'badge--orange'],
        'resuelto'    => ['Resuelto',    'badge--success'],

        // Spanish (finanzas)
        'pagado'      => ['Pagado',      'badge--success'],
        'vencido'     => ['Vencido',     'badge--danger'],

        // Spanish (keywords)
        'en_uso'      => ['En Uso',      'badge--primary'],
        'seguimiento' => ['Seguimiento', 'badge--info'],
        'descartada'  => ['Descartada',  'badge--neutral'],

        // Spanish (backlinks)
        'caido'       => ['Caído',       'badge--danger'],

        // Spanish (prioridad: tareas / bugs)
        'alta'        => ['Alta',        'badge--danger'],
        'media'       => ['Media',       'badge--warning'],
        'baja'        => ['Baja',        'badge--success'],

        // Spanish (proyectos — fases, legacy SDLC-stage values kept for old data)
        'briefing'      => ['Briefing',      'badge--neutral'],
        'disenio'       => ['Diseño',        'badge--info'],
        'frontend'      => ['Frontend',      'badge--primary'],
        'backend'       => ['Backend',       'badge--primary'],
        'qa'            => ['QA Testing',    'badge--warning'],
        'lanzamiento'   => ['Lanzamiento',   'badge--success'],
        'mantenimiento' => ['Mantenimiento', 'badge--info'],

        // Spanish (proyectos — Proceso Administrativo: fase_actual / estado)
        'planeacion'    => ['Planeación',    'badge--info'],
        'organizacion'  => ['Organización',  'badge--primary'],
        'direccion'     => ['Dirección',     'badge--warning'],
        'control'       => ['Control',       'badge--orange'],
        'cerrado'       => ['Cerrado',       'badge--success'],

        // Spanish (proyecto_qa — resultado)
        'aprobado'      => ['Aprobado',      'badge--success'],
        'fallido'       => ['Fallido',       'badge--danger'],

        // Spanish (seo_campanas — Proceso Administrativo: fase_actual)
        'auditoria'     => ['Auditoría',     'badge--info'],
        'estrategia'    => ['Estrategia',    'badge--primary'],
        'ejecucion'     => ['Ejecución',     'badge--warning'],
        'reporte'       => ['Reporte',       'badge--orange'],
    ];
    [$label, $class] = $map[$key] ?? [$key, 'badge--neutral'];
@endphp
<span {{ $attributes->merge(['class' => "badge {$class}"]) }}>{{ $label }}</span>
