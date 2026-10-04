<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, FileSpreadsheet, Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type User = {
    id: number;
    name: string;
    username: string;
    email: string;
    role_name?: string | null;
    profile?: { nomor_induk?: string | null; nidn?: string | null; nim?: string | null } | null;
};
type Pagination = {
    data: User[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
};

const props = defineProps<{ title: string; type: string; users: Pagination; search?: string; angkatan?: number | null; angkatans?: number[] }>();

const search = ref(props.search ?? '');
const angkatan = ref<number | string>(props.angkatan ?? 'all');
const applyFilters = () =>
    router.get(
        route(`admin.users.${props.type}`),
        { search: search.value, angkatan: angkatan.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, applyFilters);

const searchPlaceholder: Record<string, string> = {
    dosen: 'Cari nama atau NIDN',
    mahasiswa: 'Cari nama atau NIM',
    karyawan: 'Cari nama atau Nomor Induk',
};
const idLabel: Record<string, string> = { dosen: 'NIDN', mahasiswa: 'NIM', karyawan: 'Nomor Induk' };
const createLabel: Record<string, string> = { dosen: 'Tambah Dosen', mahasiswa: 'Tambah Mahasiswa', karyawan: 'Tambah Karyawan' };
const subtitle: Record<string, string> = {
    dosen: 'Kelola data dosen dan home base program studi.',
    mahasiswa: 'Kelola data mahasiswa, dosen wali, dan informasi orang tua.',
    karyawan: 'Kelola akun staf/karyawan: nomor induk, data diri, dan role yang menentukan menu yang bisa dibuka.',
};
const idValue = (user: User): string => user.profile?.nidn ?? user.profile?.nim ?? user.profile?.nomor_induk ?? '-';

const confirmOpen = ref(false);
const pendingUser = ref<User | null>(null);

const remove = (user: User) => {
    pendingUser.value = user;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingUser.value) return;
    router.delete(route(`admin.users.${props.type}.destroy`, pendingUser.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingUser.value = null;
        },
    });
};
</script>

<template>
    <Head :title="props.title" />
    <AppLayout :breadcrumbs="[{ title: props.title, href: '#' }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.title }}</h1>
                        <p class="deskripsi-halaman">{{ subtitle[props.type] ?? '' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="props.type !== 'karyawan'" as-child variant="outline">
                            <Link :href="route('admin.impor.index', props.type)"><FileSpreadsheet /> Impor Excel</Link>
                        </Button>
                        <Button as-child
                            ><Link :href="route(`admin.users.${props.type}.create`)"> {{ createLabel[props.type] }} </Link></Button
                        >
                    </div>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" :placeholder="searchPlaceholder[props.type]" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-if="props.type === 'mahasiswa'" v-model="angkatan" label="Filter angkatan" @change="applyFilters">
                        <option value="all">Semua Angkatan</option>
                        <option v-for="item in props.angkatans" :key="item" :value="item">{{ item }}</option>
                    </SelectFilter>

                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.users.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[920px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Nama</th>
                                    <th>
                                        {{ idLabel[props.type] }}
                                    </th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(user, index) in props.users.data" :key="user.username">
                                    <td class="kolom-no">{{ (props.users.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="font-medium text-black dark:text-foreground">{{ user.name }}</span>
                                    </td>
                                    <td>
                                        {{ idValue(user) }}
                                    </td>
                                    <td>{{ user.username }}</td>
                                    <td class="max-w-[220px] truncate">
                                        {{ user.email }}
                                    </td>
                                    <td>{{ user.role_name ?? '-' }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]"
                                                ><Link
                                                    :href="route(`admin.users.${props.type}.show`, user.id)"
                                                    title="Lihat Detail"
                                                    aria-label="Lihat Detail"
                                                    ><Eye /></Link
                                            ></Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route(`admin.users.${props.type}.edit`, user.id)" title="Edit" aria-label="Edit"
                                                    ><Pencil /></Link
                                            ></Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus"
                                                @click="remove(user)"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.users.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada data {{ props.type }}. Tambahkan data baru untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <AlertModal
                    :open="confirmOpen"
                    description="Anda yakin ingin menghapus data ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="confirmDelete"
                    @cancel="confirmOpen = false"
                />

                <Pagination :links="props.users.links" :total="props.users.total" />
            </div>
        </div>
    </AppLayout>
</template>
