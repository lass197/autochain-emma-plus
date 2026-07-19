<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockchainSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("blockchain_setting_{$key}", 300, function () use ($key, $default) {
            return static::query()->where('key', $key)->value('value') ?? $default;
        });
    }

    public static function setValue(string $key, ?string $value, ?string $description = null): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => $value,
                'description' => $description,
            ], fn ($v) => $v !== null)
        );

        Cache::forget("blockchain_setting_{$key}");

        return $setting;
    }
}
