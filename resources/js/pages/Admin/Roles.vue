<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Role = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    user_type: string;
    user_type_label: string;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
};
type Pagination = { data: Role[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

// rute: admin.roles (menu admin) atau dev.roles (panel developer).
const props = defineProps<{ roles: Pagination; search?: string; rute: 'admin.roles' | 'dev.roles' }>();

const search = ref(props.search ?? '');
watch(search, (value) => router.get(route(`${props.rute}.index`), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));

const confirmOpen = ref(false);
const pendingItem = ref<Role | null>(null);

const deleteBlockedReason = (item: Role): string | null => {
    if (item.is_system) return 'Role bawaan sistem tidak dapat dihapus';
    if (item.users_count > 0) return `Masih digunakan oleh ${item.users_count} pengguna`;

    return null;
};

const remove = (item: Role) => {
    if (deleteBlockedReason(item)) return;
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route(`${props.rute}.destroy`, pendingItem.value.id), {
        preserveScroll: true,
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Kelola Role" />
    <AppLayout :breadcrumbs="[{ title: 'Kelola Role', href: route(`${props.rute}.index`) }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Kelola Role</h1>
                        <p class="deskripsi-halaman">Atur role pengguna beserta menu dan fitur yang boleh diakses.</p>
                    </div>
                    <Button as-child><Link :href="route(`${props.rute}.create`)">Tambah Role</Link></Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau deskripsi role" aria-label="Cari" class="pl-9" />
                    </div>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.roles.total }}</span> role<span v-if="props.search">
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
                        <table class="tabel min-w-[780px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Role</th>
                                    <th>Jenis Pengguna</th>
                                    <th class="text-center">Pengguna</th>
                                    <th class="text-center">Hak Akses</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.roles.data" :key="item.id">
                                    <td class="kolom-no align-top">{{ (props.roles.from ?? 1) + index }}</td>
                                    <td class="max-w-[360px] align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-black dark:text-foreground">{{ item.name }}</span>
                                            <span
                                                v-if="item.is_system"
                                                class="rounded-full border border-[#cfe2f7] bg-[#f2f8fe] px-2 py-0.5 text-xs font-medium text-[#0075de]"
                                                >Bawaan</span
                                            >
                                        </div>
                                        <p
                                            v-if="item.description"
                                            class="mt-0.5 truncate text-[#615d59] dark:text-muted-foreground"
                                            :title="item.description"
                                        >
                                            {{ item.description }}
                                        </p>
                                    </td>
                                    <td class="align-top">
                                        {{ item.user_type_label }}
                                    </td>
                                    <td class="text-center align-top">
                                        {{ item.users_count }}
                                    </td>
                                    <td class="text-center align-top">
                                        {{ item.permissions_count }}
                                    </td>
                                    <td class="kolom-aksi align-top">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]"
                                                ><Link :href="route(`${props.rute}.edit`, item.id)" title="Edit & atur hak akses" aria-label="Edit"
                                                    ><Pencil /></Link
                                            ></Button>
                                            <span class="inline-flex" :title="deleteBlockedReason(item) ?? 'Hapus'">
                                                <Button
                                                    variant="outline"
                                                    size="icon-sm"
                                                    class="text-[#dd5b00]"
                                                    aria-label="Hapus"
                                                    :disabled="!!deleteBlockedReason(item)"
                                                    @click="remove(item)"
                                                    ><Trash2
                                                /></Button>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.roles.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Role akan tampil di sini. Tambahkan role baru untuk memulai.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <AlertModal
                    :open="confirmOpen"
                    :description="`Anda yakin ingin menghapus role ${pendingItem?.name ?? ''}?`"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="confirmDelete"
                    @cancel="confirmOpen = false"
                />

                <Pagination :links="props.roles.links" :total="props.roles.total" />
            </div>
        </div>
    </AppLayout>
</template>
