<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'meta',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * The 'meta' column should be cast to an array/object when retrieved.
     * @var array
     */

    protected $casts = [
        'meta' => 'array',
    ];

   // --- Helper Methods for Data Access ---

    /**
     * Retrieve a setting value by its key.
     *
     * @param string $key The key of the setting (e.g., 'hotel_name')
     * @param mixed $default The default value if the key is not found
     * @return mixed
     */
    public static function get(string $key, $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        // If found, return the value, otherwise return the default
        if ($setting) {
            // Attempt to return the parsed value if it was saved as JSON/serialized
            // Otherwise, return the raw string value
            return is_null($setting->value) ? null : $setting->value;
        }

        return $default;
    }

    /**
     * Set or update a setting value by its key.
     *
     * @param string $key The key of the setting
     * @param mixed $value The value to store
     * @param array|null $meta Optional metadata
     * @return static
     */
    public static function set(string $key, mixed $value, array $meta = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'meta' => $meta,
            ]
        );
    }
}