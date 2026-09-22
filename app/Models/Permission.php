<?php

namespace App\Models;

use App\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['key', 'name', 'group', 'user_type', 'description', 'sort_order'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isAvailableFor(UserType $type): bool
    {
        return $this->user_type === null || $this->user_type === $type;
    }

    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'sort_order' => 'integer',
        ];
    }
}
