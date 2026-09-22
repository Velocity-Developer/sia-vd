<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'user_type' => UserType::Admin,
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function type(UserType $type): static
    {
        return $this->state(fn (): array => ['user_type' => $type]);
    }

    /**
     * @param  list<string>  $keys
     */
    public function withPermissions(array $keys): static
    {
        return $this->afterCreating(fn (Role $role) => $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id')));
    }
}
