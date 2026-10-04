<script setup lang="ts">
import TabelNilaiKomponen, { type BarisNilai, type KomponenNilai, type SkalaAngka } from '@/components/TabelNilaiKomponen.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    kelas: {
        id: number;
        kode_matkul: string | null;
        nama_matkul: string | null;
        prodi: string | null;
        sks: number;
        kode_kelas: string;
        dosen: string | null;
        tahun_akademik: string | null;
        tahun_akademik_id: number;
        dinilai_di: 'pendadaran' | 'kkm' | null;
        final: boolean;
    };
    komponen: KomponenNilai[];
    persenLengkap: boolean;
    skalaSiap: boolean;
    skala: SkalaAngka[];
    mahasiswa: BarisNilai[];
    langsung: boolean;
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const bisaSimpan = computed(() => props.persenLengkap && props.skalaSiap && !props.kelas.dinilai_di);
</script>

<template>
    <Head :title="`Nilai Semester ${props.kelas.nama_matkul ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Nilai Semester', href: route('admin.nilai-semester.index', { tahun_akademik_id: props.kelas.tahun_akademik_id }) },
            { title: `${props.kelas.kode_matkul} ${props.kelas.kode_kelas}`, href: route('admin.nilai-semester.show', props.kelas.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.kelas.kode_matkul }} — {{ props.kelas.nama_matkul }}</h1>
                        <p class="deskripsi-halaman">
                            Kelas {{ props.kelas.kode_kelas }} · {{ props.kelas.sks }} SKS · {{ props.kelas.dosen ?? 'Tanpa dosen' }} ·
                            {{ props.kelas.tahun_akademik }} · {{ props.kelas.prodi }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="route('admin.nilai-semester.index', { tahun_akademik_id: props.kelas.tahun_akademik_id })">
                            <ArrowLeft class="size-4" /> Kembali
                        </Link>
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <p v-if="props.kelas.dinilai_di === 'pendadaran'" class="alert-info" role="status">
                    Nilai TA/Skripsi terisi otomatis dari hasil pendadaran, jadi tidak diisi di sini.
                </p>
                <p v-else-if="props.kelas.dinilai_di === 'kkm'" class="alert-info" role="status">
                    Nilai mata kuliah KKM diisi di
                    <Link :href="route('admin.nilai-kkm.index')" class="font-medium underline">Penilaian → Nilai KKM</Link>.
                </p>
                <p v-else-if="!props.persenLengkap" class="alert-info" role="status">
                    Komponen nilai belum diatur atau jumlah persennya belum 100%. Atur dulu di
                    <Link :href="route('admin.komponen-nilai.index')" class="font-medium underline">Tambah Komponen Nilai</Link>.
                </p>
                <p v-else-if="!props.skalaSiap" class="alert-info" role="status">
                    Angka minimal huruf belum diatur untuk prodi mata kuliah ini, jadi nilai akhir belum bisa diubah menjadi huruf. Isi kolom Angka
                    min. di Akademik → Konfigurasi → Bobot Nilai.
                </p>
                <p v-else-if="props.langsung" class="alert-info" role="status">
                    Mata kuliah ini dinilai langsung tanpa komponen: isi Nilai Akhir 0–100, hurufnya dihitung dari Bobot Nilai prodi.
                </p>
                <p v-if="!props.kelas.dinilai_di && props.kelas.final" class="alert-info" role="status">
                    Nilai kelas ini sudah final untuk dosen. Perubahan di sini tetap tersimpan dan langsung berlaku di KHS dan transkrip.
                </p>

                <p class="teks-bantu">
                    Isi angka 0–100. Nilai akhir dan huruf muncul setelah semua komponen terisi, lalu tersimpan saat Anda menekan Simpan. Baris yang
                    belum lengkap tetap disimpan angkanya. Komponen bertanda “otomatis” diambil dari persentase kehadiran di presensi kelas. Huruf
                    bertanda “manual” diisi tanpa komponen; peserta remidi yang daftarnya sudah dikunci hanya berubah lewat remidi.
                </p>

                <TabelNilaiKomponen
                    :komponen="props.komponen"
                    :skala="props.skala"
                    :mahasiswa="props.mahasiswa"
                    :url="route('admin.nilai-semester.update', props.kelas.id)"
                    :bisa-ubah="bisaSimpan"
                />
            </div>
        </div>
    </AppLayout>
</template>
