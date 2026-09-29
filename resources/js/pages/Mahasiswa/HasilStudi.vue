<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Kartu Hasil Studi</h1>
                        <p class="deskripsi-halaman">Daftar kelas yang diambil dan nilai akademik Anda.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-2">
                            <label for="tahun_akademik" class="label-isian">Tahun Akademik</label>
                            <select id="tahun_akademik" v-model="selectedYear" class="isian isian-pilih min-w-[210px]" @change="changeYear">
                                <option v-for="tahun in tahunAkademiks" :key="tahun.id" :value="tahun.id">
                                    {{ tahun.tahun }} — {{ tahun.semester }}{{ tahun.status ? ' (Aktif)' : '' }}
                                </option>
                            </select>
                        </div>
                        <Button as-child>
                            <a :href="downloadUrl">
                                <Download class="h-4 w-4" />
                                Download KHS
                            </a>
                        </Button>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-[#0075de] bg-[#0075de] p-6 text-white shadow-sm">
                        <p class="text-xs uppercase tracking-[0.08em] text-blue-100">Periode</p>
                        <p class="mt-2 text-lg font-semibold">{{ tahunAkademiks.find((tahun) => tahun.id === selectedYear)?.tahun ?? '-' }}</p>
                        <p class="text-sm text-blue-100">{{ tahunAkademiks.find((tahun) => tahun.id === selectedYear)?.semester ?? '-' }}</p>
                    </div>
                    <div class="kartu p-6">
                        <p class="teks-bantu uppercase tracking-[0.08em]">Total SKS</p>
                        <p class="mt-2 text-2xl font-bold text-black dark:text-foreground">{{ ringkasan.totalSks }}</p>
                    </div>
                    <div class="kartu p-6">
                        <p class="teks-bantu uppercase tracking-[0.08em]">Indeks Prestasi</p>
                        <p class="mt-2 text-2xl font-bold text-[#0075de]">{{ ringkasan.ip?.toFixed(2) ?? '-' }}</p>
                        <p class="teks-bantu mt-1">{{ ringkasan.totalSksDinilai }} SKS dinilai</p>
                    </div>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[720px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mata Kuliah</th>
                                    <th class="text-center">SKS</th>
                                    <th class="text-center">Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in krs" :key="item.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <p class="font-medium text-black dark:text-foreground">{{ item.nama ?? '-' }}</p>
                                        <p class="teks-bantu">{{ item.kode ?? '-' }}</p>
                                    </td>
                                    <td class="text-center tabular-nums">{{ item.sks ?? '-' }}</td>
                                    <td class="text-center">
                                        <span
                                            class="inline-flex min-w-9 justify-center rounded-full px-2.5 py-1 text-xs font-bold uppercase"
                                            :class="nilaiClass(item.nilai)"
                                            >{{ item.nilai ?? 'Belum ada' }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!krs.length" class="baris-kosong">
                                    <td colspan="4" class="tabel-kosong">Belum ada hasil studi pada tahun akademik ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
