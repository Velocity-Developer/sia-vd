<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Krs = {
    id: number;
    kode: string | null;
    nama: string | null;
    sks: number | null;
    nilai: string | null;
};

type TahunAkademik = { id: number; tahun: string; semester: string; status: boolean };

const props = defineProps<{
    krs: Krs[];
    tahunAkademiks: TahunAkademik[];
    tahunAkademikTerpilih: number | null;
    ringkasan: { totalSks: number; totalSksDinilai: number; totalMutu: number; ip: number | null };
}>();

const selectedYear = ref(props.tahunAkademikTerpilih ?? '');
const nilaiClass = (nilai: string | null) => (nilai ? 'bg-[#eaf4ff] text-[#0075de]' : 'bg-[#f6f5f4] text-[#8a8580]');

const downloadUrl = computed(() => route('mahasiswa.hasil-studi.download', { tahun_akademik_id: selectedYear.value }));

const changeYear = () => {
    router.get(route('mahasiswa.hasil-studi'), { tahun_akademik_id: selectedYear.value }, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Kartu Hasil Studi" />
    <AppLayout :breadcrumbs="[{ title: 'Kartu Hasil Studi', href: route('mahasiswa.hasil-studi') }]">
        <div class="min-h-full bg-[#f6f5f4] dark:bg-gray-950">
            <div class="mx-auto flex w-full max-w-[1100px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black dark:text-white">Kartu Hasil Studi</h1>
                        <p class="text-sm leading-5 text-[#615d59] dark:text-gray-400">Daftar kelas yang diambil dan nilai akademik Anda.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-end">
                        <label class="flex flex-col gap-1.5 text-sm font-medium text-[#31302e] dark:text-gray-200">
                            Tahun Akademik
                            <select
                                v-model="selectedYear"
                                class="min-w-[210px] rounded-lg border border-[#e6e6e6] bg-white px-3 py-2.5 text-sm text-black outline-none focus:border-[#0075de] focus:ring-2 focus:ring-[#0075de]/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                @change="changeYear"
                            >
                                <option v-for="tahun in tahunAkademiks" :key="tahun.id" :value="tahun.id">
                                    {{ tahun.tahun }} — {{ tahun.semester }}{{ tahun.status ? ' (Aktif)' : '' }}
                                </option>
                            </select>
                        </label>
                        <a
                            :href="downloadUrl"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#0075de] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#005bab]"
                        >
                            <Download class="h-4 w-4" />
                            Download KHS
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-[#0075de] bg-[#0075de] p-5 text-white shadow-sm">
                        <p class="text-xs uppercase tracking-[0.08em] text-blue-100">Periode</p>
                        <p class="mt-2 text-lg font-semibold">{{ tahunAkademiks.find((tahun) => tahun.id === selectedYear)?.tahun ?? '-' }}</p>
                        <p class="text-sm text-blue-100">{{ tahunAkademiks.find((tahun) => tahun.id === selectedYear)?.semester ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs uppercase tracking-[0.08em] text-[#a39e98]">Total SKS</p>
                        <p class="mt-2 text-2xl font-bold text-black dark:text-white">{{ ringkasan.totalSks }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs uppercase tracking-[0.08em] text-[#a39e98]">Indeks Prestasi</p>
                        <p class="mt-2 text-2xl font-bold text-[#0075de]">{{ ringkasan.ip?.toFixed(2) ?? '-' }}</p>
                        <p class="mt-1 text-xs text-[#8a8580]">{{ ringkasan.totalSksDinilai }} SKS dinilai</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="tabel-responsif w-full text-left md:min-w-[720px]">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4] dark:border-gray-800 dark:bg-gray-800">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">SKS</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6] dark:divide-gray-800">
                                <tr
                                    v-for="(item, index) in krs"
                                    :key="item.id"
                                    class="transition-colors hover:bg-[#f6f5f4]/60 dark:hover:bg-gray-800"
                                >
                                    <td data-label="No." class="px-4 py-4 text-sm text-[#615d59] dark:text-gray-400">{{ index + 1 }}</td>
                                    <td data-label="Mata Kuliah" class="px-4 py-4">
                                        <p class="text-sm font-semibold text-black dark:text-white">{{ item.nama ?? '-' }}</p>
                                        <p class="text-xs text-[#8a8580]">{{ item.kode ?? '-' }}</p>
                                    </td>
                                    <td data-label="SKS" class="px-4 py-4 text-center text-sm text-[#31302e] dark:text-gray-200">
                                        {{ item.sks ?? '-' }}
                                    </td>
                                    <td data-label="Nilai" class="px-4 py-4 text-center">
                                        <span
                                            class="inline-flex min-w-9 justify-center rounded-full px-2.5 py-1 text-sm font-bold uppercase"
                                            :class="nilaiClass(item.nilai)"
                                            >{{ item.nilai ?? 'Belum ada' }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!krs.length">
                                    <td colspan="4" class="px-4 py-16 text-center text-sm text-[#615d59] dark:text-gray-400">
                                        Belum ada hasil studi pada tahun akademik ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
