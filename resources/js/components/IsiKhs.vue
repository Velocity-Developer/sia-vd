<script setup lang="ts">
import { computed } from 'vue';

export type BarisKhs = {
    id: number;
    kode: string | null;
    nama: string | null;
    sks: number | null;
    nilai: string | null;
    menunggu_validasi?: boolean;
    berlanjut: boolean;
};
export type TahunAkademikKhs = { id: number; tahun: string; semester: string; status: boolean };
export type KhsProps = {
    krs: BarisKhs[];
    tahunAkademiks: TahunAkademikKhs[];
    tahunAkademikTerpilih: number | null;
    ringkasan: { totalSks: number; totalSksDinilai: number; totalMutu: number; ip: number | null };
};

// Ringkasan + tabel Kartu Hasil Studi satu tahun akademik (halaman mahasiswa dan admin Hasil Studi → KHS).
const props = defineProps<KhsProps>();
const periode = computed(() => props.tahunAkademiks.find((tahun) => tahun.id === props.tahunAkademikTerpilih));
const nilaiClass = (nilai: string | null) => (nilai ? 'bg-[#eaf4ff] text-[#0075de]' : 'bg-[#f6f5f4] text-[#8a8580]');
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-[#0075de] bg-[#0075de] p-6 text-white shadow-sm">
            <p class="text-xs uppercase tracking-[0.08em] text-blue-100">Periode</p>
            <p class="mt-2 text-lg font-semibold">{{ periode?.tahun ?? '-' }}</p>
            <p class="text-sm text-blue-100">{{ periode?.semester ?? '-' }}</p>
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
                                >{{ item.nilai ?? (item.menunggu_validasi ? 'Menunggu validasi' : item.berlanjut ? 'Berlanjut' : 'Belum ada') }}</span
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
</template>
