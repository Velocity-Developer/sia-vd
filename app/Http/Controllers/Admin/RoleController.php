<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\UserType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'user_type' => $role->user_type->value,
                'user_type_label' => $role->user_type->label(),
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
            ]);

        return Inertia::render('Admin/Roles', ['roles' => $roles, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/RoleForm', $this->formProps(null));
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Admin/RoleForm', $this->formProps($role));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        try {
            DB::transaction(function () use ($data): void {
                $role = Role::create([
                    'name' => $data['name'],
                    'slug' => $this->uniqueSlug($data['name']),
                    'user_type' => $data['user_type'],
                    'description' => $data['description'] ?? null,
                    'is_system' => false,
                ]);
                $role->permissions()->sync($data['permissions']);
            });
        } catch (Throwable) {
            return to_route('admin.roles.index')->with('error', 'Role gagal ditambahkan.');
        }

        return to_route('admin.roles.index')->with('success', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validated($request, $role);

        try {
            DB::transaction(function () use ($role, $data): void {
                $role->update([
                    'name' => $data['name'],
                    'user_type' => $data['user_type'],
                    'description' => $data['description'] ?? null,
                ]);
                $role->permissions()->sync($data['permissions']);
            });
        } catch (Throwable) {
            return to_route('admin.roles.index')->with('error', 'Role gagal diperbarui.');
        }

        return to_route('admin.roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return to_route('admin.roles.index')->with('error', "Role {$role->name} adalah role bawaan sistem dan tidak dapat dihapus.");
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return to_route('admin.roles.index')->with('error', "Role {$role->name} masih digunakan oleh {$usersCount} pengguna. Pindahkan pengguna ke role lain sebelum menghapus.");
        }

        try {
            DB::transaction(fn (): ?bool => $role->delete());
        } catch (Throwable) {
            return to_route('admin.roles.index')->with('error', 'Role gagal dihapus.');
        }

        return to_route('admin.roles.index')->with('success', 'Role berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Role $role): array
    {
        $role?->load('permissions:id')->loadCount('users');

        return [
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'user_type' => $role->user_type->value,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('id')->all(),
            ] : null,
            'userTypes' => UserType::options(),
            'permissionGroups' => $this->permissionGroups(),
            'typeLocked' => $role !== null && ($role->is_system || $role->users_count > 0),
            'lockedPermissions' => $role ? $this->lockedPermissionIds($role) : [],
        ];
    }

    /**
     * @return list<array{group: string, permissions: list<array{id: int, key: string, name: string, description: ?string, user_type: ?string}>}>
     */
    private function permissionGroups(): array
    {
        return Permission::query()->orderBy('sort_order')->get()
            ->groupBy('group')
            ->map(fn (Collection $permissions, string $group): array => [
                'group' => $group,
                'permissions' => $permissions->map(fn (Permission $permission): array => [
                    'id' => $permission->id,
                    'key' => $permission->key,
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'user_type' => $permission->user_type?->value,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Permission yang tidak boleh dicabut dari role agar Admin tidak kehilangan akses ke halaman Kelola Role.
     *
     * @return list<int>
     */
    private function lockedPermissionIds(Role $role): array
    {
        $isOwnRole = request()->user()?->role_id === $role->id;
        $isSystemAdmin = $role->is_system && $role->user_type === UserType::Admin;

        if (! $isOwnRole && ! $isSystemAdmin) {
            return [];
        }

        return Permission::query()->where('key', Role::SUPER_PERMISSION)->pluck('id')->all();
    }

    /**
     * @return array{name: string, user_type: string, description?: ?string, permissions: list<int>}
     *
     * @throws ValidationException
     */
    private function validated(Request $request, ?Role $role): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:1000'],
            'user_type' => ['required', Rule::enum(UserType::class)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ], $this->messages(), $this->attributes());

        $type = UserType::from($data['user_type']);
        $permissionIds = array_map('intval', $data['permissions']);

        if ($role !== null && $type !== $role->user_type && ($role->is_system || $role->isInUse())) {
            throw ValidationException::withMessages([
                'user_type' => $role->is_system
                    ? 'Jenis pengguna role bawaan sistem tidak dapat diubah.'
                    : 'Jenis pengguna tidak dapat diubah karena role masih digunakan oleh pengguna.',
            ]);
        }

        $incompatible = Permission::query()->whereIn('id', $permissionIds)->get()
            ->reject(fn (Permission $permission): bool => $permission->isAvailableFor($type));
        if ($incompatible->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'Hak akses '.$incompatible->pluck('name')->join(', ', ' dan ').' tidak sesuai dengan jenis pengguna '.$type->label().'.',
            ]);
        }

        $locked = $role ? $this->lockedPermissionIds($role) : [];
        if (array_diff($locked, $permissionIds) !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'Hak akses Kelola Role tidak dapat dicabut dari role ini agar Admin tidak kehilangan akses.',
            ]);
        }

        $data['permissions'] = $permissionIds;

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $suffix = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'present' => ':attribute wajib dikirim.',
            'string' => ':attribute harus berupa teks.',
            'array' => ':attribute tidak valid.',
            'integer' => ':attribute tidak valid.',
            'distinct' => ':attribute tidak boleh duplikat.',
            'unique' => ':attribute sudah digunakan.',
            'exists' => ':attribute tidak ditemukan.',
            'enum' => 'Pilihan :attribute tidak valid.',
            'max.string' => ':attribute maksimal :max karakter.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'name' => 'Nama Role',
            'description' => 'Deskripsi',
            'user_type' => 'Jenis Pengguna',
            'permissions' => 'Hak Akses',
            'permissions.*' => 'Hak Akses',
        ];
    }
}
