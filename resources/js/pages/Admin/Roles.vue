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

const props = defineProps<{ roles: Pagination; search?: string }>();

const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.roles.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));

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
    router.delete(route('admin.roles.destroy', pendingItem.value.id), {
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
    <AppLayout :breadcrumbs="[{ title: 'Kelola Role', href: route('admin.roles.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Kelola Role</h1>
                        <p class="text-sm leading-5 text-[#615d59]">Atur role pengguna beserta menu dan fitur yang boleh diakses.</p>
                    </div>
                    <Link :href="route('admin.roles.create')">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Role</Button>
                    </Link>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input
                            v-model="search"
                            placeholder="Cari nama atau deskripsi role"
                            class="h-9 rounded-[4px] border-[#dddddd] bg-white pl-9 text-[15px] placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        />
                    </div>
                    <p class="text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.roles.total }}</span> role<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

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

                <div
                    class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="overflow-x-auto">
                        <table class="tabel-responsif w-full text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Role</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jenis Pengguna</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pengguna</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Hak Akses</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.roles.data" :key="item.id" class="transition-colors hover:bg-[#f6f5f4]/60">
                                    <td data-label="No." class="px-4 py-3 align-top text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.roles.from ?? 0) + index }}
                                    </td>
                                    <td data-label="Role" class="max-w-[360px] px-4 py-3 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[15px] font-medium leading-5 text-black">{{ item.name }}</span>
                                            <span
                                                v-if="item.is_system"
                                                class="rounded-full border border-[#e6e6e6] bg-white px-2 py-0.5 text-xs font-semibold text-[#0075de]"
                                                >Bawaan</span
                                            >
                                        </div>
                                        <p v-if="item.description" class="mt-0.5 truncate text-sm leading-5 text-[#615d59]" :title="item.description">
                                            {{ item.description }}
                                        </p>
                                    </td>
                                    <td data-label="Jenis Pengguna" class="px-4 py-3 align-top text-[15px] leading-5 text-[#31302e]">
                                        {{ item.user_type_label }}
                                    </td>
                                    <td data-label="Pengguna" class="px-4 py-3 text-center align-top text-[15px] leading-5 text-[#31302e]">
                                        {{ item.users_count }}
                                    </td>
                                    <td data-label="Hak Akses" class="px-4 py-3 text-center align-top text-[15px] leading-5 text-[#31302e]">
                                        {{ item.permissions_count }}
                                    </td>
                                    <td data-label="Aksi" class="px-4 py-3 align-top">
                                        <div class="flex justify-end gap-1.5">
                                            <Link :href="route('admin.roles.edit', item.id)" title="Edit & atur hak akses" aria-label="Edit">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Pencil class="size-4"
                                                /></Button>
                                            </Link>
                                            <button
                                                type="button"
                                                :title="deleteBlockedReason(item) ?? 'Hapus'"
                                                aria-label="Hapus"
                                                :disabled="!!deleteBlockedReason(item)"
                                                class="disabled:cursor-not-allowed"
                                                @click="remove(item)"
                                            >
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                    :class="deleteBlockedReason(item) ? 'pointer-events-none opacity-40' : ''"
                                                    aria-hidden="true"
                                                    ><Trash2 class="size-4"
                                                /></Button>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.roles.data.length">
                                    <td colspan="6" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8">
                                            <p class="text-sm font-medium text-black">Belum ada data</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                Role akan tampil di sini. Tambahkan role baru untuk memulai.
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
