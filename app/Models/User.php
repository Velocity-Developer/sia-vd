<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Notifications\AturUlangKataSandi;
use App\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SerializesDatesInAppTimezone;

    /**
     * Dashboard yang dicoba saat menentukan halaman awal user. Nama route sama dengan key permission-nya.
     *
     * @var list<string>
     */
    private const HOME_ROUTES = ['admin.dashboard', 'dosen.dashboard', 'mahasiswa.dashboard'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class);
    }

    public function dosenProfile(): HasOne
    {
        return $this->hasOne(DosenProfile::class);
    }

    public function mahasiswaProfile(): HasOne
    {
        return $this->hasOne(MahasiswaProfile::class);
    }

    public function profile(): HasOne
    {
        return match ($this->type()) {
            UserType::Admin => $this->adminProfile(),
            UserType::Dosen => $this->dosenProfile(),
            UserType::Mahasiswa => $this->mahasiswaProfile(),
            default => $this->hasOne(UserProfile::class),
        };
    }

    /**
     * Jenis pengguna mengikuti role yang dimiliki.
     */
    public function type(): ?UserType
    {
        return $this->role?->user_type;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, UserType $type): void
    {
        $query->whereHas('role', fn (Builder $role) => $role->where('user_type', $type));
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        $this->loadMissing('role.permissions');

        return $this->role?->permissionKeys() ?? [];
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionKeys(), true);
    }

    /**
     * Role boleh diberikan oleh user ini bila seluruh hak aksesnya juga dimiliki user ini,
     * kecuali user pemegang Kelola Role yang boleh memberikan role apa pun.
     */
    public function canAssignRole(Role $role): bool
    {
        return $this->hasPermission(Role::SUPER_PERMISSION)
            || array_diff($role->permissionKeys(), $this->permissionKeys()) === [];
    }

    /**
     * User lain hanya boleh diubah/dihapus bila haknya tidak melebihi hak user ini.
     */
    public function canManage(User $target): bool
    {
        return $target->role === null || $this->canAssignRole($target->role);
    }

    /**
     * Kelompok route Ziggy yang dikirim ke browser untuk user ini (lihat config/ziggy.php).
     * Pemegang izin admin/dosen mendapat daftar lengkap; selain itu cukup route mahasiswa.
     */
    public function grupRute(): string
    {
        foreach ($this->permissionKeys() as $key) {
            if (str_starts_with($key, 'admin.') || str_starts_with($key, 'dosen.')) {
                return 'staf';
            }
        }

        return 'mahasiswa';
    }

    /**
     * Apakah user ini satu-satunya yang masih memegang akses Kelola Role.
     */
    public function isLastRoleManager(): bool
    {
        return $this->hasPermission(Role::SUPER_PERMISSION)
            && static::query()
                ->whereKeyNot($this->id)
                ->whereHas('role.permissions', fn ($query) => $query->where('key', Role::SUPER_PERMISSION))
                ->doesntExist();
    }

    /**
     * Nama route halaman awal setelah login, berdasarkan permission yang dimiliki.
     */
    public function homeRoute(): string
    {
        $preferred = $this->type() ? $this->type()->value.'.dashboard' : null;
        $candidates = array_unique(array_filter([$preferred, ...self::HOME_ROUTES]));

        foreach ($candidates as $route) {
            if ($this->hasPermission($route)) {
                return $route;
            }
        }

        return 'dashboard';
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Surel tautan atur ulang kata sandi versi sendiri (berbahasa Indonesia).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new AturUlangKataSandi($token));
    }
}
