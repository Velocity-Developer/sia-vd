<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Krs = {
    id: number;
    mahasiswa?: { nim?: string; user?: { name?: string; email?: string }; prodi?: { nama_prodi?: string } };
    kelas_kuliah?: { kode_kelas?: string; mata_kuliah?: { kode_matkul?: string; nama_matkul?: string } };
};
type Pagination = { data: Krs[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
const props = defineProps<{ krs: Pagination; search?: string }>();
const search = ref(props.search ?? '');
watch(search, (value) => router.get(route('dosen.mahasiswa-kelas'), { search: value }, { preserveState: true, preserveScroll: true, replace: true }));
</script>

<template>
    <Head title="Mahasiswa Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Mahasiswa Kelas', href: route('dosen.mahasiswa-kelas') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold tracking-[-0.625px] text-black">Mahasiswa Kelas</h1>
                    <p class="text-sm text-[#615d59]">Daftar mahasiswa yang mengambil kelas Anda.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" /><Input
                            v-model="search"
                            placeholder="Cari nama, NIM, kelas, atau mata kuliah"
                            class="h-9 rounded-[4px] border-[#dddddd] bg-white pl-9 text-[15px] placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        />
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.krs.total }}</span> mahasiswa
                    </p>
                </div>
                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left lg:min-w-0">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Program Studi</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Kelas</th>
                                    <th class="px-4 py-3 text-xs uppercase text-[#a39e98]">Mata Kuliah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.krs.data" :key="item.id">
                                    <td class="px-4 py-3 text-sm text-[#615d59]">{{ (props.krs.from ?? 0) + index }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="block font-medium text-black">{{ item.mahasiswa?.user?.name ?? '-' }}</span
                                        ><span class="text-xs text-[#a39e98]"
                                            >{{ item.mahasiswa?.nim ?? '-' }} | {{ item.mahasiswa?.user?.email ?? '-' }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ item.mahasiswa?.prodi?.nama_prodi ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ item.kelas_kuliah?.mata_kuliah?.kode_matkul ?? '-' }} —
                                        {{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '' }}
                                    </td>
                                </tr>
                                <tr v-if="!props.krs.data.length">
                                    <td colspan="5" class="px-4 py-16 text-center text-sm text-[#615d59]">Belum ada mahasiswa terdaftar.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination :links="props.krs.links" :total="props.krs.total" />
            </div>
        </div>
    </AppLayout>
</template>
