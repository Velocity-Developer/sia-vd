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

type Ruang = { id: number; kode_ruang: string; nama_ruang: string; kapasitas: number; detail: string | null };
type Pagination = { data: Ruang[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{ ruangs: Pagination; search?: string }>();

const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.ruang.index'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));

const confirmOpen = ref(false);
const pendingItem = ref<Ruang | null>(null);

const remove = (item: Ruang) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.ruang.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Ruang" />
    <AppLayout :breadcrumbs="[{ title: 'Ruang', href: route('admin.ruang.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Ruang</h1>
                        <p class="text-sm leading-5 text-[#615d59]">Kelola ruang kuliah, laboratorium, dan detail fasilitas.</p>
                    </div>
                    <Link :href="route('admin.ruang.create')">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Ruang</Button>
                    </Link>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input
                            v-model="search"
                            placeholder="Cari kode atau nama ruang"
                            class="h-9 rounded-[4px] border-[#dddddd] bg-white pl-9 text-[15px] placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        />
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.ruangs.total }}</span> data<span v-if="props.search">
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
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[780px] text-left lg:min-w-0">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kode</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama Ruang</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kapasitas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Detail</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.ruangs.data" :key="item.id" class="transition-colors hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.ruangs.from ?? 0) + index }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] font-medium leading-5 text-black">{{ item.kode_ruang }}</td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ item.nama_ruang }}</td>
                                    <td class="px-4 py-3 text-center text-[15px] leading-5 text-[#31302e]">
                                        {{ item.kapasitas }}
                                    </td>
                                    <td class="max-w-[320px] truncate px-4 py-3 text-[15px] leading-5 text-[#615d59]" :title="item.detail ?? ''">
                                        {{ item.detail ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Link :href="route('admin.ruang.show', item.id)" title="Detail" aria-label="Detail">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Eye class="size-4"
                                                /></Button>
                                            </Link>
                                            <Link :href="route('admin.ruang.edit', item.id)" title="Edit" aria-label="Edit">
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
                                <tr v-if="!props.ruangs.data.length">
                                    <td colspan="6" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8">
                                            <p class="text-sm font-medium text-black">Belum ada data</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                Data ruang akan tampil di sini. Tambahkan ruang baru untuk memulai.
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

                <Pagination :links="props.ruangs.links" :total="props.ruangs.total" />
            </div>
        </div>
    </AppLayout>
</template>
