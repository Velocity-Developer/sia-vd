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

type ProgramStudi = {
    id: number;
    kode_prodi: string;
    nama_prodi: string;
    jenjang: string;
    status_akreditasi: string;
    fakultas?: { nama_fakultas?: string } | null;
    ketuaProgramStudi?: { user?: { name?: string } } | null;
    ketua_program_studi?: { user?: { name?: string } } | null;
};

const kaprodiName = (item: ProgramStudi): string => item.ketuaProgramStudi?.user?.name ?? (item as any).ketua_program_studi?.user?.name ?? '-';
type Pagination = { data: ProgramStudi[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    programStudis: Pagination;
    search?: string;
    fakultas: { id: number; nama_fakultas: string }[];
    fakultasId: number | null;
}>();

const search = ref(props.search ?? '');
const fakultasId = ref<number | string>(props.fakultasId ?? 'all');
const applyFilters = () =>
    router.get(
        route('admin.program-studi.index'),
        { search: search.value, fakultas_id: fakultasId.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, applyFilters);

const confirmOpen = ref(false);
const pendingItem = ref<ProgramStudi | null>(null);

const remove = (item: ProgramStudi) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.program-studi.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>

<template>
    <Head title="Program Studi" />
    <AppLayout :breadcrumbs="[{ title: 'Program Studi', href: route('admin.program-studi.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Program Studi</h1>
                        <p class="text-sm leading-5 text-[#615d59]">Kelola program studi per fakultas, jenjang, dan kaprodi.</p>
                    </div>
                    <Link :href="route('admin.program-studi.create')">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Program Studi</Button>
                    </Link>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="relative w-full sm:max-w-sm">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="search"
                                placeholder="Cari kode atau nama program studi"
                                class="h-10 rounded-lg border-[#d8d5d2] bg-white pl-9 text-sm text-[#31302e] shadow-sm outline-none transition-colors placeholder:text-[#a39e98] hover:border-[#aaa5a0] focus:border-[#0075de] focus:ring-2 focus:ring-[#0075de]/15"
                            />
                        </div>
                        <SelectFilter v-model="fakultasId" label="Filter fakultas" @change="applyFilters">
                            <option value="all">Semua Fakultas</option>
                            <option v-for="item in props.fakultas" :key="item.id" :value="item.id">{{ item.nama_fakultas }}</option>
                        </SelectFilter>
                    </div>
                    <p class="text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.programStudis.total }}</span> data<span v-if="props.search">
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
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kode</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama Program Studi</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Fakultas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jenjang</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Akreditasi</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kaprodi</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.programStudis.data" :key="item.id" class="transition-colors hover:bg-[#f6f5f4]/60">
                                    <td data-label="No." class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.programStudis.from ?? 0) + index }}
                                    </td>
                                    <td data-label="Kode" class="px-4 py-3 text-[15px] font-medium leading-5 text-black">{{ item.kode_prodi }}</td>
                                    <td data-label="Nama Program Studi" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ item.nama_prodi }}
                                    </td>
                                    <td data-label="Fakultas" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ item.fakultas?.nama_fakultas ?? '-' }}
                                    </td>
                                    <td data-label="Jenjang" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ item.jenjang }}</td>
                                    <td data-label="Akreditasi" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ item.status_akreditasi }}
                                    </td>
                                    <td data-label="Kaprodi" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ kaprodiName(item) }}</td>
                                    <td data-label="Aksi" class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Link :href="route('admin.program-studi.show', item.id)" title="Detail" aria-label="Detail">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Eye class="size-4"
                                                /></Button>
                                            </Link>
                                            <Link :href="route('admin.program-studi.edit', item.id)" title="Edit" aria-label="Edit">
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
                                <tr v-if="!props.programStudis.data.length">
                                    <td colspan="8" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8">
                                            <p class="text-sm font-medium text-black">Belum ada data</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                Data program studi akan tampil di sini. Tambahkan prodi baru untuk memulai.
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

                <Pagination :links="props.programStudis.links" :total="props.programStudis.total" />
            </div>
        </div>
    </AppLayout>
</template>
