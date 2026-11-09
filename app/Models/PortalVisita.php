<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/** Una fila por petición al portal con un enlace válido (auditoría). */
class PortalVisita extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'portal_visitas';

    protected $fillable = ['portal_acceso_id', 'ruta', 'ip_hash', 'agente'];

    protected $hidden = ['ip_hash'];

    protected $casts = ['created_at' => 'datetime'];

    public function acceso(): BelongsTo
    {
        return $this->belongsTo(PortalAcceso::class, 'portal_acceso_id');
    }

    /** `/portal/<token>/reportes` pasa a `/reportes`: el token no debe quedar en la base. */
    public static function rutaSinToken(Request $request): string
    {
        $ruta = '/'.ltrim((string) preg_replace('~^portal/[^/]*~', '', $request->path()), '/');

        return mb_substr($ruta, 0, 190);
    }
}
