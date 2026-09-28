<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['key', 'name', 'group', 'user_type', 'description', 'sort_order'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Permission tanpa jenis pengguna (menu admin) hanya untuk role Admin/Karyawan dan Dosen
     * (dosen boleh merangkap staf, mis. kaprodi); role Mahasiswa tidak pernah mendapatkannya.
     */
    public function isAvailableFor(UserType $type): bool
    {
        return $this->user_type === null ? $type !== UserType::Mahasiswa : $this->user_type === $type;
    }

    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'sort_order' => 'integer',
        ];
    }
}
