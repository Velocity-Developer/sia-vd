<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, STATUS_UJIAN, labelMode, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Ujian = {
    id: number;
    jenis: JenisUjian;
    peserta_susulan: number | null;
    mode: ModeUjian;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    status: 'draf' | 'terbit';
    pengawas: string | null;
    petunjuk: string | null;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    kelas_kuliah?: {
        kode_kelas: string;
        krs_count: number;
        remidi_lunas_count: number;
        mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    } | null;
};

const props = defineProps<{ ujians: Ujian[]; tahunAkademikId: number | null; tahunAkademikOptions: { id: number; name: string }[] }>();
const tahun = ref<number | string>(props.tahunAkademikId ?? '');
const gantiTahun = () => router.get(route('dosen.ujian.index'), { tahun_akademik_id: tahun.value }, { preserveScroll: true });
const jumlahPeserta = (u: Ujian) => {
    if (u.jenis === 'remidi') return `${u.kelas_kuliah?.remidi_lunas_count ?? 0} peserta remidi`;
    if (u.jenis === 'uts_susulan' || u.jenis === 'uas_susulan') return `${u.peserta_susulan ?? 0} peserta susulan`;

    return `${u.kelas_kuliah?.krs_count ?? 0} mahasiswa`;
};
</script>

<template>
    <Head title="Ujian" />
    <AppLayout :breadcrumbs="[{ title: 'Ujian', href: route('dosen.ujian.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Ujian</h1>
                        <p class="deskripsi-halaman">
                            Jadwal UTS/UAS kelas yang Anda ampu. Jadwal dan mode ujian ditentukan bagian akademik; jadwal berstatus draf belum
                            terlihat oleh mahasiswa.
                        </p>
                    </div>
                </div>

                <div class="bilah-filter">
                    <SelectFilter v-model="tahun" label="Tahun akademik" @change="gantiTahun">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.ujians.length }}</span> jadwal ujian
                    </p>
                </div>

                <div v-for="u in props.ujians" :key="u.id" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="judul-bagian">{{ u.kelas_kuliah?.mata_kuliah?.nama_matkul }}</h2>
                            <p class="teks-bantu">
                                {{ u.kelas_kuliah?.kode_kelas }} ·
                                {{ jumlahPeserta(u) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded bg-[#fff6e0] px-2 py-0.5 text-xs font-semibold text-[#8a5a00]">{{ JENIS_UJIAN[u.jenis] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_UJIAN[u.status].kelas">{{
                                STATUS_UJIAN[u.status].label
                            }}</span>
                        </div>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="teks-bantu">Waktu</dt>
                            <dd class="text-[#31302e] dark:text-foreground">
                                {{ formatTanggal(u.tanggal) }}, {{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Mode</dt>
                            <dd class="text-[#31302e] dark:text-foreground">{{ labelMode(u.mode) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Tempat</dt>
                            <dd class="text-[#31302e] dark:text-foreground">
                                {{ u.mode === 'tatap_muka' ? (u.ruang ? `${u.ruang.kode_ruang} — ${u.ruang.nama_ruang}` : '-') : 'Online di SIA' }}
                                <span v-if="u.pengawas" class="teks-bantu block">Pengawas: {{ u.pengawas }}</span>
                            </dd>
                        </div>
                    </dl>
                    <p v-if="u.petunjuk" class="alert-info mt-4 whitespace-pre-line">
                        {{ u.petunjuk }}
                    </p>
                    <div class="mt-4 flex justify-end">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="route('dosen.ujian.show', u.id)">
                                {{ u.mode === 'online_berkas' ? 'Siapkan soal & lihat jawaban' : 'Buka detail' }} →
                            </Link>
                        </Button>
                    </div>
                </div>

                <p v-if="!props.ujians.length" class="kartu tabel-kosong">Belum ada jadwal ujian untuk kelas Anda di tahun akademik ini.</p>
            </div>
        </div>
    </AppLayout>
</template>
