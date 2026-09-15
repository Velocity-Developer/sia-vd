<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Copy, Download, Eye, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

type OtherClass = {
    id: number;
    kode_kelas: string;
    mataKuliah?: { nama_matkul: string } | null;
};

const props = defineProps<{ kelasKuliah: KelasKuliahShowProps; otherClasses: OtherClass[] }>();

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
        route('admin.kelas-kuliah.krs.nilai', [props.kelasKuliah.id, krs.id]),
        { nilai: grade.value },
        {
            onSuccess: () => {
                editingKrs.value = null;
            },
        },
    );

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
    router.delete(route('admin.kelas-kuliah.jadwal.destroy', [props.kelasKuliah.id, pendingJadwal.value.id]), {
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
    router.delete(route('admin.kelas-kuliah.materi.destroy', [props.kelasKuliah.id, pendingMateri.value.id]), {
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
    router.delete(route('admin.kelas-kuliah.tugas.destroy', [props.kelasKuliah.id, pendingTugas.value.id]), {
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
    router.delete(route('admin.kelas-kuliah.quiz.destroy', [props.kelasKuliah.id, pendingQuiz.value.id]), {
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
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Detail Kelas Kuliah</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Ringkasan kode kelas, tahun ajaran, dosen pengampu, dan mata kuliah.</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('admin.kelas-kuliah.index')"
                            ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                        >
                        <Link :href="route('admin.kelas-kuliah.edit', props.kelasKuliah.id)"
                            ><Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Edit</Button></Link
                        >
                    </div>
                </div>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Informasi Kelas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Kelas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.kelasKuliah.kode_kelas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Tahun Ajaran</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">
                                {{
                                    tahunAkademik()
                                        ? `${tahunAkademik()?.tahun} ${tahunAkademik()?.semester}`
                                        : '-'
                                }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kapasitas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(props.kelasKuliah.kapasitas) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Dosen Pengampu</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Nama Dosen</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(dosen()?.user?.name) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">NIDN</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(dosen()?.nidn) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Jabatan Fungsional</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(dosen()?.jabatan_fungsional) }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Kode Mata Kuliah</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(matkul()?.kode_matkul) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Nama Mata Kuliah</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(matkul()?.nama_matkul) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">SKS</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(matkul()?.sks) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Semester</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(matkul()?.semester) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Jenis</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(matkul()?.jenis) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Program Studi</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">{{ v(matkul()?.prodi?.nama_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="text-xs font-medium uppercase tracking-[0.04em] text-[#a39e98]">Fakultas</dt>
                            <dd class="break-words text-[15px] font-medium leading-5 text-black">
                                {{ v(matkul()?.prodi?.fakultas?.nama_fakultas) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jadwal</h2>
                            <p class="text-sm leading-5 text-[#615d59]">Hari, jam, dan ruang untuk kelas ini.</p>
                        </div>
                        <Link :href="route('admin.kelas-kuliah.jadwal.create', props.kelasKuliah.id)">
                            <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"><Plus class="mr-1 size-4" />Tambah Jadwal</Button>
                        </Link>
                    </div>

                    <div
                        v-if="page.props.flash?.jadwal_success"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                        role="alert"
                    >
                        {{ page.props.flash.jadwal_success }}
                    </div>
                    <div
                        v-if="page.props.flash?.jadwal_error"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                        role="alert"
                    >
                        {{ page.props.flash.jadwal_error }}
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Hari</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jam</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Ruang</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr
                                        v-for="jadwal in props.kelasKuliah.jadwals ?? []"
                                        :key="jadwal.id"
                                        class="transition-colors hover:bg-[#f6f5f4]/60"
                                    >
                                        <td class="px-4 py-3 text-[15px] font-medium leading-5 text-black">{{ v(jadwal.hari) }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            {{ jam(jadwal.jam_mulai) }}–{{ jam(jadwal.jam_akhir) }}
                                        </td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            <span class="block">{{ v(jadwal.ruang?.kode_ruang) }} — {{ v(jadwal.ruang?.nama_ruang) }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-1.5">
                                                <Link
                                                    :href="route('admin.kelas-kuliah.jadwal.edit', [props.kelasKuliah.id, jadwal.id])"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Pencil class="size-4"
                                                    /></Button>
                                                </Link>
                                                <button type="button" title="Hapus" aria-label="Hapus" @click="removeJadwal(jadwal)">
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Trash2 class="size-4"
                                                    /></Button>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!(props.kelasKuliah.jadwals ?? []).length">
                                        <td colspan="4" class="px-4 py-10 text-center">
                                            <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-6">
                                                <p class="text-sm font-medium text-black">Belum ada jadwal</p>
                                                <p class="mt-1 text-sm leading-5 text-[#615d59]">Tambahkan hari, jam, dan ruang untuk kelas ini.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Materi</h2>
                            <p class="text-sm leading-5 text-[#615d59]">Bahan ajar per pertemuan untuk kelas ini.</p>
                        </div>
                        <Link :href="route('admin.kelas-kuliah.materi.create', props.kelasKuliah.id)">
                            <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"><Plus class="mr-1 size-4" />Tambah Materi</Button>
                        </Link>
                    </div>

                    <div
                        v-if="page.props.flash?.materi_success"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                        role="alert"
                    >
                        {{ page.props.flash.materi_success }}
                    </div>
                    <div
                        v-if="page.props.flash?.materi_error"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                        role="alert"
                    >
                        {{ page.props.flash.materi_error }}
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pertemuan</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Judul Materi</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Berkas</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Diunggah Oleh</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr
                                        v-for="materi in props.kelasKuliah.materis ?? []"
                                        :key="materi.id"
                                        class="transition-colors hover:bg-[#f6f5f4]/60"
                                    >
                                        <td class="px-4 py-3 text-[15px] font-medium leading-5 text-black">Pertemuan {{ v(materi.pertemuan_ke) }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            <span class="block font-medium text-black">{{ v(materi.judul_materi) }}</span>
                                            <span
                                                v-if="materi.catatan"
                                                class="mt-0.5 block max-w-md truncate text-sm text-[#615d59]"
                                                :title="String(materi.catatan)"
                                                >{{ materi.catatan }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            <ul v-if="materiFiles(materi).length" class="space-y-1">
                                                <li v-for="path in materiFiles(materi)" :key="path">
                                                    <a
                                                        :href="`/storage/${path}`"
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
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ v(materi.uploader?.name) }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-1.5">
                                                <button
                                                    type="button"
                                                    title="Duplikasi"
                                                    aria-label="Duplikasi"
                                                    @click="openDuplicate('materi', materi)"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Copy class="size-4"
                                                    /></Button>
                                                </button>
                                                <Link
                                                    :href="route('admin.kelas-kuliah.materi.edit', [props.kelasKuliah.id, materi.id])"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Pencil class="size-4"
                                                    /></Button>
                                                </Link>
                                                <button type="button" title="Hapus" aria-label="Hapus" @click="removeMateri(materi)">
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Trash2 class="size-4"
                                                    /></Button>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!(props.kelasKuliah.materis ?? []).length">
                                        <td colspan="5" class="px-4 py-10 text-center">
                                            <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-6">
                                                <p class="text-sm font-medium text-black">Belum ada materi</p>
                                                <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                    Tambahkan judul, pertemuan, berkas, dan catatan untuk kelas ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tugas</h2>
                            <p class="text-sm leading-5 text-[#615d59]">Daftar tugas beserta tenggat waktu untuk kelas ini.</p>
                        </div>
                        <Link :href="route('admin.kelas-kuliah.tugas.create', props.kelasKuliah.id)">
                            <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"><Plus class="mr-1 size-4" />Tambah Tugas</Button>
                        </Link>
                    </div>

                    <div
                        v-if="page.props.flash?.tugas_success"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                        role="alert"
                    >
                        {{ page.props.flash.tugas_success }}
                    </div>
                    <div
                        v-if="page.props.flash?.tugas_error"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                        role="alert"
                    >
                        {{ page.props.flash.tugas_error }}
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Judul Tugas</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tenggat Waktu</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Berkas</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Diunggah Oleh</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr
                                        v-for="tugas in props.kelasKuliah.tugas ?? []"
                                        :key="tugas.id"
                                        class="transition-colors hover:bg-[#f6f5f4]/60"
                                    >
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            <span class="block font-medium text-black">{{ v(tugas.judul_tugas) }}</span>
                                            <span
                                                v-if="tugas.catatan"
                                                class="mt-0.5 block max-w-md truncate text-sm text-[#615d59]"
                                                :title="String(tugas.catatan)"
                                                >{{ tugas.catatan }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ formatTenggat(tugas.tenggat_waktu) }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            <ul v-if="tugasFiles(tugas).length" class="space-y-1">
                                                <li v-for="path in tugasFiles(tugas)" :key="path">
                                                    <a
                                                        :href="`/storage/${path}`"
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
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ v(tugas.uploader?.name) }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-1.5">
                                                <Link
                                                    :href="route('admin.kelas-kuliah.tugas.show', [props.kelasKuliah.id, tugas.id])"
                                                    title="Lihat detail"
                                                    aria-label="Lihat detail"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Eye class="size-4"
                                                    /></Button>
                                                </Link>
                                                <button type="button" title="Duplikasi" aria-label="Duplikasi" @click="openDuplicate('tugas', tugas)">
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Copy class="size-4"
                                                    /></Button>
                                                </button>
                                                <Link
                                                    :href="route('admin.kelas-kuliah.tugas.edit', [props.kelasKuliah.id, tugas.id])"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Pencil class="size-4"
                                                    /></Button>
                                                </Link>
                                                <button type="button" title="Hapus" aria-label="Hapus" @click="removeTugas(tugas)">
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Trash2 class="size-4"
                                                    /></Button>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!(props.kelasKuliah.tugas ?? []).length">
                                        <td colspan="5" class="px-4 py-10 text-center">
                                            <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-6">
                                                <p class="text-sm font-medium text-black">Belum ada tugas</p>
                                                <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                    Tambahkan judul, tenggat waktu, berkas, dan catatan untuk kelas ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Quiz</h2>
                            <p class="text-sm leading-5 text-[#615d59]">Daftar quiz beserta durasi dan tenggat waktu untuk kelas ini.</p>
                        </div>
                        <Link :href="route('admin.kelas-kuliah.quiz.create', props.kelasKuliah.id)">
                            <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"><Plus class="mr-1 size-4" />Tambah Quiz</Button>
                        </Link>
                    </div>

                    <div
                        v-if="page.props.flash?.quiz_success"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                        role="alert"
                    >
                        {{ page.props.flash.quiz_success }}
                    </div>
                    <div
                        v-if="page.props.flash?.quiz_error"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                        role="alert"
                    >
                        {{ page.props.flash.quiz_error }}
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nama Quiz</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Durasi</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tenggat Waktu</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Diunggah Oleh</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr
                                        v-for="quiz in props.kelasKuliah.quizzes ?? []"
                                        :key="quiz.id"
                                        class="transition-colors hover:bg-[#f6f5f4]/60"
                                    >
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            <span class="block font-medium text-black">{{ v(quiz.nama_quiz) }}</span>
                                            <span
                                                v-if="quiz.catatan"
                                                class="mt-0.5 block max-w-md truncate text-sm text-[#615d59]"
                                                :title="String(quiz.catatan)"
                                                >{{ quiz.catatan }}</span
                                            >
                                        </td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                            {{ quiz.waktu_pengerjaan ? `${quiz.waktu_pengerjaan} menit` : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ formatTenggat(quiz.tenggat_waktu) }}</td>
                                        <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">{{ v(quiz.uploader?.name) }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-1.5">
                                                <Link
                                                    :href="route('admin.kelas-kuliah.quiz.show', [props.kelasKuliah.id, quiz.id])"
                                                    title="Detail"
                                                    aria-label="Detail"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Eye class="size-4"
                                                    /></Button>
                                                </Link>
                                                <button type="button" title="Duplikasi" aria-label="Duplikasi" @click="openDuplicate('quiz', quiz)">
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Copy class="size-4"
                                                    /></Button>
                                                </button>
                                                <Link
                                                    :href="route('admin.kelas-kuliah.quiz.edit', [props.kelasKuliah.id, quiz.id])"
                                                    title="Edit"
                                                    aria-label="Edit"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Pencil class="size-4"
                                                    /></Button>
                                                </Link>
                                                <button type="button" title="Hapus" aria-label="Hapus" @click="removeQuiz(quiz)">
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                        aria-hidden="true"
                                                        ><Trash2 class="size-4"
                                                    /></Button>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!(props.kelasKuliah.quizzes ?? []).length">
                                        <td colspan="5" class="px-4 py-10 text-center">
                                            <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-6">
                                                <p class="text-sm font-medium text-black">Belum ada quiz</p>
                                                <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                    Tambahkan nama, durasi, tenggat waktu, dan catatan untuk kelas ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nilai Mahasiswa</h2>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:max-w-sm">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="gradeSearch"
                                placeholder="Cari nama mahasiswa atau NIM"
                                class="h-9 rounded-[4px] border-[#dddddd] bg-white pl-9 text-[15px] placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de]"
                            />
                        </div>
                        <p class="text-sm text-[#615d59]">
                            <span class="font-medium text-black">{{ filteredKrs.length }}</span>
                            mahasiswa
                        </p>
                    </div>
                    <div
                        v-if="page.props.flash?.success"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                        role="alert"
                    >
                        {{ page.props.flash.success }}
                    </div>
                    <div
                        v-if="page.props.flash?.error"
                        class="mt-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                        role="alert"
                    >
                        {{ page.props.flash.error }}
                    </div>
                    <div class="mt-4 overflow-hidden rounded-xl border border-[#e6e6e6]">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-left">
                                <thead>
                                    <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.04em] text-[#a39e98]">No.</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.04em] text-[#a39e98]">Mahasiswa</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.04em] text-[#a39e98]">Program Studi</th>
                                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.04em] text-[#a39e98]">Nilai</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.04em] text-[#a39e98]">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="(krs, index) in filteredKrs" :key="krs.id" class="hover:bg-[#f6f5f4]/60">
                                        <td class="px-4 py-3 text-sm text-[#615d59]">{{ index + 1 }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-black">
                                            {{ krs.mahasiswa?.user?.name ?? '-' }}
                                            <span class="text-[#615d59]">({{ krs.mahasiswa?.nim ?? '-' }})</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-[#31302e]">{{ krs.mahasiswa?.prodi?.nama_prodi ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <select
                                                v-if="editingKrs === krs.id"
                                                v-model="grade"
                                                class="h-9 rounded-lg border border-[#e6e6e6] bg-white px-3 text-sm"
                                            >
                                                <option v-for="option in ['A', 'B', 'C', 'D', 'E']" :key="option" :value="option">
                                                    {{ option }}
                                                </option>
                                            </select>
                                            <span v-else class="font-semibold">{{ krs.nilai ?? '-' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <template v-if="editingKrs === krs.id">
                                                <Button
                                                    size="sm"
                                                    class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                                                    @click="saveGrade(krs)"
                                                    >Simpan</Button
                                                >
                                                <Button size="sm" variant="outline" class="ml-2 rounded-full" @click="editingKrs = null"
                                                    >Batal</Button
                                                >
                                            </template>
                                            <Button v-else size="sm" variant="outline" class="rounded-full" @click="editGrade(krs)"
                                                >Ubah Nilai</Button
                                            >
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
                    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-md flex-col rounded-xl bg-white p-6">
                        <h2 class="text-lg font-semibold">Duplikasi {{ duplicateType }}</h2>
                        <p class="mt-1 text-sm text-[#615d59]">Pilih kelas tujuan.</p>
                        <div class="mt-4 flex max-h-80 flex-col gap-3 overflow-y-auto pr-2">
                            <label v-for="kelas in props.otherClasses" :key="kelas.id" class="flex gap-2 text-sm">
                                <input v-model="duplicateTargets" type="checkbox" :value="kelas.id" />
                                {{ kelas.kode_kelas }} — {{ kelas.mataKuliah?.nama_matkul ?? '-' }}
                            </label>
                        </div>
                        <div class="mt-5 flex justify-end gap-2">
                            <Button variant="outline" @click="duplicateOpen = false">Batal</Button>
                            <Button :disabled="!duplicateTargets.length" @click="duplicate">Duplikasi</Button>
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
