<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    JENIS_PERTEMUAN,
    STATUS_PRESENSI,
    formatTanggal,
    infoStatusPresensi,
    jam,
    statusTampil,
    type JenisPertemuan,
    type StatusPertemuan,
} from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarPlus, Download, FileText, Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Pertemuan = {
    terlewat?: boolean;
    id: number;
    pertemuan_ke: number;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang_id: number | null;
    jenis: JenisPertemuan;
    status: StatusPertemuan;
    dosen_id: number | null;
    topik: string | null;
    catatan: string | null;
    riwayat_jadwal_count: number;
    alasan_jadwal_terakhir: string | null;
    jumlah_tercatat: number;
    jumlah_hadir: number;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    dosen?: { user?: { name: string } | null } | null;
};
type Rekap = { hadir: number; terlambat: number; izin: number; sakit: number; alpa: number; dihitung: number; persen: number | null };
type SyaratJenis = {
    hadir: number;
    dihitung: number;
    persen: number | null;
    memenuhi: boolean | null;
    dispensasi: { id: number; alasan: string; oleh: string | null } | null;
} | null;
type Peserta = {
    mahasiswa_id: number;
    nim: string;
    nama: string;
    presensi: Record<string, string>;
    rekap: Rekap | null;
    ujian: { uts: SyaratJenis; uas: SyaratJenis } | null;
};
type JadwalUjian = {
    id: number;
    pertemuan_ke: number;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang: string | null;
    status: StatusPertemuan;
} | null;

const props = defineProps<{
    peran: Peran;
    kelasKuliah: {
        id: number;
        kode_kelas: string;
        jumlah_pertemuan: number;
        dosen_id: number;
        mata_kuliah?: { kode_matkul: string; nama_matkul: string; sks: number } | null;
        dosen?: { user?: { name: string } | null } | null;
        tahun_akademik?: { tahun: string; semester: string } | null;
        jadwals?: { id: number; hari: string; jam_mulai: string; jam_akhir: string }[];
    };
    pertemuan: Pertemuan[];
    peserta: Peserta[];
    ujian: { aktif: boolean; uts: JadwalUjian; uas: JadwalUjian };
    bisaKelola: boolean;
    bisaDispensasi: boolean;
    minKehadiran: number;
    terkunci: boolean;
    ruangs: { id: number; name: string }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string }; errors: Record<string, string> }>();
const rute = rutePeran(props.peran);
const isAdmin = computed(() => props.peran === 'admin');
const tab = ref<'pertemuan' | 'rekap' | 'ujian'>('pertemuan');
// Kaprodi yang bukan pengampu hanya melihat; tombol pengelolaan disembunyikan.
const bisaUbah = computed(() => props.bisaKelola && !props.terkunci);

// Dispensasi UTS/UAS (admin atau kaprodi).
const dispensasiUntuk = ref<Peserta | null>(null);
const dispensasiForm = useForm({ mahasiswa_id: 0, jenis: [] as string[], alasan: '' });
const bukaDispensasi = (mhs: Peserta) => {
    dispensasiUntuk.value = mhs;
    dispensasiForm.reset();
    dispensasiForm.clearErrors();
    dispensasiForm.mahasiswa_id = mhs.mahasiswa_id;
    dispensasiForm.jenis = (['uts', 'uas'] as const).filter((j) => props.ujian[j] && mhs.ujian?.[j]?.memenuhi === false);
};
const simpanDispensasi = () =>
    dispensasiForm.post(rute('presensi.dispensasi.simpan', props.kelasKuliah.id), {
        preserveScroll: true,
        onSuccess: () => (dispensasiUntuk.value = null),
    });
const cabutDispensasi = (id: number) => router.delete(rute('presensi.dispensasi.hapus', id), { preserveScroll: true });
const jumlahTidakMemenuhi = computed(() => props.peserta.filter((m) => m.ujian?.uts?.memenuhi === false || m.ujian?.uas?.memenuhi === false).length);
const kurang = computed(() => props.kelasKuliah.jumlah_pertemuan - props.pertemuan.length);

const generating = ref(false);
const generate = () =>
    router.post(
        rute('presensi.generate', props.kelasKuliah.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (generating.value = true),
            onFinish: () => (generating.value = false),
        },
    );

const jumlahForm = useForm({ jumlah_pertemuan: props.kelasKuliah.jumlah_pertemuan });
const simpanJumlah = () => jumlahForm.put(route('admin.presensi.jumlah', props.kelasKuliah.id), { preserveScroll: true });

// Ubah jadwal/jenis satu pertemuan.
const sunting = ref<Pertemuan | null>(null);
const suntingForm = useForm({
    tanggal: '',
    jam_mulai: '',
    jam_akhir: '',
    ruang_id: '' as number | string,
    jenis: 'kuliah' as JenisPertemuan,
    catatan: '',
    alasan: '',
});
const bukaSunting = (item: Pertemuan) => {
    sunting.value = item;
    suntingForm.clearErrors();
    Object.assign(suntingForm, {
        tanggal: item.tanggal.slice(0, 10),
        jam_mulai: jam(item.jam_mulai),
        jam_akhir: jam(item.jam_akhir),
        ruang_id: item.ruang_id ?? '',
        jenis: item.jenis,
        catatan: item.catatan ?? '',
        alasan: '',
    });
};
const simpanSunting = () => {
    if (!sunting.value) return;
    suntingForm
        .transform((data) => ({ ...data, ruang_id: data.ruang_id || null }))
        .put(rute('presensi.pertemuan.update', sunting.value.id), { preserveScroll: true, onSuccess: () => (sunting.value = null) });
};
const jadwalTerkunci = computed(() => sunting.value !== null && sunting.value.status !== 'dijadwalkan');
// Alasan wajib bila tanggal, jam, atau ruang berubah; perubahannya dicatat di riwayat pertemuan.
const jadwalBerubah = computed(
    () =>
        sunting.value !== null &&
        (suntingForm.tanggal !== sunting.value.tanggal.slice(0, 10) ||
            suntingForm.jam_mulai !== jam(sunting.value.jam_mulai) ||
            suntingForm.jam_akhir !== jam(sunting.value.jam_akhir) ||
            (suntingForm.ruang_id || null) !== sunting.value.ruang_id),
);

const pertemuanDihitung = computed(() => props.pertemuan.filter((item) => item.jenis === 'kuliah' && item.status === 'selesai').length);
const dibawahBatas = (rekap: Rekap | null) => rekap?.persen !== null && rekap?.persen !== undefined && rekap.persen < props.minKehadiran;
const jumlahBerisiko = computed(() => props.peserta.filter((item) => dibawahBatas(item.rekap)).length);
</script>

<template>
    <Head :title="`Presensi ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            { title: props.peran === 'admin' ? 'Presensi Mahasiswa' : 'Presensi', href: rute('presensi.index') },
            { title: props.kelasKuliah.kode_kelas, href: rute('presensi.kelas', props.kelasKuliah.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.kelasKuliah.mata_kuliah?.nama_matkul ?? '-' }}</h1>
                        <p class="deskripsi-halaman">
                            {{ props.kelasKuliah.kode_kelas }} · {{ props.kelasKuliah.tahun_akademik?.tahun }}
                            {{ props.kelasKuliah.tahun_akademik?.semester }} · {{ props.kelasKuliah.dosen?.user?.name ?? '-' }}
                        </p>
                        <p class="deskripsi-halaman">
                            Jadwal:
                            <template v-if="props.kelasKuliah.jadwals?.length">
                                <span v-for="(j, i) in props.kelasKuliah.jadwals" :key="j.id"
                                    >{{ i ? ', ' : '' }}{{ j.hari }} {{ jam(j.jam_mulai) }}–{{ jam(j.jam_akhir) }}</span
                                >
                            </template>
                            <span v-else class="text-[#dd5b00]">belum ada</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="props.bisaKelola" as-child variant="outline">
                            <Link :href="rute('kelas-kuliah.show', props.kelasKuliah.id)">Buka kelas</Link>
                        </Button>
                        <Button as-child variant="outline">
                            <a :href="rute('presensi.ekspor', props.kelasKuliah.id)"><Download /> PDF</a>
                        </Button>
                        <Button as-child variant="outline">
                            <a :href="rute('presensi.ekspor', { kelasKuliah: props.kelasKuliah.id, format: 'csv' })"><Download /> CSV</a>
                        </Button>
                        <Button as-child variant="outline" title="Berita Acara Perkuliahan: dosen hanya memuat pertemuan yang sudah diverifikasi">
                            <a :href="rute('presensi.bap', props.kelasKuliah.id)" target="_blank" rel="noopener"><FileText /> BAP</a>
                        </Button>
                        <Button v-if="kurang > 0 && bisaUbah" :disabled="generating" @click="generate">
                            <CalendarPlus /> Buat {{ kurang }} pertemuan
                        </Button>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error || page.props.errors?.pertemuan" class="alert-gagal" role="alert">
                    {{ page.props.flash?.error ?? page.props.errors.pertemuan }}
                </div>
                <div v-if="props.terkunci" class="alert-info">Tahun akademik kelas ini sudah tidak aktif. Presensi hanya bisa diubah admin.</div>
                <div v-else-if="!props.bisaKelola" class="alert-info">
                    Anda melihat kelas ini sebagai kaprodi. Presensi dan pertemuan hanya bisa diubah dosen pengampu; dispensasi ujian ada di tab
                    Peserta Ujian.
                </div>

                <form v-if="isAdmin" class="kartu flex flex-wrap items-end gap-3 p-6" @submit.prevent="simpanJumlah">
                    <div class="grid gap-2">
                        <Label for="jumlah_pertemuan" class="label-isian">Jumlah pertemuan kelas ini</Label>
                        <Input id="jumlah_pertemuan" v-model="jumlahForm.jumlah_pertemuan" type="number" min="1" max="32" class="w-28" />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="jumlahForm.processing || jumlahForm.jumlah_pertemuan === props.kelasKuliah.jumlah_pertemuan"
                        >Simpan</Button
                    >
                    <p class="teks-bantu">Termasuk UTS dan UAS. Mengurangi jumlah menghapus pertemuan terakhir yang belum berjalan.</p>
                    <InputError class="w-full" :message="jumlahForm.errors.jumlah_pertemuan" />
                </form>

                <div class="flex gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium dark:border-border dark:bg-card sm:w-fit">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="
                            tab === 'pertemuan'
                                ? 'bg-[#0075de] text-white'
                                : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                        "
                        @click="tab = 'pertemuan'"
                    >
                        Pertemuan ({{ props.pertemuan.length }}/{{ props.kelasKuliah.jumlah_pertemuan }})
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="
                            tab === 'rekap'
                                ? 'bg-[#0075de] text-white'
                                : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                        "
                        @click="tab = 'rekap'"
                    >
                        Rekap Kehadiran
                        <span v-if="jumlahBerisiko" class="ml-1 rounded-full bg-[#fdecea] px-1.5 text-xs text-[#b42318]">{{ jumlahBerisiko }}</span>
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="
                            tab === 'ujian'
                                ? 'bg-[#0075de] text-white'
                                : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                        "
                        @click="tab = 'ujian'"
                    >
                        Peserta Ujian
                        <span v-if="jumlahTidakMemenuhi" class="ml-1 rounded-full bg-[#fdecea] px-1.5 text-xs text-[#b42318]">{{
                            jumlahTidakMemenuhi
                        }}</span>
                    </button>
                </div>

                <div v-if="tab === 'pertemuan'" class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[960px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Ke</th>
                                    <th>Tanggal &amp; Jam</th>
                                    <th>Ruang</th>
                                    <th>Status</th>
                                    <th>Topik</th>
                                    <th>Hadir</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.pertemuan" :key="item.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td class="font-medium text-black dark:text-foreground">
                                        {{ item.pertemuan_ke }}
                                        <span
                                            v-if="item.jenis !== 'kuliah'"
                                            class="ml-1 rounded bg-[#fff6e0] px-1.5 py-0.5 text-xs font-semibold text-[#8a5a00]"
                                            >{{ JENIS_PERTEMUAN[item.jenis] }}</span
                                        >
                                    </td>
                                    <td>
                                        <span class="block">{{ formatTanggal(item.tanggal) }}</span>
                                        <span class="teks-bantu block">{{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }}</span>
                                    </td>
                                    <td>{{ item.ruang?.kode_ruang ?? '-' }}</td>
                                    <td>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusTampil(item).kelas">
                                            {{ statusTampil(item).label }}
                                        </span>
                                        <span
                                            v-if="item.riwayat_jadwal_count"
                                            class="mt-1 block max-w-[180px] truncate text-xs text-[#8a5a00]"
                                            :title="item.alasan_jadwal_terakhir ?? ''"
                                        >
                                            Dijadwal ulang: {{ item.alasan_jadwal_terakhir }}
                                        </span>
                                        <span v-if="item.catatan" class="teks-bantu mt-1 block max-w-[180px] truncate" :title="item.catatan">
                                            {{ item.catatan }}
                                        </span>
                                    </td>
                                    <td class="max-w-[220px]">
                                        <span class="line-clamp-2" :title="item.topik ?? ''">{{ item.topik ?? '-' }}</span>
                                        <span v-if="item.dosen_id && item.dosen_id !== props.kelasKuliah.dosen_id" class="teks-bantu block"
                                            >Pengganti: {{ item.dosen?.user?.name }}</span
                                        >
                                    </td>
                                    <td class="tabular-nums">
                                        {{ item.jumlah_tercatat ? `${item.jumlah_hadir}/${item.jumlah_tercatat}` : '-' }}
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="sm">
                                                <Link :href="rute('presensi.pertemuan.show', item.id)">
                                                    {{ item.status === 'berlangsung' ? 'Isi presensi' : 'Buka' }}
                                                </Link>
                                            </Button>
                                            <Button
                                                v-if="bisaUbah"
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#2a9d99]"
                                                title="Ubah"
                                                :aria-label="`Ubah pertemuan ${item.pertemuan_ke}`"
                                                @click="bukaSunting(item)"
                                                ><Pencil
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pertemuan.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">
                                        Pertemuan belum dibuat. Tekan "Buat pertemuan" untuk menyusunnya dari jadwal mingguan kelas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="tab === 'ujian'" class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="j in ['uts', 'uas'] as const" :key="j" class="kartu p-6">
                            <h2 class="judul-bagian">Jadwal {{ JENIS_PERTEMUAN[j] }}</h2>
                            <template v-if="props.ujian[j]">
                                <p class="mt-2 text-sm font-medium text-black dark:text-foreground">{{ formatTanggal(props.ujian[j]!.tanggal) }}</p>
                                <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                                    {{ jam(props.ujian[j]!.jam_mulai) }}–{{ jam(props.ujian[j]!.jam_akhir) }} · Ruang
                                    {{ props.ujian[j]!.ruang ?? '-' }} · pertemuan ke-{{ props.ujian[j]!.pertemuan_ke }}
                                </p>
                                <Button as-child variant="outline" size="sm" class="mt-3">
                                    <a :href="rute('presensi.peserta-ujian', { kelasKuliah: props.kelasKuliah.id, jenis: j })">
                                        <Download /> Daftar hadir {{ JENIS_PERTEMUAN[j] }} (PDF)
                                    </a>
                                </Button>
                            </template>
                            <p v-else class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                                Belum ada pertemuan berjenis {{ JENIS_PERTEMUAN[j] }}. Ubah jenis salah satu pertemuan.
                            </p>
                        </div>
                    </div>
                    <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                        <template v-if="props.ujian.aktif">
                            Syarat kehadiran {{ props.minKehadiran }}% diberlakukan. UTS dihitung dari pertemuan kuliah sebelum UTS, UAS dari semua
                            pertemuan kuliah; angka masih sementara sampai pertemuan terakhir selesai.
                        </template>
                        <template v-else>
                            Syarat kehadiran ujian belum diberlakukan (Pengaturan Akademik), jadi semua mahasiswa boleh ikut ujian. Persentase tetap
                            ditampilkan sebagai informasi.
                        </template>
                    </p>
                    <div class="tabel-wadah">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[820px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <th>UTS</th>
                                        <th>UAS</th>
                                        <th v-if="props.bisaDispensasi" class="kolom-aksi">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(mhs, index) in props.peserta" :key="mhs.mahasiswa_id">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <span class="block font-medium text-black dark:text-foreground">{{ mhs.nama }}</span>
                                            <span class="teks-bantu block">{{ mhs.nim }}</span>
                                        </td>
                                        <td v-for="j in ['uts', 'uas'] as const" :key="j">
                                            <template v-if="mhs.ujian?.[j]">
                                                <span class="font-medium"
                                                    >{{ mhs.ujian[j]!.persen ?? '-' }}{{ mhs.ujian[j]!.persen !== null ? '%' : '' }}</span
                                                >
                                                <span class="teks-bantu"> ({{ mhs.ujian[j]!.hadir }}/{{ mhs.ujian[j]!.dihitung }})</span>
                                                <span
                                                    v-if="mhs.ujian[j]!.dispensasi"
                                                    class="ml-1 inline-flex items-center gap-1 rounded bg-[#eaf3fd] px-1.5 py-0.5 text-xs text-[#0b62b5]"
                                                    :title="`${mhs.ujian[j]!.dispensasi!.alasan} — ${mhs.ujian[j]!.dispensasi!.oleh ?? ''}`"
                                                >
                                                    Dispensasi
                                                    <button
                                                        v-if="props.bisaDispensasi"
                                                        type="button"
                                                        class="font-bold"
                                                        :aria-label="`Cabut dispensasi ${JENIS_PERTEMUAN[j]} ${mhs.nama}`"
                                                        @click="cabutDispensasi(mhs.ujian[j]!.dispensasi!.id)"
                                                    >
                                                        ×
                                                    </button>
                                                </span>
                                                <span
                                                    v-else-if="mhs.ujian[j]!.memenuhi === false"
                                                    class="ml-1 rounded bg-[#fdecea] px-1.5 py-0.5 text-xs text-[#b42318]"
                                                    >Tidak memenuhi</span
                                                >
                                                <span
                                                    v-else-if="mhs.ujian[j]!.memenuhi"
                                                    class="ml-1 rounded bg-[#e8f7ec] px-1.5 py-0.5 text-xs text-[#1a7f37]"
                                                    >Memenuhi</span
                                                >
                                            </template>
                                            <span v-else class="text-[#a39e98]">-</span>
                                        </td>
                                        <td v-if="props.bisaDispensasi" class="kolom-aksi">
                                            <div class="aksi-tabel">
                                                <Button variant="outline" size="sm" @click="bukaDispensasi(mhs)">Dispensasi</Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.peserta.length" class="baris-kosong">
                                        <td :colspan="props.bisaDispensasi ? 5 : 4" class="tabel-kosong">Belum ada mahasiswa di kelas ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col gap-4">
                    <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                        Dihitung dari {{ pertemuanDihitung }} pertemuan kuliah yang sudah selesai. Izin dan sakit dihitung tidak hadir; batas minimal
                        {{ props.minKehadiran }}%.
                    </p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span v-for="s in STATUS_PRESENSI" :key="s.value" class="rounded border px-1.5 py-0.5" :class="s.kelas"
                            >{{ s.singkat }} = {{ s.label }}</span
                        >
                    </div>
                    <div class="tabel-wadah">
                        <div class="tabel-gulir">
                            <!-- Kolom No ikut bergulir; kolom Mahasiswa tetap menempel di kiri saat matriks digulir. -->
                            <table class="tabel">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th class="sticky left-0 z-10 min-w-[200px]">Mahasiswa</th>
                                        <th
                                            v-for="item in props.pertemuan"
                                            :key="item.id"
                                            class="px-1.5 text-center"
                                            :title="`${formatTanggal(item.tanggal)} — ${statusTampil(item).label}`"
                                        >
                                            {{ item.jenis === 'kuliah' ? item.pertemuan_ke : JENIS_PERTEMUAN[item.jenis] }}
                                        </th>
                                        <th class="text-right">Kehadiran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(mhs, index) in props.peserta"
                                        :key="mhs.mahasiswa_id"
                                        :class="dibawahBatas(mhs.rekap) ? 'bg-[#fff8f7] dark:bg-orange-950/20' : ''"
                                    >
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td
                                            class="sticky left-0 z-10"
                                            :class="dibawahBatas(mhs.rekap) ? 'bg-[#fff8f7] dark:bg-card' : 'bg-white dark:bg-card'"
                                        >
                                            <span class="block font-medium text-black dark:text-foreground">{{ mhs.nama }}</span>
                                            <span class="teks-bantu block">{{ mhs.nim }}</span>
                                        </td>
                                        <td v-for="item in props.pertemuan" :key="item.id" class="px-1.5 text-center">
                                            <span
                                                v-if="mhs.presensi[item.id]"
                                                class="inline-flex size-6 items-center justify-center rounded border text-xs font-semibold"
                                                :class="infoStatusPresensi(mhs.presensi[item.id])?.kelas"
                                                :title="infoStatusPresensi(mhs.presensi[item.id])?.label"
                                                >{{ infoStatusPresensi(mhs.presensi[item.id])?.singkat }}</span
                                            >
                                            <span v-else class="text-xs text-[#d0ccc7]">·</span>
                                        </td>
                                        <td class="whitespace-nowrap text-right">
                                            <span
                                                class="font-semibold"
                                                :class="dibawahBatas(mhs.rekap) ? 'text-[#b42318]' : 'text-black dark:text-foreground'"
                                                >{{ mhs.rekap?.persen ?? '-' }}{{ mhs.rekap?.persen !== null && mhs.rekap ? '%' : '' }}</span
                                            >
                                            <span v-if="mhs.rekap" class="teks-bantu block">
                                                {{ mhs.rekap.hadir + mhs.rekap.terlambat }}/{{ mhs.rekap.dihitung }} hadir
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.peserta.length" class="baris-kosong">
                                        <td :colspan="props.pertemuan.length + 3" class="tabel-kosong">Belum ada mahasiswa di kelas ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Dispensasi ujian -->
                <div
                    v-if="dispensasiUntuk"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
                    @click.self="dispensasiUntuk = null"
                >
                    <form class="kartu flex w-full max-w-md flex-col gap-4 p-6" @submit.prevent="simpanDispensasi">
                        <div>
                            <h2 class="judul-bagian">Dispensasi ujian — {{ dispensasiUntuk.nama }}</h2>
                            <p class="teks-bantu mt-1">
                                Mahasiswa tetap boleh mengikuti ujian walau kehadirannya di bawah batas. Angka kehadiran tidak berubah.
                            </p>
                        </div>
                        <div class="flex gap-4">
                            <label v-for="j in ['uts', 'uas'] as const" :key="j" class="label-isian flex items-center gap-2">
                                <input v-model="dispensasiForm.jenis" type="checkbox" :value="j" class="size-4 accent-[#0075de]" />
                                {{ JENIS_PERTEMUAN[j] }}
                            </label>
                        </div>
                        <InputError :message="dispensasiForm.errors.jenis" />
                        <div class="grid gap-2">
                            <Label for="alasan_dispensasi" class="label-isian">Alasan</Label>
                            <Input
                                id="alasan_dispensasi"
                                v-model="dispensasiForm.alasan"
                                maxlength="255"
                                placeholder="mis. Rawat inap, surat RS terlampir"
                                required
                            />
                            <InputError :message="dispensasiForm.errors.alasan ?? dispensasiForm.errors.mahasiswa_id" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="dispensasiUntuk = null">Batal</Button>
                            <Button type="submit" :disabled="dispensasiForm.processing || !dispensasiForm.jenis.length">Beri dispensasi</Button>
                        </div>
                    </form>
                </div>

                <!-- Ubah pertemuan -->
                <div v-if="sunting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="sunting = null">
                    <form
                        class="kartu flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col gap-4 overflow-y-auto p-6"
                        @submit.prevent="simpanSunting"
                    >
                        <div>
                            <h2 class="judul-bagian">Ubah pertemuan ke-{{ sunting.pertemuan_ke }}</h2>
                            <p v-if="jadwalTerkunci" class="teks-bantu mt-1">
                                Pertemuan sudah dimulai, jadi tanggal, jam, dan ruang tidak bisa diubah lagi.
                            </p>
                        </div>
                        <div class="grid content-start items-start gap-4 sm:grid-cols-3">
                            <div class="grid content-start gap-2 sm:col-span-3">
                                <Label for="tanggal" class="label-isian">Tanggal</Label>
                                <Input id="tanggal" v-model="suntingForm.tanggal" type="date" :disabled="jadwalTerkunci" required />
                                <InputError :message="suntingForm.errors.tanggal" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label for="jam_mulai" class="label-isian">Jam mulai</Label>
                                <Input id="jam_mulai" v-model="suntingForm.jam_mulai" type="time" :disabled="jadwalTerkunci" required />
                                <InputError :message="suntingForm.errors.jam_mulai" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label for="jam_akhir" class="label-isian">Jam akhir</Label>
                                <Input id="jam_akhir" v-model="suntingForm.jam_akhir" type="time" :disabled="jadwalTerkunci" required />
                                <InputError :message="suntingForm.errors.jam_akhir" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label for="jenis" class="label-isian">Jenis</Label>
                                <select id="jenis" v-model="suntingForm.jenis" class="isian isian-pilih">
                                    <option v-for="(label, value) in JENIS_PERTEMUAN" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <InputError :message="suntingForm.errors.jenis" />
                            </div>
                            <div class="grid content-start gap-2 sm:col-span-3">
                                <Label for="ruang_id" class="label-isian">Ruang</Label>
                                <select id="ruang_id" v-model="suntingForm.ruang_id" class="isian isian-pilih" :disabled="jadwalTerkunci">
                                    <option value="">Tanpa ruang (daring)</option>
                                    <option v-for="r in props.ruangs" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                                <InputError :message="suntingForm.errors.ruang_id" />
                            </div>
                            <div class="grid content-start gap-2 sm:col-span-3">
                                <Label for="catatan" class="label-isian">Catatan</Label>
                                <Input id="catatan" v-model="suntingForm.catatan" maxlength="255" placeholder="Catatan umum pertemuan (opsional)" />
                                <InputError :message="suntingForm.errors.catatan" />
                            </div>
                            <div v-if="jadwalBerubah" class="grid content-start gap-2 sm:col-span-3">
                                <Label for="alasan" class="label-isian">Alasan perubahan jadwal</Label>
                                <Input
                                    id="alasan"
                                    v-model="suntingForm.alasan"
                                    maxlength="255"
                                    placeholder="mis. Libur nasional, diganti Rabu"
                                    required
                                />
                                <p class="teks-bantu">Hanya pertemuan ini yang berubah. Perubahan dan alasannya tercatat di riwayat.</p>
                                <InputError :message="suntingForm.errors.alasan" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="sunting = null">Batal</Button>
                            <Button type="submit" :disabled="suntingForm.processing">Simpan</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
