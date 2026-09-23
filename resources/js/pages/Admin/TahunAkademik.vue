<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const formatDate = (value: string) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value));

type Item = {
    id: number;
    tahun: string;
    semester: string;
    tanggal_mulai: string;
    tanggal_akhir: string;
    tanggal_krs_awal: string;
    tanggal_krs_akhir: string;
    status: boolean;
};
const page = usePage<{ flash?: { success?: string; error?: string } }>();
const props = defineProps<{ tahunAkademiks: { data: Item[]; total: number; from: number | null; links: any[] }; search?: string }>();
const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('admin.tahun-akademik.index'), { search: value }, { preserveState: true, replace: true }));
const confirmOpen = ref(false);
const pendingItem = ref<Item | null>(null);
const remove = (item: Item) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};
const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(route('admin.tahun-akademik.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};
</script>
<template>
    <Head title="Tahun Akademik" />
    <AppLayout :breadcrumbs="[{ title: 'Tahun Akademik', href: route('admin.tahun-akademik.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#b9e7c1] bg-[#effbf1] px-4 py-3 text-sm text-[#16852b]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-xl border border-[#f3c6b5] bg-[#fff5f0] px-4 py-3 text-sm text-[#c44d16]"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-bold text-black">Tahun Akademik</h1>
                        <p class="mt-1 text-sm text-[#615d59]">Kelola periode akademik perkuliahan.</p>
                    </div>
                    <Link :href="route('admin.tahun-akademik.create')"
                        ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Tahun Akademik</Button></Link
                    >
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input
                            v-model="search"
                            placeholder="Cari tahun atau semester"
                            class="h-9 max-w-sm rounded-[4px] border-[#dddddd] bg-white pl-9 text-[15px] placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        />
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.tahunAkademiks.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[920px] text-left lg:min-w-0">
                            <thead>
                                <tr class="border-b bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tahun</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Semester</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Periode Kuliah</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Periode KRS</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.tahunAkademiks.data" :key="item.id" class="hover:bg-[#fafafa]">
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.tahunAkademiks.from ?? 0) + index }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] font-medium leading-5 text-black">{{ item.tahun }}</td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ item.semester }}</td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ formatDate(item.tanggal_mulai) }} — {{ formatDate(item.tanggal_akhir) }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ formatDate(item.tanggal_krs_awal) }} — {{ formatDate(item.tanggal_krs_akhir) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span
                                            class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold"
                                            :class="item.status ? 'bg-[#eaf8ed] text-[#16852b]' : 'bg-[#f1f1f1] text-[#77736f]'"
                                            >{{ item.status ? 'Aktif' : 'Tidak Aktif' }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end gap-1.5">
                                            <Link :href="route('admin.tahun-akademik.edit', item.id)"
                                                ><Button variant="outline" size="icon" class="size-8 rounded-full text-[#2a9d99]" title="Edit"
                                                    ><Pencil class="size-4" /></Button></Link
                                            ><Button
                                                variant="outline"
                                                size="icon"
                                                class="ml-2 size-8 rounded-full text-[#dd5b00]"
                                                title="Hapus"
                                                @click="remove(item)"
                                                ><Trash2 class="size-4"
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.tahunAkademiks.data.length">
                                    <td colspan="7" class="px-4 py-16 text-center text-sm text-[#615d59]">Belum ada data tahun akademik.</td>
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
            </div>
        </div>
    </AppLayout>
</template>
