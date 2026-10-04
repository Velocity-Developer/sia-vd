<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BadgeCheck, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

type Baris = {
    krs_id: number;
    tahun_akademik_id: number | null;
    tahun_akademik: string | null;
    kode_matkul: string | null;
    nama_matkul: string | null;
    sks: number;
    nilai_angka: number | null;
    huruf: string | null;
    bobot: number | null;
    sumber: string | null;
    status: 'belum' | 'menunggu' | 'tervalidasi';
};

const props = defineProps<{
    mahasiswa: { id: number; nim: string | null; nama: string | null; prodi: string | null; angkatan: string | null };
    nilai: Baris[];
}>();

// Baris dikelompokkan per tahun akademik (urutan dari server: tahun lama → baru).
const kelompok = computed(() => {
    const hasil: { tahun: string; tahunId: number | null; baris: Baris[] }[] = [];
    for (const b of props.nilai) {
        const tahun = b.tahun_akademik ?? '-';
        const terakhir = hasil[hasil.length - 1];
        if (terakhir?.tahun === tahun) terakhir.baris.push(b);
        else hasil.push({ tahun, tahunId: b.tahun_akademik_id, baris: [b] });
    }
    return hasil;
});
const totalSks = computed(() => props.nilai.reduce((n, b) => n + b.sks, 0));
const menunggu = (grup: { baris: Baris[] }) => grup.baris.filter((b) => b.status === 'menunggu').length;
</script>

<template>
    <Head :title="`Pendataan Nilai ${props.mahasiswa.nama ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Pendataan Nilai Akhir', href: route('admin.pendataan-nilai.index') },
            { title: props.mahasiswa.nim ?? '-', href: route('admin.pendataan-nilai.show', props.mahasiswa.id) },
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
                        <Link :href="route('admin.pendataan-nilai.index')"> <ArrowLeft class="size-4" /> Kembali </Link>
                    </Button>
                </div>

                <p class="info-jumlah">
                    <span class="font-medium text-black dark:text-foreground">{{ props.nilai.length }}</span> mata kuliah diambil · {{ totalSks }} SKS
                </p>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[820px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode MK</th>
                                    <th>Mata Kuliah</th>
                                    <th class="text-center">SKS</th>
                                    <th class="text-right">Nilai Akhir</th>
                                    <th class="text-center">Huruf</th>
                                    <th class="text-center">Bobot</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody v-for="grup in kelompok" :key="grup.tahun">
                                <tr>
                                    <td colspan="8" class="bg-[#f6f5f4] text-xs font-semibold uppercase tracking-wide text-[#615d59] dark:bg-muted">
                                        <span class="flex flex-wrap items-center justify-between gap-2">
                                            <span>Tahun Akademik {{ grup.tahun }}</span>
                                            <Link
                                                v-if="menunggu(grup) && grup.tahunId"
                                                :href="
                                                    route('admin.validasi-nilai.show', {
                                                        mahasiswa: props.mahasiswa.id,
                                                        tahun_akademik_id: grup.tahunId,
                                                    })
                                                "
                                                class="inline-flex items-center gap-1 normal-case tracking-normal text-[#0075de] hover:underline"
                                                ><ShieldCheck class="size-3.5" /> Validasi {{ menunggu(grup) }} nilai</Link
                                            >
                                        </span>
                                    </td>
                                </tr>
                                <tr v-for="b in grup.baris" :key="b.krs_id">
                                    <td class="kolom-no">{{ props.nilai.indexOf(b) + 1 }}</td>
                                    <td class="whitespace-nowrap">{{ b.kode_matkul ?? '-' }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama_matkul }}</span>
                                        <span v-if="b.sumber" class="block text-xs text-[#a39e98]">{{ b.sumber }}</span>
                                    </td>
                                    <td class="text-center tabular-nums">{{ b.sks }}</td>
                                    <td class="text-right tabular-nums">{{ b.nilai_angka ?? '-' }}</td>
                                    <td class="text-center font-semibold">{{ b.huruf ?? '-' }}</td>
                                    <td class="text-center tabular-nums">{{ b.bobot ?? '-' }}</td>
                                    <td>
                                        <span
                                            v-if="b.status === 'tervalidasi'"
                                            class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#ecfdf3] px-2 py-0.5 text-xs font-medium text-[#067647]"
                                            ><BadgeCheck class="size-3.5" /> Tervalidasi</span
                                        >
                                        <span
                                            v-else-if="b.status === 'menunggu'"
                                            class="whitespace-nowrap rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-medium text-[#0075de]"
                                            >Menunggu validasi</span
                                        >
                                        <span
                                            v-else
                                            class="whitespace-nowrap rounded-full bg-[#f6f5f4] px-2 py-0.5 text-xs font-medium text-[#615d59]"
                                            >Belum dinilai</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                            <tbody v-if="!props.nilai.length">
                                <tr class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Mahasiswa ini belum mengambil mata kuliah.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="teks-bantu">
                    Huruf dihitung otomatis dari nilai akhir sesuai Bobot Nilai prodi mata kuliah. Angka diubah di Penilaian → Nilai Semester (atau
                    Nilai KKM); setelah benar, sahkan di Penilaian → Validasi Nilai. Hanya nilai tervalidasi yang masuk KHS dan transkrip.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
