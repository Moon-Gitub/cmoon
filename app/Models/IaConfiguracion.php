<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IaConfiguracion extends Model
{
    protected $table = 'ia_configuracion';

    protected $fillable = [
        'provider',
        'api_key',
        'base_url',
        'model',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'activo' => 'boolean',
        ];
    }

    public static function actual(): self
    {
        return static::query()->firstOrCreate([], [
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'activo' => true,
        ]);
    }
}
