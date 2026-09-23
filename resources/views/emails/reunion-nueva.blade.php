<table width="100%" cellpadding="0" cellspacing="0" style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 560px;">
    <tr>
        <td style="padding: 24px 0; font-size: 20px; font-weight: bold;">
            Nueva reunión agendada
        </td>
    </tr>
    <tr>
        <td style="padding: 16px; background-color: #f3f4f6; border-radius: 8px; font-size: 15px;">
            <strong>{{ $reunion->inicia_en->translatedFormat('l j \d\e F, Y') }}</strong><br>
            {{ $reunion->inicia_en->format('H:i') }} - {{ $reunion->termina_en->format('H:i') }}
        </td>
    </tr>
    <tr>
        <td style="padding-top: 16px; font-size: 14px; line-height: 1.5;">
            <strong>Nombre:</strong> {{ $reunion->nombre }}<br>
            <strong>Email:</strong> {{ $reunion->email }}<br>
            @if ($reunion->telefono)
                <strong>Teléfono:</strong> {{ $reunion->telefono }}<br>
            @endif
            @if ($reunion->notas)
                <strong>Notas:</strong> {{ $reunion->notas }}<br>
            @endif
            @if ($reunion->cliente_id)
                <strong>Cliente vinculado:</strong> #{{ $reunion->cliente_id }}<br>
            @endif
        </td>
    </tr>
</table>
