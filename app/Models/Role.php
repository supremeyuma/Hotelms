<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    // A role has many users
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /* ---------------- Scopes ---------------- */

    // Scope: Filter by slug
    public function scopeSlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }
}
