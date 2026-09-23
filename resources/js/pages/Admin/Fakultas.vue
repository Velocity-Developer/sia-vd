<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Fakultas = {
    id: number;
    kode_fakultas: string;
    nama_fakultas: string;
    dekan?: { user?: { name?: string } } | null;
    tanggal_berdiri?: string | null;
    no_telp?: string | null;
    email?: string | null;
};
type Pagination = { data: Fakultas[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{ fakultas: Pagination; search?: string }>();

const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.fakultas.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));

const confirmOpen = ref(false);
const pendingItem = ref<Fakultas | null>(null);

const remove = (item: Fakultas) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.fakultas.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Fakultas" />
    <AppLayout :breadcrumbs="[{ title: 'Fakultas', href: route('admin.fakultas.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Fakultas</h1>
                        <p class="text-sm leading-5 text-[#615d59]">Kelola data fakultas, dekan, dan informasi kontak.</p>
                    </div>
                    <Link :href="route('admin.fakultas.create')">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Fakultas</Button>
                    </Link>
                </div>

                <!-- Controls -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input
                            v-model="search"
                            placeholder="Cari kode atau nama fakultas"
                            class="h-9 rounded-[4px] border-[#dddddd] bg-white pl-9 text-[15px] placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        />
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.fakultas.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <!-- Flash -->
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

                <!-- Table card -->
                <div
                    class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[780px] text-left lg:min-w-0">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kode Fakultas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama Fakultas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Dekan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Email</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.fakultas.data" :key="item.id" class="transition-colors hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.fakultas.from ?? 0) + index }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] font-medium leading-5 text-black">
                                        {{ item.kode_fakultas }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ item.nama_fakultas }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ item.dekan?.user?.name ?? '-' }}
                                    </td>
                                    <td class="max-w-[200px] truncate px-4 py-3 text-[15px] leading-5 text-[#31302e]" :title="item.email ?? ''">
                                        {{ item.email ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Link :href="route('admin.fakultas.show', item.id)" title="Detail" aria-label="Detail">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Eye class="size-4"
                                                /></Button>
                                            </Link>
                                            <Link :href="route('admin.fakultas.edit', item.id)" title="Edit" aria-label="Edit">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Pencil class="size-4"
                                                /></Button>
                                            </Link>
                                            <button type="button" title="Hapus" aria-label="Hapus" @click="remove(item)">
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
                                <tr v-if="!props.fakultas.data.length">
                                    <td colspan="6" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8">
                                            <p class="text-sm font-medium text-black">Belum ada data</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                Data fakultas akan tampil di sini. Tambahkan fakultas baru untuk memulai.
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

                <Pagination :links="props.fakultas.links" :total="props.fakultas.total" />
            </div>
        </div>
    </AppLayout>
</template>
