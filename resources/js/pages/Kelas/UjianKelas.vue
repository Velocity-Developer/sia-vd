<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFitur } from '@/composables/useFitur';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatJamDari, formatTanggal, jam } from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { JENIS_UJIAN, jenisKhusus, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FileUp, Paperclip, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type Syarat = { persen: number | null; memenuhi: boolean | null; dispensasi: { alasan: string } | null } | null;
type Peserta = {
    mahasiswa_id: number;
    nim: string;
    nama: string;
    syarat: Syarat;
    pengerjaan: {
        id: number;
        mulai_at: string | null;
        selesai_at: string | null;
        skor: string | null;
        nilai: number | null;
        auto_closed: boolean;
        essay_belum_dinilai: boolean;
    } | null;
    jawaban: {
        id: number;
        jumlah_berkas: number;
        nama_berkas: string[];
        dikumpulkan_at: string | null;
        nilai: string | null;
        catatan_dosen: string | null;
        penilai: string | null;
    } | null;
};

const props = defineProps<{
    peran: Peran;
    kelasKuliah: { id: number; kode_kelas: string; mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null };
    ujian: {
        id: number;
        jenis: JenisUjian;
        mode: ModeUjian;
        tanggal: string;
        jam_mulai: string;
        jam_akhir: string;
        label_mode: string;
        ruang: string | null;
        pengawas: string | null;
        petunjuk: string | null;
        status: 'draf' | 'terbit';
        nilai_dirilis: boolean;
        soal: string[];
    };
    peserta: Peserta[];
    lembarSoal: { id: number; jumlah_soal: number; total_poin: number; waktu_pengerjaan: number | null } | null;
    syaratAktif: boolean;
    sudahMulai: boolean;
    sudahSelesai: boolean;
    terkunci: boolean;
    batasNilaiRemidi: string | null;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
// Tanpa fitur keuangan, remidi dan susulan tidak bersyarat bayar.
const keuangan = useFitur().aktif('keuangan');
const rute = rutePeran(props.peran);
const berkasMode = computed(() => props.ujian.mode === 'online_berkas');
const soalMode = computed(() => props.ujian.mode === 'online_soal');
const tatapMuka = computed(() => props.ujian.mode === 'tatap_muka');
const dinilai = computed(() => props.peserta.filter((p) => p.jawaban?.nilai != null).length);
const terkumpul = computed(() => props.peserta.filter((p) => (soalMode.value ? p.pengerjaan?.selesai_at : p.jawaban)).length);
const buatLembarSoal = () => router.post(rute('ujian.lembar-soal', props.ujian.id));

// Unggah soal (sebelum ujian dimulai).
const soalForm = useForm({ soal: [] as File[] });
const inputSoal = ref<HTMLInputElement | null>(null);
const pilihSoal = (event: Event) => (soalForm.soal = Array.from((event.target as HTMLInputElement).files ?? []));
const unggahSoal = () =>
    soalForm.post(rute('ujian.soal.unggah', props.ujian.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            soalForm.reset();
            if (inputSoal.value) inputSoal.value.value = '';
        },
    });
const hapusSoal = (index: number) => router.delete(rute('ujian.soal.hapus', [props.ujian.id, index]), { preserveScroll: true });
const errorSoal = computed(() => Object.entries(soalForm.errors).find(([k]) => k.startsWith('soal'))?.[1]);

// Nilai per mahasiswa (tatap muka & unggah berkas).
const nilai = reactive<Record<number, { nilai: string; catatan: string }>>(
    Object.fromEntries(props.peserta.map((p) => [p.mahasiswa_id, { nilai: p.jawaban?.nilai ?? '', catatan: p.jawaban?.catatan_dosen ?? '' }])),
);
const menyimpan = ref<number | null>(null);
const simpanNilai = (mahasiswaId: number) => {
    menyimpan.value = mahasiswaId;
    router.put(
        rute('ujian.nilai', [props.ujian.id, mahasiswaId]),
        { nilai: nilai[mahasiswaId].nilai === '' ? null : nilai[mahasiswaId].nilai, catatan_dosen: nilai[mahasiswaId].catatan || null },
        { preserveScroll: true, onFinish: () => (menyimpan.value = null) },
    );
};
const rilis = (nilaiDirilis: boolean) =>
    router.put(rute('ujian.rilis-nilai', props.ujian.id), { nilai_dirilis: nilaiDirilis }, { preserveScroll: true });

const labelSyarat = (s: Syarat) => (s?.dispensasi ? 'Dispensasi' : s?.memenuhi === false ? 'Tidak memenuhi' : s?.memenuhi ? 'Memenuhi' : '-');
</script>

<template>
    <Head :title="`${JENIS_UJIAN[props.ujian.jenis]} ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            {
                title: props.peran === 'admin' ? 'Jadwal Ujian' : 'Ujian',
                href: props.peran === 'admin' ? route('admin.ujian.index') : route('dosen.ujian.index'),
            },
            { title: `${JENIS_UJIAN[props.ujian.jenis]} ${props.kelasKuliah.kode_kelas}`, href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ JENIS_UJIAN[props.ujian.jenis] }} — {{ props.ujian.label_mode }}</h1>
                        <p class="deskripsi-halaman">{{ props.kelasKuliah.mata_kuliah?.nama_matkul }} · {{ props.kelasKuliah.kode_kelas }}</p>
                        <p class="deskripsi-halaman">
                            {{ formatTanggal(props.ujian.tanggal) }}, {{ jam(props.ujian.jam_mulai) }}–{{ jam(props.ujian.jam_akhir) }} ·
                            {{ props.ujian.mode === 'tatap_muka' ? (props.ujian.ruang ?? '-') : 'Online di SIA' }}
                            <span
                                v-if="props.ujian.status === 'draf'"
                                class="ml-1 rounded bg-[#f6f5f4] px-1.5 text-xs text-[#615d59] dark:bg-muted dark:text-muted-foreground"
                                >Draf – belum tampil ke mahasiswa</span
                            >
                        </p>
                        <p v-if="props.ujian.jenis === 'remidi'" class="deskripsi-halaman">
                            {{ keuangan ? 'Hanya peserta remidi yang tagihannya lunas.' : 'Hanya peserta daftar remidi kelas ini.' }}
                            <template v-if="props.batasNilaiRemidi"
                                >Nilai remidi bisa diisi sampai {{ formatTanggal(props.batasNilaiRemidi) }}.</template
                            >
                        </p>
                        <p v-else-if="jenisKhusus(props.ujian.jenis)" class="deskripsi-halaman">
                            Hanya pemohon susulan yang disetujui{{ keuangan ? ', tagihannya lunas,' : '' }} dan tidak mengikuti ujian utama.
                            Mengerjakan susulan tidak mengubah presensi.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <p v-if="props.ujian.petunjuk" class="alert-info whitespace-pre-line">
                    {{ props.ujian.petunjuk }}
                </p>

                <!-- Soal (mode unggah berkas) -->
                <section v-if="berkasMode" class="kartu p-6">
                    <h2 class="judul-bagian">Berkas soal</h2>
                    <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                        Mahasiswa baru bisa mengunduh soal saat ujian dimulai. Soal hanya bisa diubah sebelum jam mulai.
                    </p>
                    <ul
                        v-if="props.ujian.soal.length"
                        class="mt-4 divide-y divide-[#e6e6e6] rounded-lg border border-[#e6e6e6] dark:divide-border dark:border-border"
                    >
                        <li v-for="(nama, i) in props.ujian.soal" :key="nama" class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <a
                                :href="route('berkas.ujian-soal', [props.ujian.id, i])"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1.5 text-[#0075de] hover:underline"
                            >
                                <Paperclip class="size-4" /> {{ nama }}
                            </a>
                            <Button
                                v-if="!props.sudahMulai"
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                class="text-[#dd5b00]"
                                title="Hapus"
                                :aria-label="`Hapus ${nama}`"
                                @click="hapusSoal(i)"
                            >
                                <Trash2 />
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-[#dd5b00]">Belum ada berkas soal.</p>
                    <form v-if="!props.sudahMulai" class="mt-4 flex flex-wrap items-center gap-3" @submit.prevent="unggahSoal">
                        <input ref="inputSoal" type="file" multiple class="text-sm" aria-label="Pilih berkas soal" @change="pilihSoal" />
                        <Button type="submit" :disabled="soalForm.processing || !soalForm.soal.length"> <FileUp /> Unggah soal </Button>
                        <InputError class="w-full" :message="errorSoal" />
                    </form>
                </section>

                <!-- Pengumpulan & nilai -->
                <section v-if="berkasMode || tatapMuka" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="judul-bagian">{{ tatapMuka ? 'Nilai ujian' : 'Jawaban mahasiswa' }}</h2>
                            <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                                <template v-if="tatapMuka">{{ dinilai }} dari {{ props.peserta.length }} mahasiswa sudah dinilai.</template>
                                <template v-else>{{ terkumpul }} dari {{ props.peserta.length }} mahasiswa mengumpulkan.</template>
                                <template v-if="!props.sudahSelesai">{{ ' ' }}Nilai diisi setelah ujian selesai.</template>
                            </p>
                        </div>
                        <div v-if="props.sudahSelesai && !props.terkunci" class="flex items-center gap-2 text-sm">
                            <span :class="props.ujian.nilai_dirilis ? 'text-[#1a7f37]' : 'text-[#615d59] dark:text-muted-foreground'">
                                {{ props.ujian.nilai_dirilis ? 'Nilai terlihat oleh mahasiswa' : 'Nilai belum dirilis' }}
                            </span>
                            <Button variant="outline" size="sm" @click="rilis(!props.ujian.nilai_dirilis)">
                                {{ props.ujian.nilai_dirilis ? 'Sembunyikan' : 'Rilis nilai' }}
                            </Button>
                        </div>
                    </div>
                    <div class="tabel-wadah mt-4">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[880px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <th v-if="props.syaratAktif">Syarat</th>
                                        <th v-if="berkasMode">Jawaban</th>
                                        <th>Nilai (0–100)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(p, index) in props.peserta" :key="p.mahasiswa_id">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <span class="block font-medium text-black dark:text-foreground">{{ p.nama }}</span>
                                            <span class="teks-bantu block">{{ p.nim }}</span>
                                        </td>
                                        <td
                                            v-if="props.syaratAktif"
                                            :class="p.syarat?.memenuhi === false && !p.syarat?.dispensasi ? 'text-[#b42318]' : ''"
                                        >
                                            {{ labelSyarat(p.syarat) }}
                                        </td>
                                        <td v-if="berkasMode">
                                            <template v-if="p.jawaban">
                                                <a
                                                    v-for="(nama, i) in p.jawaban.nama_berkas"
                                                    :key="nama"
                                                    :href="route('berkas.ujian-jawaban', [p.jawaban.id, i])"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="block text-[#0075de] hover:underline"
                                                    >{{ nama }}</a
                                                >
                                                <span class="teks-bantu">Dikumpulkan {{ formatJamDari(p.jawaban.dikumpulkan_at) }}</span>
                                            </template>
                                            <span v-else class="text-[#a39e98]">Belum mengumpulkan</span>
                                        </td>
                                        <td>
                                            <form
                                                v-if="(tatapMuka || p.jawaban) && props.sudahSelesai && !props.terkunci"
                                                class="flex flex-wrap items-center gap-2"
                                                @submit.prevent="simpanNilai(p.mahasiswa_id)"
                                            >
                                                <Input
                                                    v-model="nilai[p.mahasiswa_id].nilai"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    class="w-24"
                                                    :aria-label="`Nilai ${p.nama}`"
                                                />
                                                <Input
                                                    v-model="nilai[p.mahasiswa_id].catatan"
                                                    maxlength="1000"
                                                    placeholder="Catatan (opsional)"
                                                    class="w-48"
                                                    :aria-label="`Catatan ${p.nama}`"
                                                />
                                                <Button type="submit" size="sm" variant="outline" :disabled="menyimpan === p.mahasiswa_id"
                                                    >Simpan</Button
                                                >
                                            </form>
                                            <span v-else-if="p.jawaban?.nilai">{{ p.jawaban.nilai }}</span>
                                            <span v-else class="text-[#a39e98]">-</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.peserta.length" class="baris-kosong">
                                        <td :colspan="3 + (props.syaratAktif ? 1 : 0) + (berkasMode ? 1 : 0)" class="tabel-kosong">
                                            Belum ada peserta ujian.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Mode soal di sistem -->
                <template v-if="soalMode">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Lembar soal</h2>
                        <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                            Disusun dengan editor quiz. Urutan soal dan opsi diacak per mahasiswa, satu kali pengerjaan, dan batas waktunya jam
                            selesai ujian. Soal terkunci setelah ujian dimulai.
                        </p>
                        <div v-if="props.lembarSoal" class="mt-4 flex flex-wrap items-center gap-4 text-sm">
                            <span>{{ props.lembarSoal.jumlah_soal }} soal · total {{ props.lembarSoal.total_poin }} poin</span>
                            <span>{{
                                props.lembarSoal.waktu_pengerjaan ? `Durasi ${props.lembarSoal.waktu_pengerjaan} menit` : 'Durasi: sampai jam selesai'
                            }}</span>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="rute('kelas-kuliah.quiz.show', [props.kelasKuliah.id, props.lembarSoal.id])">
                                    {{ props.sudahMulai ? 'Lihat soal' : 'Kelola soal' }} →
                                </Link>
                            </Button>
                            <span v-if="!props.lembarSoal.jumlah_soal && !props.sudahMulai" class="text-[#dd5b00]">Belum ada soal.</span>
                        </div>
                        <Button v-else-if="!props.sudahMulai" class="mt-4" @click="buatLembarSoal">Buat lembar soal</Button>
                        <p v-else class="mt-4 text-sm text-[#b42318]">Lembar soal tidak dibuat sebelum ujian dimulai.</p>
                    </section>

                    <section class="kartu p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="judul-bagian">Pengerjaan mahasiswa</h2>
                                <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                                    {{ terkumpul }} dari {{ props.peserta.length }} mahasiswa selesai mengerjakan.
                                </p>
                            </div>
                            <div v-if="props.sudahSelesai && !props.terkunci" class="flex items-center gap-2 text-sm">
                                <span :class="props.ujian.nilai_dirilis ? 'text-[#1a7f37]' : 'text-[#615d59] dark:text-muted-foreground'">
                                    {{ props.ujian.nilai_dirilis ? 'Nilai terlihat oleh mahasiswa' : 'Nilai belum dirilis' }}
                                </span>
                                <Button variant="outline" size="sm" @click="rilis(!props.ujian.nilai_dirilis)">
                                    {{ props.ujian.nilai_dirilis ? 'Sembunyikan' : 'Rilis nilai' }}
                                </Button>
                            </div>
                        </div>
                        <div class="tabel-wadah mt-4">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[780px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Mahasiswa</th>
                                            <th v-if="props.syaratAktif">Syarat</th>
                                            <th>Status</th>
                                            <th>Nilai (0–100)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, index) in props.peserta" :key="p.mahasiswa_id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td>
                                                <span class="block font-medium text-black dark:text-foreground">{{ p.nama }}</span>
                                                <span class="teks-bantu block">{{ p.nim }}</span>
                                            </td>
                                            <td
                                                v-if="props.syaratAktif"
                                                :class="p.syarat?.memenuhi === false && !p.syarat?.dispensasi ? 'text-[#b42318]' : ''"
                                            >
                                                {{ labelSyarat(p.syarat) }}
                                            </td>
                                            <td>
                                                <template v-if="p.pengerjaan?.selesai_at">
                                                    Selesai {{ formatJamDari(p.pengerjaan.selesai_at) }}
                                                    <span v-if="p.pengerjaan.auto_closed" class="teks-bantu block"
                                                        >ditutup otomatis saat waktu habis</span
                                                    >
                                                </template>
                                                <span v-else-if="p.pengerjaan" class="text-[#0b62b5]"
                                                    >Mengerjakan sejak {{ formatJamDari(p.pengerjaan.mulai_at) }}</span
                                                >
                                                <span v-else class="text-[#a39e98]">Belum mengerjakan</span>
                                            </td>
                                            <td>
                                                <template v-if="p.pengerjaan?.selesai_at && props.lembarSoal">
                                                    <span class="font-medium text-black dark:text-foreground">{{ p.pengerjaan.nilai ?? '-' }}</span>
                                                    <span class="teks-bantu ml-1"
                                                        >({{ p.pengerjaan.skor ?? '-' }} / {{ props.lembarSoal.total_poin }} poin)</span
                                                    >
                                                    <Link
                                                        :href="
                                                            rute('kelas-kuliah.quiz.attempts.show', [
                                                                props.kelasKuliah.id,
                                                                props.lembarSoal.id,
                                                                p.pengerjaan.id,
                                                            ])
                                                        "
                                                        class="ml-2 text-[#0075de] hover:underline"
                                                        >{{ p.pengerjaan.essay_belum_dinilai ? 'Koreksi esai' : 'Lihat jawaban' }}</Link
                                                    >
                                                </template>
                                                <span v-else class="text-[#a39e98]">-</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!props.peserta.length" class="baris-kosong">
                                            <td :colspan="props.syaratAktif ? 5 : 4" class="tabel-kosong">Belum ada peserta ujian.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </template>

                <section v-if="props.ujian.mode === 'tatap_muka'" class="kartu p-6">
                    <p v-if="jenisKhusus(props.ujian.jenis)" class="text-sm text-[#615d59] dark:text-muted-foreground">
                        Ujian {{ JENIS_UJIAN[props.ujian.jenis].toLowerCase() }} tatap muka:
                        <a :href="rute('ujian.daftar-hadir', props.ujian.id)" class="text-[#0075de] hover:underline">unduh daftar hadir (PDF)</a>
                        berisi pesertanya{{ keuangan ? ' yang sudah lunas' : '' }}.
                    </p>
                    <p v-else class="text-sm text-[#615d59] dark:text-muted-foreground">
                        Ujian tatap muka: daftar hadir dicetak dari halaman
                        <Link :href="rute('presensi.kelas', props.kelasKuliah.id)" class="text-[#0075de] hover:underline">Presensi kelas</Link>
                        (tab Peserta Ujian).
                    </p>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
