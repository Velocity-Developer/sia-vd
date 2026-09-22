<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\UserType;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, SerializesDatesInAppTimezone;

    public const SUPER_PERMISSION = 'admin.roles';

    protected $fillable = ['name', 'slug', 'user_type', 'description', 'is_system'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->orderBy('sort_order');
    }

    /**
     * Role bawaan (sistem) untuk jenis pengguna tertentu.
     */
    public static function system(UserType $type): self
    {
        return static::query()->where('is_system', true)->where('user_type', $type)->orderBy('id')->firstOrFail();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, UserType $type): void
    {
        $query->where('user_type', $type);
    }

    /**
     * Key permission yang aktif untuk role ini (hanya yang sesuai jenis pengguna role).
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->permissions
            ->filter(fn (Permission $permission): bool => $permission->user_type === null || $permission->user_type === $this->user_type)
            ->pluck('key')
            ->values()
            ->all();
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionKeys(), true);
    }

    public function isInUse(): bool
    {
        return $this->users()->exists();
    }

    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'is_system' => 'boolean',
        ];
    }
}
