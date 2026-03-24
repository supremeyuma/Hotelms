<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\ActionCodeService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class StaffProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'position',
        'phone',
        'meta',
        'action_code',
        'action_code_hash',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $hidden = ['action_code', 'action_code_hash'];

    protected $appends = ['action_code_plain', 'action_code_configured'];

    public function getActionCodePlainAttribute(): string
    {
        // 1. Check if the value is null or empty first
        if (! $this->usesEncryptedActionCode()) {
            return '';
        }

        try {
            return ActionCodeService::decrypt($this->action_code);
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getActionCodeConfiguredAttribute(): bool
    {
        return $this->hasUsableActionCode();
    }

    public function hasUsableActionCode(): bool
    {
        return $this->usesHashedActionCode() || $this->usesEncryptedActionCode();
    }

    public function matchesActionCode(string $plainCode): bool
    {
        if ($this->usesHashedActionCode()) {
            return Hash::check($plainCode, $this->action_code_hash);
        }

        if ($this->usesEncryptedActionCode()) {
            return hash_equals($this->action_code_plain, $plainCode);
        }

        return false;
    }

    public function storeActionCode(string $plainCode): void
    {
        if (self::hasColumn('action_code_hash')) {
            $this->action_code_hash = Hash::make($plainCode);
        }

        if (self::hasColumn('action_code')) {
            $this->action_code = ActionCodeService::encrypt($plainCode);
        }
    }

    protected function usesHashedActionCode(): bool
    {
        return self::hasColumn('action_code_hash')
            && filled($this->getAttribute('action_code_hash'));
    }

    protected function usesEncryptedActionCode(): bool
    {
        return self::hasColumn('action_code')
            && filled($this->getAttribute('action_code'));
    }

    protected static function hasColumn(string $column): bool
    {
        static $columns = [];

        if (! array_key_exists($column, $columns)) {
            $columns[$column] = Schema::hasColumn('staff_profiles', $column);
        }

        return $columns[$column];
    }

    /* ---------------- Relationships ---------------- */

    // Belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ---------------- Mutators ---------------- */

    // Hash staff action codes

    /* ---------------- Scopes ---------------- */

    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }
}
