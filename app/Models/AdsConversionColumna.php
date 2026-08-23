<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdsConversionColumna extends Model
{
    use HasFactory;

    protected $table = 'ads_conversion_columnas';

    protected $fillable = [
        'nombre',
    ];
}
