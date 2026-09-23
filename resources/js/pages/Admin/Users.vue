<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Search, Trash2 } from 'lucide-vue-next';
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
const createLabel: Record<string, string> = { dosen: 'Tambah Dosen', mahasiswa: 'Tambah Mahasiswa' };
const subtitle: Record<string, string> = {
    dosen: 'Kelola data dosen dan home base program studi.',
    mahasiswa: 'Kelola data mahasiswa, dosen wali, dan informasi orang tua.',
    karyawan: 'Kelola data staf dan nomor induk karyawan.',
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
        <!-- Notion canvas — warm paper, no clinical white -->
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <!-- Header: title + subtitle on paper -->
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ props.title }}</h1>
                        <p class="text-sm leading-5 text-[#615d59]">{{ subtitle[props.type] ?? '' }}</p>
                    </div>
                    <Link v-if="props.type !== 'karyawan'" :href="route(`admin.users.${props.type}.create`)">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"> {{ createLabel[props.type] }} </Button>
                    </Link>
                </div>

                <!-- Controls on paper — search + meta -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="relative w-full sm:max-w-sm">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="search"
                                :placeholder="searchPlaceholder[props.type]"
                                class="h-10 rounded-lg border-[#d8d5d2] bg-white pl-9 text-sm text-[#31302e] shadow-sm outline-none transition-colors placeholder:text-[#a39e98] hover:border-[#aaa5a0] focus:border-[#0075de] focus:ring-2 focus:ring-[#0075de]/15"
                            />
                        </div>
                        <SelectFilter v-if="props.type === 'mahasiswa'" v-model="angkatan" label="Filter angkatan" @change="applyFilters">
                            <option value="all">Semua Angkatan</option>
                            <option v-for="item in props.angkatans" :key="item" :value="item">{{ item }}</option>
                        </SelectFilter>
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.users.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <!-- Flash — toast-like, on paper -->
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02),0_2.025px_7.847px_rgba(0,0,0,0.027)]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <!-- Table as white feature-card on warm canvas -->
                <div
                    class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[920px] text-left lg:min-w-0">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                                        {{ idLabel[props.type] }}
                                    </th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Username</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Email</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Role</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(user, index) in props.users.data" :key="user.username" class="transition-colors hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.users.from ?? 0) + index }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-[15px] font-medium leading-5 text-black">{{ user.name }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ idValue(user) }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ user.username }}</td>
                                    <td class="max-w-[220px] truncate px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ user.role_name ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Link
                                                :href="route(`admin.users.${props.type}.show`, user.id)"
                                                title="Lihat Detail"
                                                aria-label="Lihat Detail"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Eye class="size-4"
                                                /></Button>
                                            </Link>
                                            <Link :href="route(`admin.users.${props.type}.edit`, user.id)" title="Edit" aria-label="Edit">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Pencil class="size-4"
                                                /></Button>
                                            </Link>
                                            <button type="button" title="Hapus" aria-label="Hapus" @click="remove(user)">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Trash2 class="size-4"
                                                /></Button>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.users.data.length">
                                    <td colspan="7" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8">
                                            <p class="text-sm font-medium text-black">Belum ada data</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                Data {{ props.type }} akan tampil di sini. Tambahkan data baru untuk memulai.
                                            </p>
                                        </div>
                                    </td>
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

                <!-- Pagination — utility buttons on paper -->
                <Pagination :links="props.users.links" :total="props.users.total" />
            </div>
        </div>
    </AppLayout>
</template>
