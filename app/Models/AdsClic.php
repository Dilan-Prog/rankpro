<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Evento de clic write-once — sin updated_at (no aplica $timestamps por defecto). */
class AdsClic extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'ads_clics';

    protected $fillable = [
        'cliente_id',
        'ads_campana_id',
        'visitor_id',
        'gclid',
        'gbraid',
        'wbraid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_url',
        'referrer',
        'user_agent',
        'ip_address',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function adsCampana(): BelongsTo
    {
        return $this->belongsTo(AdsCampana::class);
    }

    public function conversiones(): HasMany
    {
        return $this->hasMany(AdsConversion::class, 'ads_clic_id');
    }
}
