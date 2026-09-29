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

// Sama dengan Permission::isAvailableFor: menu admin (tanpa jenis) terlarang untuk role Mahasiswa.
const isAvailable = (permission: PermissionItem) =>
    permission.user_type === null ? form.user_type !== 'mahasiswa' : permission.user_type === form.user_type;
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
</script>

<template>
    <Head :title="title" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Kelola Role', href: route('admin.roles.index') },
            { title, href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Tentukan identitas role dan menu atau fitur yang boleh diakses penggunanya.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.roles.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Role</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name" class="label-isian">Nama Role</Label>
                                <Input id="name" v-model="form.name" type="text" placeholder="Contoh: Staf Akademik" required />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="user_type" class="label-isian">Jenis Pengguna</Label>
                                <select id="user_type" v-model="form.user_type" class="isian isian-pilih" :disabled="props.typeLocked" required>
                                    <option v-for="type in props.userTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                                <p class="teks-bantu">
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
                            <Label for="description" class="label-isian">Deskripsi</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                placeholder="Jelaskan tugas atau tanggung jawab role ini (opsional)"
                                class="isian isian-area"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h2 class="judul-bagian">Hak Akses</h2>
                                <p class="teks-bantu mt-1">Centang menu dan fitur yang dapat diakses oleh role ini.</p>
                            </div>
                            <p class="info-jumlah">
                                <span class="font-medium text-black">{{ selectedCount }}</span> dipilih
                            </p>
                        </div>
                        <p v-if="props.role && props.role.users_count > 0" class="alert-info mt-3">
                            Perubahan hak akses langsung berlaku untuk {{ props.role.users_count }} pengguna dengan role ini.
                        </p>
                        <InputError class="mt-3" :message="form.errors.permissions" />

                        <div class="mt-4 space-y-5">
                            <fieldset
                                v-for="group in props.permissionGroups"
                                :key="group.group"
                                class="overflow-hidden rounded-xl border border-[#e6e6e6] dark:border-border"
                            >
                                <legend class="sr-only">{{ group.group }}</legend>
                                <div
                                    class="flex items-center justify-between gap-3 border-b border-[#e6e6e6] bg-[#f6f5f4] px-4 py-2.5 dark:border-border dark:bg-muted"
                                >
                                    <span class="text-sm font-semibold text-black dark:text-foreground">{{ group.group }}</span>
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
                                        class="flex gap-3 border-t border-[#e6e6e6] bg-white px-4 py-3 first:border-t-0 dark:border-border dark:bg-card sm:odd:border-r sm:[&:nth-child(2)]:border-t-0"
                                        :class="
                                            isAvailable(permission) && !isLocked(permission)
                                                ? 'cursor-pointer hover:bg-[#fbfaf9] dark:hover:bg-accent/40'
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
                                            <span class="block text-sm font-medium text-black dark:text-foreground">{{ permission.name }}</span>
                                            <span
                                                v-if="permission.description"
                                                class="mt-0.5 block text-sm text-[#615d59] dark:text-muted-foreground"
                                                >{{ permission.description }}</span
                                            >
                                            <span v-if="!isAvailable(permission)" class="teks-bantu mt-1 block">
                                                {{
                                                    permission.user_type
                                                        ? `Khusus role ${typeLabel(permission.user_type)}`
                                                        : `Khusus role ${typeLabel('admin')} dan ${typeLabel('dosen')}`
                                                }}
                                            </span>
                                            <span v-else-if="isLocked(permission)" class="teks-bantu mt-1 block">
                                                Wajib aktif agar Admin tidak kehilangan akses
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
