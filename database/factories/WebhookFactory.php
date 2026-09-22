<?php

namespace Database\Factories;

use App\Support\Webhooks\Eventos;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebhookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Webhook '.$this->faker->unique()->word(),
            'url' => $this->faker->url(),
            'secreto' => Str::random(48),
            'eventos' => ['ping'],
            'activo' => true,
        ];
    }
}
