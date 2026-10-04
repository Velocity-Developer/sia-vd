<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import type { KomponenNilai } from '@/components/TabelNilaiKomponen.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Baris = {
    krs_id: number;
    kode_matkul: string | null;
    nama_matkul: string | null;
    sks: number;
    kode_kelas: string;
    tanpa_komponen: string | null;
    nilai: Record<string, number | null>;
    nilai_angka: number | null;
    huruf: string | null;
    divalidasi_at: string | null;
    divalidasi_oleh: string | null;
};

const props = defineProps<{
    mahasiswa: { id: number; nim: string | null; nama: string | null; prodi: string | null; angkatan: string | null };
    tahunAkademikId: number | null;
    tahunAkademikOptions: { id: number; name: string }[];
    komponen: KomponenNilai[];
    nilai: Baris[];
}>();

const tahunAkademikId = ref(props.tahunAkademikId);
const pilihTahun = () =>
    router.get(
        route('admin.detail-nilai.show', { mahasiswa: props.mahasiswa.id, tahun_akademik_id: tahunAkademikId.value }),
        {},
        { preserveScroll: true },
    );

const tervalidasi = computed(() => props.nilai.filter((b) => b.divalidasi_at).length);
</script>

<template>
    <Head :title="`Detail Nilai ${props.mahasiswa.nama ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Detail Nilai', href: route('admin.detail-nilai.index', { tahun_akademik_id: props.tahunAkademikId }) },
            { title: props.mahasiswa.nim ?? '-', href: route('admin.detail-nilai.show', props.mahasiswa.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.mahasiswa.nama }}</h1>
                        <p class="deskripsi-halaman">
                            NIM {{ props.mahasiswa.nim ?? '-' }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan ?? '-' }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="route('admin.detail-nilai.index', { tahun_akademik_id: props.tahunAkademikId })">
                            <ArrowLeft class="size-4" /> Kembali
                        </Link>
                    </Button>
                </div>

                <div class="bilah-filter">
                    <SelectFilter v-model="tahunAkademikId" label="Tahun akademik" @change="pilihTahun">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ tervalidasi }}</span> dari {{ props.nilai.length }} mata kuliah
                        tervalidasi
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel" :style="{ minWidth: `${640 + props.komponen.length * 100}px` }">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode MK</th>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th v-for="k in props.komponen" :key="k.id" class="text-right">
                                        {{ k.nama }}<span class="block text-xs font-normal normal-case text-[#a39e98]">{{ k.persen }}%</span>
                                    </th>
                                    <th class="text-right">Nilai Akhir</th>
                                    <th class="text-center">Huruf</th>
                                    <th>Validasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.nilai" :key="b.krs_id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="whitespace-nowrap">{{ b.kode_matkul ?? '-' }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama_matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.sks }} SKS</span>
                                    </td>
                                    <td class="whitespace-nowrap">{{ b.kode_kelas }}</td>
                                    <template v-if="b.tanpa_komponen">
                                        <td :colspan="props.komponen.length" class="text-center text-xs text-[#615d59]">{{ b.tanpa_komponen }}</td>
                                    </template>
                                    <template v-else>
                                        <td v-for="k in props.komponen" :key="k.id" class="text-right tabular-nums">{{ b.nilai[k.id] ?? '-' }}</td>
                                    </template>
                                    <td class="text-right font-medium tabular-nums">{{ b.nilai_angka ?? '-' }}</td>
                                    <td class="text-center font-semibold">{{ b.huruf ?? '-' }}</td>
                                    <td>
                                        <span
                                            v-if="b.divalidasi_at"
                                            class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#ecfdf3] px-2 py-0.5 text-xs font-medium text-[#067647]"
                                            ><BadgeCheck class="size-3.5" /> Tervalidasi</span
                                        >
                                        <span v-else class="whitespace-nowrap text-xs text-[#615d59]">{{
                                            b.huruf ? 'Belum divalidasi' : 'Belum bernilai'
                                        }}</span>
                                        <span v-if="b.divalidasi_at" class="mt-0.5 block text-xs text-[#a39e98]">
                                            {{ formatTanggal(b.divalidasi_at)
                                            }}<template v-if="b.divalidasi_oleh"> · {{ b.divalidasi_oleh }}</template>
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!props.nilai.length" class="baris-kosong">
                                    <td :colspan="7 + props.komponen.length" class="tabel-kosong">
                                        Mahasiswa ini tidak punya KRS pada tahun akademik ini.
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
