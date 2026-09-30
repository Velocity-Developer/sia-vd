<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import BagianLipat from '@/components/BagianLipat.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFitur } from '@/composables/useFitur';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam as jamPendek } from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { STATUS_TAGIHAN_REMIDI, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Download, Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref, watch } from 'vue';

const page = usePage<{
    flash: {
        success?: string;
        error?: string;
        jadwal_success?: string;
        jadwal_error?: string;
        materi_success?: string;
        materi_error?: string;
        tugas_success?: string;
        tugas_error?: string;
        quiz_success?: string;
        quiz_error?: string;
    };
}>();

// Bagian Jadwal, Materi, Tugas, Quiz, dan Nilai tertutup saat halaman dibuka. Bagian yang baru saja
// diubah (ada pesan flash-nya) dibuka otomatis agar pesannya terlihat.
const terbuka = reactive({ jadwal: false, materi: false, tugas: false, quiz: false, nilai: false });
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.jadwal_success || flash?.jadwal_error) terbuka.jadwal = true;
        if (flash?.materi_success || flash?.materi_error) terbuka.materi = true;
        if (flash?.tugas_success || flash?.tugas_error) terbuka.tugas = true;
        if (flash?.quiz_success || flash?.quiz_error) terbuka.quiz = true;
        if (flash?.success || flash?.error) terbuka.nilai = true;
    },
    { immediate: true },
);

type JadwalShow = {
    id: number;
    hari: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang?: { kode_ruang: string; nama_ruang: string; kapasitas: number } | null;
};

type MateriShow = {
    id: number;
    judul_materi: string;
    pertemuan_ke: number;
    file?: string[] | string | null;
    catatan?: string | null;
    uploader?: { name: string } | null;
};

type TugasShow = {
    id: number;
    judul_tugas: string;
    tenggat_waktu?: string | null;
    file?: string[] | string | null;
    catatan?: string | null;
    uploader?: { name: string } | null;
};

type KrsShow = {
    id: number;
    mahasiswa_id: number;
    nilai?: string | null;
    mahasiswa?: {
        nim: string;
        user?: { name: string } | null;
        prodi?: { nama_prodi: string } | null;
    } | null;
};

type QuizShow = {
    id: number;
    nama_quiz: string;
    catatan?: string | null;
    waktu_pengerjaan?: number | null;
    tenggat_waktu?: string | null;
    uploader?: { name: string } | null;
};

type KelasKuliahShowProps = {
    id: number;
    kode_kelas: string;
    tahunAkademik?: { tahun: string; semester: string } | null;
    tahun_akademik?: { tahun: string; semester: string } | null;
    kapasitas: number;
    dosen?: { id: number; nidn: string; jabatan_fungsional?: string; user?: { name: string } | null } | null;
    mata_kuliah?: {
        id: number;
        kode_matkul: string;
        nama_matkul: string;
        sks: number;
        semester: number;
        jenis: string;
        prodi?: { nama_prodi: string; jenjang: string; fakultas?: { nama_fakultas: string } | null } | null;
    } | null;
    mataKuliah?: {
        id: number;
        kode_matkul: string;
        nama_matkul: string;
        sks: number;
        semester: number;
        jenis: string;
        prodi?: { nama_prodi: string; jenjang: string; fakultas?: { nama_fakultas: string } | null } | null;
    } | null;
    jadwals?: JadwalShow[];
    materis?: MateriShow[];
    tugas?: TugasShow[];
    quizzes?: QuizShow[];
    krs?: KrsShow[];
};

type StatusNilai = {
    final: boolean;
    final_at: string | null;
    final_oleh: string | null;
    batas: string | null;
    batas_tahun_lewat: boolean;
    uas_belum_selesai: boolean;
    susulan_tertunda: number;
    tanpa_nilai: number;
};

type RemidiMahasiswa = {
    mahasiswa_id: number;
    nama: string | null;
    nim: string | null;
    nilai: string | null;
    huruf_remidi: boolean;
    ikut_uas: boolean | null;
    diusulkan: boolean;
    terpilih: boolean;
    nilai_awal?: string | null;
    tagihan?: StatusTagihanRemidi | null;
    nilai_remidi?: number | null;
};

type RemidiInfo = {
    ujian: { id: number; tanggal: string; jam_mulai: string; jam_akhir: string; selesai: boolean } | null;
    final_at: string | null;
    batas_nilai: string | null;
    jendela_terbuka: boolean;
    huruf_maks: string | null;
    dikunci_at: string | null;
    dikunci_oleh: string | null;
    ada_uas: boolean;
    mahasiswa: RemidiMahasiswa[];
};

type OtherClass = { id: number; kode_kelas: string; nama_matkul?: string | null };

const props = defineProps<{
    peran: Peran;
    kelasKuliah: KelasKuliahShowProps;
    otherClasses: OtherClass[];
    skalaNilai: string[];
    nilaiTerkunci: boolean;
    kelasTugasAkhir: boolean;
    statusNilai: StatusNilai;
    remidi: RemidiInfo | null;
    remidiTerbuka: number[];
    pesertaRemidi: number[];
    hurufRemidi: string[];
}>();
const rute = rutePeran(props.peran);
const { can } = usePermissions();
// Kelola jadwal, edit kelas, data dosen pengampu, dan pembatalan KRS hanya untuk admin.
const isAdmin = computed(() => props.peran === 'admin');

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    return String(val);
};

const dosen = () => props.kelasKuliah.dosen ?? null;
const matkul = () => props.kelasKuliah.mataKuliah ?? props.kelasKuliah.mata_kuliah ?? null;
const tahunAkademik = () => props.kelasKuliah.tahunAkademik ?? props.kelasKuliah.tahun_akademik ?? null;
const jam = (time: string) => (time ?? '').slice(0, 5);

const confirmOpen = ref(false);
const pendingJadwal = ref<JadwalShow | null>(null);
const confirmMateriOpen = ref(false);
const pendingMateri = ref<MateriShow | null>(null);
const confirmTugasOpen = ref(false);
const pendingTugas = ref<TugasShow | null>(null);
const confirmQuizOpen = ref(false);
const pendingQuiz = ref<QuizShow | null>(null);
const duplicateOpen = ref(false);
const duplicateType = ref<'materi' | 'tugas' | 'quiz'>('materi');
const duplicateItem = ref<MateriShow | TugasShow | QuizShow | null>(null);
const duplicateTargets = ref<number[]>([]);
const editingKrs = ref<number | null>(null);
const grade = ref('');
const gradeSearch = ref('');
const filteredKrs = computed(() => {
    const query = gradeSearch.value.trim().toLowerCase();

    return (props.kelasKuliah.krs ?? []).filter((krs) => {
        const name = krs.mahasiswa?.user?.name?.toLowerCase() ?? '';
        const nim = krs.mahasiswa?.nim?.toLowerCase() ?? '';

        return !query || name.includes(query) || nim.includes(query);
    });
});
const editGrade = (krs: KrsShow) => {
    editingKrs.value = krs.id;
    grade.value = krs.nilai ?? '';
};
const saveGrade = (krs: KrsShow) =>
    router.put(
        rute('kelas-kuliah.krs.nilai', [props.kelasKuliah.id, krs.id]),
        { nilai: grade.value || null },
        {
            preserveState: true,
            onSuccess: () => {
                editingKrs.value = null;
            },
        },
    );

const finalisasiOpen = ref(false);
// Setelah finalisasi, langkah berikutnya (kunci daftar remidi) langsung didekatkan ke dosen.
const finalisasi = () =>
    router.post(
        rute('kelas-kuliah.finalisasi-nilai', props.kelasKuliah.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (finalisasiOpen.value = false),
            onSuccess: () => nextTick(() => document.getElementById('daftar-remidi')?.scrollIntoView({ behavior: 'smooth', block: 'start' })),
        },
    );
const pesanFinalisasi = computed(() => {
    const kosong = props.statusNilai.tanpa_nilai;
    const peringatan = kosong > 0 ? `Masih ada ${kosong} mahasiswa tanpa huruf akhir. ` : '';

    return `${peringatan}Setelah difinalisasi, dosen tidak bisa lagi mengubah nilai akhir, nilai tugas, koreksi quiz, dan nilai ujian kelas ini. Hanya admin yang bisa membuka kembali.`;
});
const bukaOpen = ref(false);
const bukaSampai = ref('');
const bukaError = ref('');
const bukaKunci = () =>
    router.post(
        route('admin.kelas-kuliah.buka-kunci-nilai', props.kelasKuliah.id),
        { sampai: bukaSampai.value || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                bukaOpen.value = false;
                bukaSampai.value = '';
                bukaError.value = '';
            },
            onError: (errors) => (bukaError.value = errors.sampai ?? ''),
        },
    );

const remidiDipilih = ref<number[]>((props.remidi?.mahasiswa ?? []).filter((m) => m.terpilih).map((m) => m.mahasiswa_id));
const remidiDikunci = computed(() => !!props.remidi?.dikunci_at);
const remidiTampil = computed(() =>
    remidiDikunci.value ? (props.remidi?.mahasiswa ?? []).filter((m) => m.terpilih) : (props.remidi?.mahasiswa ?? []),
);
const kunciRemidiOpen = ref(false);
const kunciRemidi = () =>
    router.post(
        rute('kelas-kuliah.remidi.kunci', props.kelasKuliah.id),
        { mahasiswa_ids: remidiDipilih.value },
        { preserveScroll: true, onFinish: () => (kunciRemidiOpen.value = false) },
    );
const bukaRemidi = () => router.post(route('admin.kelas-kuliah.remidi.buka', props.kelasKuliah.id), {}, { preserveScroll: true });
const finalRemidiOpen = ref(false);
const finalisasiRemidi = () =>
    router.post(
        rute('kelas-kuliah.remidi.finalisasi', props.kelasKuliah.id),
        {},
        { preserveScroll: true, onFinish: () => (finalRemidiOpen.value = false) },
    );
const bukaFinalRemidi = () => router.post(route('admin.kelas-kuliah.remidi.buka-finalisasi', props.kelasKuliah.id), {}, { preserveScroll: true });
// Huruf akhir peserta remidi yang lunas dibuka setelah ujian remidi selesai, walau nilai kelas sudah final.
// Tanpa fitur keuangan, remidi tidak bersyarat bayar dan kolom tagihan tidak ditampilkan.
const keuangan = useFitur().aktif('keuangan');
// Huruf peserta remidi hanya berubah lewat remidi, walau admin membuka kembali kunci nilai kelas.
const jalurRemidi = (krs: KrsShow) => props.remidiTerbuka.includes(krs.mahasiswa_id);
const menungguRemidi = (krs: KrsShow) => props.pesertaRemidi.includes(krs.mahasiswa_id) && !jalurRemidi(krs);
// Nilai TA/Skripsi hanya diisi otomatis dari hasil pendadaran.
const bolehUbahNilai = (krs: KrsShow) => !props.kelasTugasAkhir && (jalurRemidi(krs) || (!props.nilaiTerkunci && !menungguRemidi(krs)));
const opsiHuruf = (krs: KrsShow) => (jalurRemidi(krs) ? props.hurufRemidi : props.skalaNilai);
const ketIkutUas = (m: RemidiMahasiswa) => (m.ikut_uas === null ? '—' : m.ikut_uas ? 'Ya' : 'Tidak');

const pendingCancelKrs = ref<KrsShow | null>(null);
const cancelKrs = (krs: KrsShow) => {
    pendingCancelKrs.value = krs;
};
const confirmCancelKrs = () => {
    if (!pendingCancelKrs.value) return;

    router.delete(rute('kelas-kuliah.krs.destroy', [props.kelasKuliah.id, pendingCancelKrs.value.id]), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            pendingCancelKrs.value = null;
        },
    });
};

const openDuplicate = (type: 'materi' | 'tugas' | 'quiz', item: MateriShow | TugasShow | QuizShow) => {
    duplicateType.value = type;
    duplicateItem.value = item;
    duplicateTargets.value = [];
    duplicateOpen.value = true;
};

const duplicate = () => {
    if (!duplicateItem.value || !duplicateTargets.value.length) return;

    router.post(
        route(`admin.kelas-kuliah.${duplicateType.value}.duplicate`, [props.kelasKuliah.id, duplicateItem.value.id]),
        { target_ids: duplicateTargets.value },
        {
            onFinish: () => {
                duplicateOpen.value = false;
                duplicateItem.value = null;
                duplicateTargets.value = [];
            },
        },
    );
};

const removeJadwal = (jadwal: JadwalShow) => {
    pendingJadwal.value = jadwal;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingJadwal.value) return;
    router.delete(rute('kelas-kuliah.jadwal.destroy', [props.kelasKuliah.id, pendingJadwal.value.id]), {
        preserveState: true,
        onFinish: () => {
            confirmOpen.value = false;
            pendingJadwal.value = null;
        },
    });
};

const removeMateri = (materi: MateriShow) => {
    pendingMateri.value = materi;
    confirmMateriOpen.value = true;
};

const confirmDeleteMateri = () => {
    if (!pendingMateri.value) return;
    router.delete(rute('kelas-kuliah.materi.destroy', [props.kelasKuliah.id, pendingMateri.value.id]), {
        preserveState: true,
        onFinish: () => {
            confirmMateriOpen.value = false;
            pendingMateri.value = null;
        },
    });
};

const removeTugas = (tugas: TugasShow) => {
    pendingTugas.value = tugas;
    confirmTugasOpen.value = true;
};

const confirmDeleteTugas = () => {
    if (!pendingTugas.value) return;
    router.delete(rute('kelas-kuliah.tugas.destroy', [props.kelasKuliah.id, pendingTugas.value.id]), {
        preserveState: true,
        onFinish: () => {
            confirmTugasOpen.value = false;
            pendingTugas.value = null;
        },
    });
};

const removeQuiz = (quiz: QuizShow) => {
    pendingQuiz.value = quiz;
    confirmQuizOpen.value = true;
};

const confirmDeleteQuiz = () => {
    if (!pendingQuiz.value) return;
    router.delete(rute('kelas-kuliah.quiz.destroy', [props.kelasKuliah.id, pendingQuiz.value.id]), {
        preserveState: true,
        onFinish: () => {
            confirmQuizOpen.value = false;
            pendingQuiz.value = null;
        },
    });
};

const fileName = (path: string | null | undefined) => (path ?? '').split('/').pop() ?? '-';

const materiFiles = (materi: MateriShow): string[] => {
    const f = materi.file;
    if (Array.isArray(f)) return f.filter((v): v is string => typeof v === 'string' && v !== '');
    if (typeof f === 'string' && f !== '') {
        try {
            const d = JSON.parse(f);
            if (Array.isArray(d)) return d.filter((v): v is string => typeof v === 'string' && v !== '');
        } catch {
            return [f];
        }
        return [f];
    }
    return [];
};

const tugasFiles = (tugas: TugasShow): string[] => {
    const f = tugas.file;
    if (Array.isArray(f)) return f.filter((v): v is string => typeof v === 'string' && v !== '');
    if (typeof f === 'string' && f !== '') {
        try {
            const d = JSON.parse(f);
            if (Array.isArray(d)) return d.filter((v): v is string => typeof v === 'string' && v !== '');
        } catch {
            return [f];
        }
        return [f];
    }
    return [];
};

const formatTenggat = (value: string | null | undefined): string => {
    if (!value) return '-';

    const [date, time] = value.replace('T', ' ').split(' ');

    if (!date) return String(value);

    const [year, month, day] = date.split('-');
    const [hour = '00', minute = '00'] = (time ?? '').split(':');

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    return `${day} ${months[Number(month) - 1]} ${year}, ${hour}:${minute}`;
};
</script>

<template>
    <Head :title="`Detail ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Kelas Kuliah', href: '#' }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Kelas Kuliah</h1>
                        <p class="deskripsi-halaman">Ringkasan kode kelas, tahun ajaran, dosen pengampu, dan mata kuliah.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline"><Link :href="rute('kelas-kuliah.index')">Kembali</Link></Button>
                        <Button v-if="can(`${props.peran}.presensi`) && !props.kelasTugasAkhir" as-child variant="outline">
                            <Link :href="rute('presensi.kelas', props.kelasKuliah.id)">Presensi</Link>
                        </Button>
                        <Button v-if="isAdmin" as-child><Link :href="rute('kelas-kuliah.edit', props.kelasKuliah.id)">Edit</Link></Button>
                    </div>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Kelas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Kelas</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(props.kelasKuliah.kode_kelas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Tahun Ajaran</dt>
                            <dd class="break-words text-sm font-medium text-black">
                                {{ tahunAkademik() ? `${tahunAkademik()?.tahun} ${tahunAkademik()?.semester}` : '-' }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kapasitas</dt>
                            <dd class="break-words text-sm font-medium text-black">
                                {{ props.kelasTugasAkhir ? 'Tanpa batas' : v(props.kelasKuliah.kapasitas) }}
                            </dd>
                        </div>
                    </dl>
                    <p v-if="props.kelasTugasAkhir" class="alert-info mt-4">
                        Kelas TA/Skripsi: tanpa jadwal, pertemuan, materi, tugas, quiz, dan ujian. Pembimbing ditetapkan per mahasiswa saat pengajuan
                        tugas akhir disetujui, dan nilai terisi otomatis dari hasil pendadaran. Mahasiswa yang belum selesai mengambil kelas TA lagi
                        di semester berikutnya.
                    </p>
                </section>

                <section v-if="isAdmin" class="kartu p-6">
                    <h2 class="judul-bagian">Dosen Pengampu</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Nama Dosen</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(dosen()?.user?.name) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">NIDN</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(dosen()?.nidn) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Jabatan Fungsional</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(dosen()?.jabatan_fungsional) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Mata Kuliah</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Mata Kuliah</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(matkul()?.kode_matkul) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="teks-bantu">Nama Mata Kuliah</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(matkul()?.nama_matkul) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">SKS</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(matkul()?.sks) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Semester</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(matkul()?.semester) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Jenis</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(matkul()?.jenis) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Program Studi</dt>
                            <dd class="break-words text-sm font-medium text-black">{{ v(matkul()?.prodi?.nama_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Fakultas</dt>
                            <dd class="break-words text-sm font-medium text-black">
                                {{ v(matkul()?.prodi?.fakultas?.nama_fakultas) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <template v-if="!props.kelasTugasAkhir">
                    <BagianLipat
                        v-model:open="terbuka.jadwal"
                        judul="Jadwal"
                        :jumlah="(props.kelasKuliah.jadwals ?? []).length"
                        keterangan="Hari, jam, dan ruang untuk kelas ini."
                    >
                        <template #keterangan>
                            <Link
                                :href="rute('jadwal.index', { kelas_id: props.kelasKuliah.id })"
                                class="text-xs font-medium text-[#0075de] hover:underline"
                                >Buka di menu Jadwal Kelas →</Link
                            >
                        </template>
                        <template v-if="isAdmin" #aksi>
                            <Button as-child size="sm">
                                <Link :href="rute('kelas-kuliah.jadwal.create', props.kelasKuliah.id)"><Plus />Tambah Jadwal</Link>
                            </Button>
                        </template>

                        <div v-if="page.props.flash?.jadwal_success" class="alert-sukses mt-4" role="alert">
                            {{ page.props.flash.jadwal_success }}
                        </div>
                        <div v-if="page.props.flash?.jadwal_error" class="alert-gagal mt-4" role="alert">{{ page.props.flash.jadwal_error }}</div>

                        <div class="tabel-wadah mt-4">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[640px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Hari</th>
                                            <th>Jam</th>
                                            <th>Ruang</th>
                                            <th v-if="isAdmin" class="kolom-aksi">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(jadwal, index) in props.kelasKuliah.jadwals ?? []" :key="jadwal.id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td class="font-medium text-black">{{ v(jadwal.hari) }}</td>
                                            <td>{{ jam(jadwal.jam_mulai) }}–{{ jam(jadwal.jam_akhir) }}</td>
                                            <td>
                                                <span class="block">{{ v(jadwal.ruang?.kode_ruang) }} — {{ v(jadwal.ruang?.nama_ruang) }}</span>
                                            </td>
                                            <td v-if="isAdmin" class="kolom-aksi">
                                                <div class="aksi-tabel">
                                                    <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                        <Link
                                                            :href="rute('kelas-kuliah.jadwal.edit', [props.kelasKuliah.id, jadwal.id])"
                                                            title="Edit"
                                                            aria-label="Edit"
                                                            ><Pencil
                                                        /></Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#dd5b00]"
                                                        title="Hapus"
                                                        aria-label="Hapus"
                                                        @click="removeJadwal(jadwal)"
                                                        ><Trash2
                                                    /></Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!(props.kelasKuliah.jadwals ?? []).length" class="baris-kosong">
                                            <td :colspan="isAdmin ? 5 : 4" class="tabel-kosong">
                                                Belum ada jadwal. Tambahkan hari, jam, dan ruang untuk kelas ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </BagianLipat>

                    <BagianLipat
                        v-model:open="terbuka.materi"
                        judul="Materi"
                        :jumlah="(props.kelasKuliah.materis ?? []).length"
                        keterangan="Bahan ajar per pertemuan untuk kelas ini."
                    >
                        <template #keterangan>
                            <Link
                                :href="rute('materi.index', { kelas_id: props.kelasKuliah.id })"
                                class="text-xs font-medium text-[#0075de] hover:underline"
                                >Buka di menu Materi →</Link
                            >
                        </template>
                        <template #aksi>
                            <Button as-child size="sm">
                                <Link :href="rute('kelas-kuliah.materi.create', props.kelasKuliah.id)"><Plus />Tambah Materi</Link>
                            </Button>
                        </template>

                        <div v-if="page.props.flash?.materi_success" class="alert-sukses mt-4" role="alert">
                            {{ page.props.flash.materi_success }}
                        </div>
                        <div v-if="page.props.flash?.materi_error" class="alert-gagal mt-4" role="alert">{{ page.props.flash.materi_error }}</div>

                        <div class="tabel-wadah mt-4">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[640px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Pertemuan</th>
                                            <th>Judul Materi</th>
                                            <th>Berkas</th>
                                            <th>Diunggah Oleh</th>
                                            <th class="kolom-aksi">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(materi, index) in props.kelasKuliah.materis ?? []" :key="materi.id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td class="font-medium text-black">Pertemuan {{ v(materi.pertemuan_ke) }}</td>
                                            <td>
                                                <span class="block font-medium text-black">{{ v(materi.judul_materi) }}</span>
                                                <span
                                                    v-if="materi.catatan"
                                                    class="mt-0.5 block max-w-md truncate text-xs text-[#a39e98]"
                                                    :title="String(materi.catatan)"
                                                    >{{ materi.catatan }}</span
                                                >
                                            </td>
                                            <td>
                                                <ul v-if="materiFiles(materi).length" class="space-y-1">
                                                    <li v-for="(path, fileIndex) in materiFiles(materi)" :key="path">
                                                        <a
                                                            :href="route('berkas.materi', [materi.id, fileIndex])"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="inline-flex items-center gap-1.5 text-[#0075de] hover:underline"
                                                        >
                                                            <Download class="size-4" />{{ fileName(path) }}
                                                        </a>
                                                    </li>
                                                </ul>
                                                <span v-else>-</span>
                                            </td>
                                            <td>
                                                {{ v(materi.uploader?.name) }}
                                            </td>
                                            <td class="kolom-aksi">
                                                <div class="aksi-tabel">
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#2a9d99]"
                                                        title="Duplikasi"
                                                        aria-label="Duplikasi"
                                                        @click="openDuplicate('materi', materi)"
                                                        ><Copy
                                                    /></Button>
                                                    <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                        <Link
                                                            :href="rute('kelas-kuliah.materi.edit', [props.kelasKuliah.id, materi.id])"
                                                            title="Edit"
                                                            aria-label="Edit"
                                                            ><Pencil
                                                        /></Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#dd5b00]"
                                                        title="Hapus"
                                                        aria-label="Hapus"
                                                        @click="removeMateri(materi)"
                                                        ><Trash2
                                                    /></Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!(props.kelasKuliah.materis ?? []).length" class="baris-kosong">
                                            <td colspan="6" class="tabel-kosong">
                                                Belum ada materi. Tambahkan judul, pertemuan, berkas, dan catatan untuk kelas ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </BagianLipat>

                    <BagianLipat
                        v-model:open="terbuka.tugas"
                        judul="Tugas"
                        :jumlah="(props.kelasKuliah.tugas ?? []).length"
                        keterangan="Daftar tugas beserta tenggat waktu untuk kelas ini."
                    >
                        <template #keterangan>
                            <Link
                                :href="rute('tugas.index', { kelas_id: props.kelasKuliah.id })"
                                class="text-xs font-medium text-[#0075de] hover:underline"
                                >Buka di menu Tugas →</Link
                            >
                        </template>
                        <template #aksi>
                            <Button as-child size="sm">
                                <Link :href="rute('kelas-kuliah.tugas.create', props.kelasKuliah.id)"><Plus />Tambah Tugas</Link>
                            </Button>
                        </template>

                        <div v-if="page.props.flash?.tugas_success" class="alert-sukses mt-4" role="alert">{{ page.props.flash.tugas_success }}</div>
                        <div v-if="page.props.flash?.tugas_error" class="alert-gagal mt-4" role="alert">{{ page.props.flash.tugas_error }}</div>

                        <div class="tabel-wadah mt-4">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[640px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Judul Tugas</th>
                                            <th>Tenggat Waktu</th>
                                            <th>Berkas</th>
                                            <th>Diunggah Oleh</th>
                                            <th class="kolom-aksi">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(tugas, index) in props.kelasKuliah.tugas ?? []" :key="tugas.id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td>
                                                <span class="block font-medium text-black">{{ v(tugas.judul_tugas) }}</span>
                                                <span
                                                    v-if="tugas.catatan"
                                                    class="mt-0.5 block max-w-md truncate text-xs text-[#a39e98]"
                                                    :title="String(tugas.catatan)"
                                                    >{{ tugas.catatan }}</span
                                                >
                                            </td>
                                            <td>
                                                {{ formatTenggat(tugas.tenggat_waktu) }}
                                            </td>
                                            <td>
                                                <ul v-if="tugasFiles(tugas).length" class="space-y-1">
                                                    <li v-for="(path, fileIndex) in tugasFiles(tugas)" :key="path">
                                                        <a
                                                            :href="route('berkas.tugas', [tugas.id, fileIndex])"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="inline-flex items-center gap-1.5 text-[#0075de] hover:underline"
                                                        >
                                                            <Download class="size-4" />{{ fileName(path) }}
                                                        </a>
                                                    </li>
                                                </ul>
                                                <span v-else>-</span>
                                            </td>
                                            <td>
                                                {{ v(tugas.uploader?.name) }}
                                            </td>
                                            <td class="kolom-aksi">
                                                <div class="aksi-tabel">
                                                    <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                        <Link
                                                            :href="rute('kelas-kuliah.tugas.show', [props.kelasKuliah.id, tugas.id])"
                                                            title="Lihat detail"
                                                            aria-label="Lihat detail"
                                                            ><Eye
                                                        /></Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#2a9d99]"
                                                        title="Duplikasi"
                                                        aria-label="Duplikasi"
                                                        @click="openDuplicate('tugas', tugas)"
                                                        ><Copy
                                                    /></Button>
                                                    <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                        <Link
                                                            :href="rute('kelas-kuliah.tugas.edit', [props.kelasKuliah.id, tugas.id])"
                                                            title="Edit"
                                                            aria-label="Edit"
                                                            ><Pencil
                                                        /></Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#dd5b00]"
                                                        title="Hapus"
                                                        aria-label="Hapus"
                                                        @click="removeTugas(tugas)"
                                                        ><Trash2
                                                    /></Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!(props.kelasKuliah.tugas ?? []).length" class="baris-kosong">
                                            <td colspan="6" class="tabel-kosong">
                                                Belum ada tugas. Tambahkan judul, tenggat waktu, berkas, dan catatan untuk kelas ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </BagianLipat>

                    <BagianLipat
                        v-model:open="terbuka.quiz"
                        judul="Quiz"
                        :jumlah="(props.kelasKuliah.quizzes ?? []).length"
                        keterangan="Daftar quiz beserta durasi dan tenggat waktu untuk kelas ini."
                    >
                        <template #keterangan>
                            <Link
                                :href="rute('quiz.index', { kelas_id: props.kelasKuliah.id })"
                                class="text-xs font-medium text-[#0075de] hover:underline"
                                >Buka di menu Quiz →</Link
                            >
                        </template>
                        <template #aksi>
                            <Button as-child size="sm">
                                <Link :href="rute('kelas-kuliah.quiz.create', props.kelasKuliah.id)"><Plus />Tambah Quiz</Link>
                            </Button>
                        </template>

                        <div v-if="page.props.flash?.quiz_success" class="alert-sukses mt-4" role="alert">{{ page.props.flash.quiz_success }}</div>
                        <div v-if="page.props.flash?.quiz_error" class="alert-gagal mt-4" role="alert">{{ page.props.flash.quiz_error }}</div>

                        <div class="tabel-wadah mt-4">
                            <div class="tabel-gulir">
                                <table class="tabel min-w-[640px]">
                                    <thead>
                                        <tr>
                                            <th class="kolom-no">No</th>
                                            <th>Nama Quiz</th>
                                            <th>Durasi</th>
                                            <th>Tenggat Waktu</th>
                                            <th>Diunggah Oleh</th>
                                            <th class="kolom-aksi">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(quiz, index) in props.kelasKuliah.quizzes ?? []" :key="quiz.id">
                                            <td class="kolom-no">{{ index + 1 }}</td>
                                            <td>
                                                <span class="block font-medium text-black">{{ v(quiz.nama_quiz) }}</span>
                                                <span
                                                    v-if="quiz.catatan"
                                                    class="mt-0.5 block max-w-md truncate text-xs text-[#a39e98]"
                                                    :title="String(quiz.catatan)"
                                                    >{{ quiz.catatan }}</span
                                                >
                                            </td>
                                            <td>
                                                {{ quiz.waktu_pengerjaan ? `${quiz.waktu_pengerjaan} menit` : '-' }}
                                            </td>
                                            <td>
                                                {{ formatTenggat(quiz.tenggat_waktu) }}
                                            </td>
                                            <td>
                                                {{ v(quiz.uploader?.name) }}
                                            </td>
                                            <td class="kolom-aksi">
                                                <div class="aksi-tabel">
                                                    <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                        <Link
                                                            :href="rute('kelas-kuliah.quiz.show', [props.kelasKuliah.id, quiz.id])"
                                                            title="Detail"
                                                            aria-label="Detail"
                                                            ><Eye
                                                        /></Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#2a9d99]"
                                                        title="Duplikasi"
                                                        aria-label="Duplikasi"
                                                        @click="openDuplicate('quiz', quiz)"
                                                        ><Copy
                                                    /></Button>
                                                    <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                        <Link
                                                            :href="rute('kelas-kuliah.quiz.edit', [props.kelasKuliah.id, quiz.id])"
                                                            title="Edit"
                                                            aria-label="Edit"
                                                            ><Pencil
                                                        /></Link>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="icon-sm"
                                                        class="text-[#dd5b00]"
                                                        title="Hapus"
                                                        aria-label="Hapus"
                                                        @click="removeQuiz(quiz)"
                                                        ><Trash2
                                                    /></Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!(props.kelasKuliah.quizzes ?? []).length" class="baris-kosong">
                                            <td colspan="6" class="tabel-kosong">
                                                Belum ada quiz. Tambahkan nama, durasi, tenggat waktu, dan catatan untuk kelas ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </BagianLipat>
                </template>

                <BagianLipat v-model:open="terbuka.nilai" judul="Nilai Mahasiswa" :jumlah="(props.kelasKuliah.krs ?? []).length">
                    <template v-if="!props.kelasTugasAkhir" #keterangan>
                        <p v-if="props.statusNilai.final_at" class="text-sm text-[#31302e]">
                            <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-semibold text-[#0075de]">Final</span>
                            Difinalisasi {{ formatTanggal(props.statusNilai.final_at) }}
                            <template v-if="props.statusNilai.final_oleh">oleh {{ props.statusNilai.final_oleh }}</template>
                        </p>
                        <p v-else-if="props.statusNilai.final" class="text-sm text-[#31302e]">
                            <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-semibold text-[#0075de]">Terkunci</span>
                            Batas input nilai {{ formatTanggal(props.statusNilai.batas) }} sudah lewat.
                        </p>
                        <p v-else-if="props.statusNilai.batas" class="text-sm text-[#615d59]">
                            Batas input nilai: <span class="font-medium text-black">{{ formatTanggal(props.statusNilai.batas) }}</span>
                        </p>
                    </template>
                    <template v-if="!props.kelasTugasAkhir" #aksi>
                        <template v-if="!props.statusNilai.final && !props.nilaiTerkunci">
                            <Button
                                size="sm"
                                :disabled="props.statusNilai.uas_belum_selesai || props.statusNilai.susulan_tertunda > 0"
                                :title="
                                    props.statusNilai.uas_belum_selesai || props.statusNilai.susulan_tertunda
                                        ? 'Tunggu sampai UAS (dan susulannya) selesai'
                                        : undefined
                                "
                                @click="finalisasiOpen = true"
                                >Finalisasi Nilai</Button
                            >
                        </template>
                        <Button v-if="isAdmin && props.statusNilai.final" size="sm" variant="outline" @click="bukaOpen = true"
                            >Buka Kunci Nilai</Button
                        >
                    </template>
                    <p v-if="props.statusNilai.uas_belum_selesai && !props.statusNilai.final" class="teks-bantu mt-2">
                        Nilai bisa difinalisasi setelah UAS selesai.
                    </p>
                    <p v-else-if="props.statusNilai.susulan_tertunda && !props.statusNilai.final" class="teks-bantu mt-2">
                        Nilai bisa difinalisasi setelah UAS susulan {{ props.statusNilai.susulan_tertunda }} mahasiswa selesai atau gugur.
                    </p>
                    <div class="bilah-filter mt-4">
                        <div class="kolom-cari">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input v-model="gradeSearch" placeholder="Cari nama mahasiswa atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                        </div>
                        <p class="info-jumlah sm:ml-auto">
                            <span class="font-medium text-black">{{ filteredKrs.length }}</span> mahasiswa
                        </p>
                    </div>
                    <div v-if="page.props.flash?.success" class="alert-sukses mt-4" role="alert">{{ page.props.flash.success }}</div>
                    <div v-if="page.props.flash?.error" class="alert-gagal mt-4" role="alert">{{ page.props.flash.error }}</div>
                    <div class="tabel-wadah mt-4">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[720px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <th>Program Studi</th>
                                        <th>Nilai</th>
                                        <th class="kolom-aksi">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(krs, index) in filteredKrs" :key="krs.id">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td class="font-medium text-black">
                                            {{ krs.mahasiswa?.user?.name ?? '-' }}
                                            <span class="font-normal text-[#615d59]">({{ krs.mahasiswa?.nim ?? '-' }})</span>
                                        </td>
                                        <td>
                                            {{ krs.mahasiswa?.prodi?.nama_prodi ?? '-' }}
                                        </td>
                                        <td>
                                            <select v-if="editingKrs === krs.id" v-model="grade" aria-label="Nilai" class="isian isian-pilih w-36">
                                                <option v-if="!jalurRemidi(krs)" value="">— Kosong —</option>
                                                <option v-for="option in opsiHuruf(krs)" :key="option" :value="option">
                                                    {{ option }}
                                                </option>
                                            </select>
                                            <span v-else class="font-semibold text-black">{{ krs.nilai ?? '-' }}</span>
                                        </td>
                                        <td class="kolom-aksi">
                                            <div class="aksi-tabel">
                                                <template v-if="editingKrs === krs.id">
                                                    <Button size="sm" variant="outline" @click="editingKrs = null">Batal</Button>
                                                    <Button size="sm" @click="saveGrade(krs)">Simpan</Button>
                                                </template>
                                                <template v-else-if="props.kelasTugasAkhir">
                                                    <span class="teks-bantu">Dari hasil pendadaran</span>
                                                    <Button v-if="isAdmin && !krs.nilai" size="sm" variant="destructive" @click="cancelKrs(krs)"
                                                        >Batalkan KRS</Button
                                                    >
                                                </template>
                                                <span v-else-if="!bolehUbahNilai(krs)" class="teks-bantu">{{
                                                    menungguRemidi(krs) && !props.nilaiTerkunci ? 'Diubah lewat remidi' : 'Nilai terkunci'
                                                }}</span>
                                                <template v-else>
                                                    <Button size="sm" variant="outline" @click="editGrade(krs)">{{
                                                        jalurRemidi(krs) ? 'Ubah Nilai Remidi' : 'Ubah Nilai'
                                                    }}</Button>
                                                    <Button v-if="isAdmin && !krs.nilai" size="sm" variant="destructive" @click="cancelKrs(krs)"
                                                        >Batalkan KRS</Button
                                                    >
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredKrs.length" class="baris-kosong">
                                        <td colspan="5" class="tabel-kosong">Tidak ada mahasiswa yang cocok.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </BagianLipat>

                <section v-if="props.remidi" id="daftar-remidi" class="kartu p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="judul-bagian">Daftar Remidi</h2>
                            <p v-if="remidiDikunci" class="mt-2 text-sm text-[#31302e]">
                                <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-semibold text-[#0075de]">Dikunci</span>
                                {{ formatTanggal(props.remidi.dikunci_at) }}
                                <template v-if="props.remidi.dikunci_oleh">oleh {{ props.remidi.dikunci_oleh }}</template>
                                · {{ remidiTampil.length }} peserta
                            </p>
                            <p v-else class="mt-2 max-w-2xl text-sm text-[#615d59]">
                                Mahasiswa dengan huruf akhir tidak lulus atau boleh diulang<template v-if="props.remidi.ada_uas">
                                    yang ikut UAS</template
                                >
                                sudah dicentang otomatis. Tambah atau coret sesuai kebutuhan, lalu kunci daftar agar
                                {{ keuangan ? 'tagihan remidi bisa diterbitkan' : 'ujian remidi bisa dijadwalkan' }}.
                            </p>
                            <p v-if="!props.remidi.ada_uas && !remidiDikunci" class="teks-bantu mt-1">
                                Kelas ini tidak punya jadwal UAS terbit di sistem, jadi keikutsertaan UAS tidak diperiksa.
                            </p>
                            <template v-if="remidiDikunci && remidiTampil.length">
                                <p class="mt-1 text-sm text-[#615d59]">
                                    <template v-if="props.remidi.ujian">
                                        Ujian remidi {{ formatTanggal(props.remidi.ujian.tanggal) }}, {{ jamPendek(props.remidi.ujian.jam_mulai) }}–{{
                                            jamPendek(props.remidi.ujian.jam_akhir)
                                        }}
                                        <Link :href="rute('ujian.show', props.remidi.ujian.id)" class="text-[#0075de] hover:underline"
                                            >Buka ujian</Link
                                        >
                                    </template>
                                    <template v-else>Ujian remidi belum dijadwalkan admin.</template>
                                </p>
                                <p v-if="props.remidi.final_at" class="mt-1 text-sm text-[#31302e]">
                                    <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-semibold text-[#0075de]">Remidi final</span>
                                    {{ formatTanggal(props.remidi.final_at) }}
                                </p>
                                <p v-else-if="props.remidi.ujian?.selesai && props.remidi.jendela_terbuka" class="mt-1 text-sm text-[#615d59]">
                                    Huruf akhir peserta{{ keuangan ? ' lunas' : '' }} bisa diubah di tabel Nilai Mahasiswa<template
                                        v-if="props.remidi.huruf_maks"
                                    >
                                        (paling tinggi {{ props.remidi.huruf_maks }})</template
                                    ><template v-if="props.remidi.batas_nilai"> sampai {{ formatTanggal(props.remidi.batas_nilai) }}</template
                                    >.
                                </p>
                                <p v-else-if="props.remidi.ujian?.selesai" class="mt-1 text-sm text-[#615d59]">
                                    Batas input nilai remidi sudah lewat.
                                </p>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="!remidiDikunci" size="sm" @click="kunciRemidiOpen = true">Kunci Daftar ({{ remidiDipilih.length }})</Button>
                            <Button
                                v-if="remidiDikunci && props.remidi.ujian?.selesai && props.remidi.jendela_terbuka"
                                size="sm"
                                @click="finalRemidiOpen = true"
                                >Finalisasi Remidi</Button
                            >
                            <Button v-if="isAdmin && props.remidi.final_at" size="sm" variant="outline" @click="bukaFinalRemidi"
                                >Buka Finalisasi Remidi</Button
                            >
                            <Button
                                v-if="isAdmin && remidiDikunci && !(keuangan && remidiTampil.some((m) => m.tagihan))"
                                size="sm"
                                variant="outline"
                                @click="bukaRemidi"
                                >Buka Kunci Daftar</Button
                            >
                        </div>
                    </div>
                    <div class="tabel-wadah mt-4">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[640px]">
                                <thead>
                                    <tr>
                                        <th v-if="!remidiDikunci" class="w-10"><span class="sr-only">Pilih</span></th>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <template v-if="remidiDikunci">
                                            <th>Nilai Awal</th>
                                            <th v-if="keuangan">Tagihan</th>
                                            <th>Nilai Remidi</th>
                                            <th>Huruf Akhir</th>
                                        </template>
                                        <template v-else>
                                            <th>Nilai</th>
                                            <th>Ikut UAS</th>
                                            <th>Keterangan</th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(m, index) in remidiTampil" :key="m.mahasiswa_id">
                                        <td v-if="!remidiDikunci">
                                            <input
                                                :id="`remidi-${m.mahasiswa_id}`"
                                                v-model="remidiDipilih"
                                                type="checkbox"
                                                :value="m.mahasiswa_id"
                                                class="size-4 accent-[#0075de]"
                                            />
                                        </td>
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td class="font-medium text-black">
                                            <label :for="`remidi-${m.mahasiswa_id}`">
                                                {{ m.nama ?? '-' }} <span class="text-[#615d59]">({{ m.nim ?? '-' }})</span>
                                            </label>
                                        </td>
                                        <template v-if="remidiDikunci">
                                            <td class="font-semibold text-[#dd5b00]">{{ m.nilai_awal ?? '-' }}</td>
                                            <td v-if="keuangan">
                                                <span
                                                    v-if="m.tagihan"
                                                    class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="STATUS_TAGIHAN_REMIDI[m.tagihan].kelas"
                                                    >{{ STATUS_TAGIHAN_REMIDI[m.tagihan].label }}</span
                                                >
                                                <span v-else class="teks-bantu">Belum terbit</span>
                                            </td>
                                            <td>{{ m.nilai_remidi ?? '-' }}</td>
                                            <td class="font-semibold text-black">{{ m.nilai ?? '-' }}</td>
                                        </template>
                                        <template v-else>
                                            <td class="font-semibold" :class="m.huruf_remidi ? 'text-[#dd5b00]' : ''">
                                                {{ m.nilai ?? '-' }}
                                            </td>
                                            <td :class="{ 'text-[#dd5b00]': m.ikut_uas === false }">
                                                {{ ketIkutUas(m) }}
                                            </td>
                                            <td class="teks-bantu">
                                                <span v-if="m.diusulkan">Usulan otomatis</span>
                                                <span v-else-if="m.huruf_remidi && m.ikut_uas === false">Tidak ikut UAS</span>
                                                <span v-else-if="remidiDipilih.includes(m.mahasiswa_id)">Ditambahkan manual</span>
                                            </td>
                                        </template>
                                    </tr>
                                    <tr v-if="!remidiTampil.length" class="baris-kosong">
                                        <td colspan="6" class="tabel-kosong">
                                            {{ remidiDikunci ? 'Tidak ada peserta remidi di kelas ini.' : 'Belum ada mahasiswa di kelas ini.' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <div
                    v-if="duplicateOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
                    @click.self="duplicateOpen = false"
                >
                    <div class="kartu flex max-h-[calc(100vh-2rem)] w-full max-w-md flex-col p-6 shadow-xl">
                        <h2 class="judul-bagian">Duplikasi {{ duplicateType }}</h2>
                        <p class="deskripsi-halaman">Pilih kelas tujuan.</p>
                        <div class="mt-4 flex max-h-80 flex-col gap-3 overflow-y-auto pr-2">
                            <label v-for="kelas in props.otherClasses" :key="kelas.id" class="flex gap-2 text-sm">
                                <input v-model="duplicateTargets" type="checkbox" :value="kelas.id" class="size-4 accent-[#0075de]" />
                                {{ kelas.kode_kelas }} — {{ kelas.nama_matkul ?? '-' }}
                            </label>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <Button variant="outline" @click="duplicateOpen = false">Batal</Button>
                            <Button :disabled="!duplicateTargets.length" @click="duplicate">Duplikasi</Button>
                        </div>
                    </div>
                </div>
                <AlertModal
                    :open="pendingCancelKrs !== null"
                    :description="`Batalkan KRS ${pendingCancelKrs?.mahasiswa?.user?.name ?? ''} di kelas ini? Kursinya akan dilepas untuk mahasiswa lain.`"
                    confirm-text="Batalkan KRS"
                    cancel-text="Kembali"
                    @update:open="!$event && (pendingCancelKrs = null)"
                    @confirm="confirmCancelKrs"
                    @cancel="pendingCancelKrs = null"
                />
                <AlertModal
                    :open="finalRemidiOpen"
                    title="Finalisasi remidi?"
                    description="Nilai remidi dan huruf akhir peserta remidi akan terkunci. Hanya admin yang bisa membukanya kembali."
                    confirm-text="Finalisasi"
                    cancel-text="Batal"
                    @update:open="finalRemidiOpen = $event"
                    @confirm="finalisasiRemidi"
                    @cancel="finalRemidiOpen = false"
                />
                <AlertModal
                    :open="kunciRemidiOpen"
                    title="Kunci daftar remidi?"
                    :description="`${remidiDipilih.length} mahasiswa akan masuk daftar remidi. Setelah dikunci, daftar hanya bisa diubah bila admin membukanya kembali.`"
                    confirm-text="Kunci"
                    cancel-text="Batal"
                    @update:open="kunciRemidiOpen = $event"
                    @confirm="kunciRemidi"
                    @cancel="kunciRemidiOpen = false"
                />
                <AlertModal
                    :open="finalisasiOpen"
                    title="Finalisasi nilai?"
                    :description="pesanFinalisasi"
                    confirm-text="Finalisasi"
                    cancel-text="Batal"
                    @update:open="finalisasiOpen = $event"
                    @confirm="finalisasi"
                    @cancel="finalisasiOpen = false"
                />
                <div v-if="bukaOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="bukaOpen = false">
                    <div class="kartu w-full max-w-md p-6 shadow-xl">
                        <h3 class="judul-bagian">Buka kunci nilai</h3>
                        <p class="deskripsi-halaman">
                            Dosen bisa mengubah nilai kelas ini lagi sampai difinalisasi ulang atau sampai batas di bawah lewat.
                            <template v-if="props.remidi?.dikunci_at">Huruf akhir peserta remidi tetap hanya berubah lewat remidi.</template>
                        </p>
                        <label class="mt-4 grid gap-2">
                            <span class="label-isian"
                                >Batas baru untuk kelas ini<span v-if="!props.statusNilai.batas_tahun_lewat" class="font-normal text-[#a39e98]">
                                    (opsional)</span
                                ></span
                            >
                            <input v-model="bukaSampai" type="date" class="isian" />
                            <span v-if="props.statusNilai.batas_tahun_lewat" class="teks-bantu"
                                >Batas input nilai tahun akademik sudah lewat, jadi kelas ini perlu batas sendiri.</span
                            >
                            <span v-if="bukaError" class="text-xs text-[#dd5b00]">{{ bukaError }}</span>
                        </label>
                        <div class="mt-6 flex justify-end gap-2">
                            <Button variant="outline" @click="bukaOpen = false">Batal</Button>
                            <Button @click="bukaKunci">Buka Kunci</Button>
                        </div>
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
                <AlertModal
                    :open="confirmMateriOpen"
                    description="Anda yakin ingin menghapus materi ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmMateriOpen = $event"
                    @confirm="confirmDeleteMateri"
                    @cancel="confirmMateriOpen = false"
                />
                <AlertModal
                    :open="confirmTugasOpen"
                    description="Anda yakin ingin menghapus tugas ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmTugasOpen = $event"
                    @confirm="confirmDeleteTugas"
                    @cancel="confirmTugasOpen = false"
                />
                <AlertModal
                    :open="confirmQuizOpen"
                    description="Anda yakin ingin menghapus quiz ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmQuizOpen = $event"
                    @confirm="confirmDeleteQuiz"
                    @cancel="confirmQuizOpen = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
