<?php

namespace App\Enums;

enum TipoServicio: string
{
    case Seo = 'seo';
    case GoogleAds = 'google_ads';
    case MetaAds = 'meta_ads';
    case TiktokAds = 'tiktok_ads';
    case Rediseno = 'rediseno';
    case Software = 'software';
    case Automatizacion = 'automatizacion';
}
