<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type UserType = 'admin' | 'dosen' | 'mahasiswa';
type PermissionItem = { id: number; key: string; name: string; description: string | null; user_type: UserType | null };
type PermissionGroup = { group: string; permissions: PermissionItem[] };
type RoleData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    user_type: UserType;
    is_system: boolean;
    users_count: number;
    permissions: number[];
};

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    role: RoleData | null;
    userTypes: { value: UserType; label: string }[];
    permissionGroups: PermissionGroup[];
    typeLocked: boolean;
    lockedPermissions: number[];
}>();

const title = `${props.role ? 'Edit' : 'Tambah'} Role`;

const form = useForm<{ name: string; description: string; user_type: UserType; permissions: number[] }>({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    user_type: props.role?.user_type ?? 'admin',
    permissions: [...(props.role?.permissions ?? [])],
});

const typeLabel = (type: UserType | null) => props.userTypes.find((item) => item.value === type)?.label ?? '';

const isAvailable = (permission: PermissionItem) => permission.user_type === null || permission.user_type === form.user_type;
const isLocked = (permission: PermissionItem) => props.lockedPermissions.includes(permission.id);
const isChecked = (permission: PermissionItem) => form.permissions.includes(permission.id);

const toggle = (permission: PermissionItem, checked: boolean) => {
    if (!isAvailable(permission) || isLocked(permission)) return;
    form.permissions = checked ? [...new Set([...form.permissions, permission.id])] : form.permissions.filter((id) => id !== permission.id);
};

const selectablePermissions = (group: PermissionGroup) => group.permissions.filter((permission) => isAvailable(permission) && !isLocked(permission));
const isGroupFullySelected = (group: PermissionGroup) => {
    const available = group.permissions.filter(isAvailable);

    return available.length > 0 && available.every(isChecked);
};
const hasSelectable = (group: PermissionGroup) => selectablePermissions(group).length > 0;
const toggleGroup = (group: PermissionGroup) => {
    const selectable = selectablePermissions(group);
    const ids = selectable.map((permission) => permission.id);
    form.permissions = isGroupFullySelected(group) ? form.permissions.filter((id) => !ids.includes(id)) : [...new Set([...form.permissions, ...ids])];
};

// Hak akses khusus jenis pengguna lain otomatis dilepas saat jenis pengguna diganti.
watch(
    () => form.user_type,
    () => {
        const allPermissions = props.permissionGroups.flatMap((group) => group.permissions);
        form.permissions = form.permissions.filter((id) => {
            const permission = allPermissions.find((item) => item.id === id);

            return permission ? isAvailable(permission) : false;
        });
    },
);

const selectedCount = computed(() => form.permissions.length);

const submit = () => (props.role ? form.put(route('admin.roles.update', props.role.id)) : form.post(route('admin.roles.store')));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] disabled:cursor-not-allowed disabled:bg-[#f6f5f4] disabled:text-[#615d59]';
</script>

<template>
    <Head :title="title" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Kelola Role', href: route('admin.roles.index') },
            { title, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Tentukan identitas role dan menu atau fitur yang boleh diakses penggunanya.
                        </p>
                    </div>
                    <Link :href="route('admin.roles.index')"
                        ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                    >
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01)]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Role</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name" class="text-sm font-medium text-black">Nama Role</Label>
                                <Input id="name" v-model="form.name" type="text" :class="inp" placeholder="Contoh: Staf Akademik" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="user_type" class="text-sm font-medium text-black">Jenis Pengguna</Label>
                                <select id="user_type" v-model="form.user_type" :class="sel" :disabled="props.typeLocked" required>
                                    <option v-for="type in props.userTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                                <p class="text-xs leading-4 text-[#615d59]">
                                    <template v-if="props.typeLocked">
                                        {{
                                            props.role?.is_system
                                                ? 'Jenis pengguna role bawaan tidak dapat diubah.'
                                                : 'Tidak dapat diubah karena role sedang digunakan.'
                                        }}
                                    </template>
                                    <template v-else>Menentukan data profil pengguna dan hak akses yang tersedia.</template>
                                </p>
                                <InputError :message="form.errors.user_type" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="description" class="text-sm font-medium text-black">Deskripsi</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Jelaskan tugas atau tanggung jawab role ini (opsional)"
                                class="rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </section>

                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Hak Akses</h2>
                                <p class="mt-1 text-sm leading-5 text-[#615d59]">Centang menu dan fitur yang dapat diakses oleh role ini.</p>
                            </div>
                            <p class="text-sm text-[#615d59]">
                                <span class="font-medium text-black">{{ selectedCount }}</span> dipilih
                            </p>
                        </div>
                        <p
                            v-if="props.role && props.role.users_count > 0"
                            class="mt-3 rounded-lg bg-[#f6f5f4] px-3 py-2 text-sm leading-5 text-[#31302e]"
                        >
                            Perubahan hak akses langsung berlaku untuk {{ props.role.users_count }} pengguna dengan role ini.
                        </p>
                        <InputError class="mt-3" :message="form.errors.permissions" />

                        <div class="mt-4 space-y-5">
                            <fieldset
                                v-for="group in props.permissionGroups"
                                :key="group.group"
                                class="overflow-hidden rounded-xl border border-[#e6e6e6]"
                            >
                                <legend class="sr-only">{{ group.group }}</legend>
                                <div class="flex items-center justify-between gap-3 border-b border-[#e6e6e6] bg-[#f6f5f4] px-4 py-2.5">
                                    <span class="text-sm font-semibold text-black">{{ group.group }}</span>
                                    <button
                                        v-if="hasSelectable(group)"
                                        type="button"
                                        class="text-sm font-medium text-[#0075de] hover:underline"
                                        @click="toggleGroup(group)"
                                    >
                                        {{ isGroupFullySelected(group) ? 'Hapus semua' : 'Pilih semua' }}
                                    </button>
                                </div>
                                <div class="grid sm:grid-cols-2">
                                    <label
                                        v-for="permission in group.permissions"
                                        :key="permission.id"
                                        class="flex gap-3 border-t border-[#e6e6e6] bg-white px-4 py-3 first:border-t-0 sm:odd:border-r sm:[&:nth-child(2)]:border-t-0"
                                        :class="
                                            isAvailable(permission) && !isLocked(permission)
                                                ? 'cursor-pointer hover:bg-[#f6f5f4]/60'
                                                : 'cursor-not-allowed'
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 size-4 shrink-0 accent-[#0075de]"
                                            :checked="isChecked(permission)"
                                            :disabled="!isAvailable(permission) || isLocked(permission)"
                                            @change="toggle(permission, ($event.target as HTMLInputElement).checked)"
                                        />
                                        <span class="min-w-0" :class="isAvailable(permission) ? '' : 'opacity-50'">
                                            <span class="block text-[15px] font-medium leading-5 text-black">{{ permission.name }}</span>
                                            <span v-if="permission.description" class="mt-0.5 block text-sm leading-5 text-[#615d59]">{{
                                                permission.description
                                            }}</span>
                                            <span v-if="!isAvailable(permission)" class="mt-1 block text-xs leading-4 text-[#a39e98]">
                                                Khusus role {{ typeLabel(permission.user_type) }}
                                            </span>
                                            <span v-else-if="isLocked(permission)" class="mt-1 block text-xs leading-4 text-[#a39e98]">
                                                Wajib aktif agar Admin tidak kehilangan akses
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>
                        </div>
                    </section>

                    <div class="flex justify-end pt-2">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
