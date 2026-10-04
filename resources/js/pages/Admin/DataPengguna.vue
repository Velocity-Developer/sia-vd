<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Search, UserPlus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Pengguna = {
    id: number;
    name: string;
    username: string;
    email: string;
    jenis: string;
    tipe_rute: string;
    nomor_induk: string | null;
    role_name: string | null;
};
type Halaman = {
    data: Pengguna[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    total: number;
};

const props = defineProps<{ users: Halaman; search: string; jenis: string | null; jenisOpsi: { value: string; label: string }[] }>();

const search = ref(props.search);
const jenis = ref<string>(props.jenis ?? 'all');
const terapkan = () =>
    router.get(
        route('admin.pengguna.index'),
        { search: search.value || undefined, jenis: jenis.value === 'all' ? undefined : jenis.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, terapkan);
</script>

<template>
    <Head title="Data Pengguna" />
    <AppLayout :breadcrumbs="[{ title: 'Data Pengguna', href: route('admin.pengguna.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Data Pengguna</h1>
                        <p class="deskripsi-halaman">Semua akun Prodi, Dosen, Mahasiswa, dan Karyawan dalam satu daftar.</p>
                    </div>
                    <Button as-child>
                        <Link :href="route('admin.pengguna.buat')"><UserPlus /> Create User</Link>
                    </Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama, username, email, atau nomor induk" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="jenis" label="Filter jenis akun" @change="terapkan">
                        <option value="all">Semua Jenis</option>
                        <option v-for="item in props.jenisOpsi" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.users.total }}</span> akun<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[920px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Nama</th>
                                    <th>Jenis</th>
                                    <th>NIM / NIDN / No. Induk</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(user, index) in props.users.data" :key="user.id">
                                    <td class="kolom-no">{{ (props.users.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="font-medium text-black dark:text-foreground">{{ user.name }}</span>
                                    </td>
                                    <td>{{ user.jenis }}</td>
                                    <td>{{ user.nomor_induk ?? '-' }}</td>
                                    <td>{{ user.username }}</td>
                                    <td class="max-w-[220px] truncate">{{ user.email }}</td>
                                    <td>{{ user.role_name ?? '-' }}</td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                <Link
                                                    :href="route(`admin.users.${user.tipe_rute}.show`, user.id)"
                                                    title="Lihat Detail"
                                                    aria-label="Lihat Detail"
                                                    ><Eye
                                                /></Link>
                                            </Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="route(`admin.users.${user.tipe_rute}.edit`, user.id)" title="Edit" aria-label="Edit"
                                                    ><Pencil
                                                /></Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.users.data.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Tidak ada akun yang cocok.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.users.links" :total="props.users.total" />
            </div>
        </div>
    </AppLayout>
</template>
