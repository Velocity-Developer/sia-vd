<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    JENIS_PERTEMUAN,
    STATUS_PERTEMUAN,
    STATUS_PRESENSI,
    formatTanggal,
    infoStatusPresensi,
    jam,
    type JenisPertemuan,
    type StatusPertemuan,
} from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarPlus, Download, Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Pertemuan = {
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
    });
};
const simpanSunting = () => {
    if (!sunting.value) return;
    suntingForm
        .transform((data) => ({ ...data, ruang_id: data.ruang_id || null }))
        .put(rute('presensi.pertemuan.update', sunting.value.id), { preserveScroll: true, onSuccess: () => (sunting.value = null) });
};
const jadwalTerkunci = computed(() => sunting.value !== null && sunting.value.status !== 'dijadwalkan');

// Batalkan pertemuan (mis. libur) dengan alasan.
const pembatalan = ref<Pertemuan | null>(null);
const batalForm = useForm({ catatan: '' });
const bukaBatal = (item: Pertemuan) => {
    pembatalan.value = item;
    batalForm.reset();
    batalForm.clearErrors();
};
const simpanBatal = () => {
    if (!pembatalan.value) return;
    batalForm.put(rute('presensi.pertemuan.batal', pembatalan.value.id), { preserveScroll: true, onSuccess: () => (pembatalan.value = null) });
};
const aktifkan = (item: Pertemuan) => router.put(rute('presensi.pertemuan.aktifkan', item.id), {}, { preserveScroll: true });

const pertemuanDihitung = computed(() => props.pertemuan.filter((item) => item.jenis === 'kuliah' && item.status === 'selesai').length);
const dibawahBatas = (rekap: Rekap | null) => rekap?.persen !== null && rekap?.persen !== undefined && rekap.persen < props.minKehadiran;
const jumlahBerisiko = computed(() => props.peserta.filter((item) => dibawahBatas(item.rekap)).length);

const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
const sel = 'h-10 w-full rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] disabled:bg-[#f6f5f4]';
</script>

<template>
    <Head :title="`Presensi ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi', href: rute('presensi.index') },
            { title: props.kelasKuliah.kode_kelas, href: rute('presensi.kelas', props.kelasKuliah.id) },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">{{ props.kelasKuliah.mata_kuliah?.nama_matkul ?? '-' }}</h1>
                        <p class="text-sm text-[#615d59]">
                            {{ props.kelasKuliah.kode_kelas }} · {{ props.kelasKuliah.tahun_akademik?.tahun }}
                            {{ props.kelasKuliah.tahun_akademik?.semester }} · {{ props.kelasKuliah.dosen?.user?.name ?? '-' }}
                        </p>
                        <p class="text-sm text-[#615d59]">
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
                        <Link v-if="props.bisaKelola" :href="rute('kelas-kuliah.show', props.kelasKuliah.id)">
                            <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black">Buka kelas</Button>
                        </Link>
                        <a :href="rute('presensi.ekspor', props.kelasKuliah.id)">
                            <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black"
                                ><Download class="mr-1 size-4" /> PDF</Button
                            >
                        </a>
                        <a :href="rute('presensi.ekspor', { kelasKuliah: props.kelasKuliah.id, format: 'csv' })">
                            <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black"
                                ><Download class="mr-1 size-4" /> CSV</Button
                            >
                        </a>
                        <Button
                            v-if="kurang > 0 && bisaUbah"
                            class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                            :disabled="generating"
                            @click="generate"
                        >
                            <CalendarPlus class="mr-1 size-4" /> Buat {{ kurang }} pertemuan
                        </Button>
                    </div>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error || page.props.errors?.pertemuan"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                    role="alert"
                >
                    {{ page.props.flash?.error ?? page.props.errors.pertemuan }}
                </div>
                <div v-if="props.terkunci" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#615d59]">
                    Tahun akademik kelas ini sudah tidak aktif. Presensi hanya bisa diubah admin.
                </div>
                <div v-else-if="!props.bisaKelola" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#615d59]">
                    Anda melihat kelas ini sebagai kaprodi. Presensi dan pertemuan hanya bisa diubah dosen pengampu; dispensasi ujian ada di tab
                    Peserta Ujian.
                </div>

                <form
                    v-if="isAdmin"
                    class="flex flex-wrap items-end gap-3 rounded-xl border border-[#e6e6e6] bg-white p-4 shadow-sm"
                    @submit.prevent="simpanJumlah"
                >
                    <div class="grid gap-1.5">
                        <Label for="jumlah_pertemuan" class="text-sm">Jumlah pertemuan kelas ini</Label>
                        <Input id="jumlah_pertemuan" v-model="jumlahForm.jumlah_pertemuan" type="number" min="1" max="32" :class="[inp, 'w-28']" />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="jumlahForm.processing || jumlahForm.jumlah_pertemuan === props.kelasKuliah.jumlah_pertemuan"
                        >Simpan</Button
                    >
                    <p class="text-xs text-[#a39e98]">Termasuk UTS dan UAS. Mengurangi jumlah menghapus pertemuan terakhir yang belum berjalan.</p>
                    <InputError class="w-full" :message="jumlahForm.errors.jumlah_pertemuan" />
                </form>

                <div class="flex gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium sm:w-fit">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="tab === 'pertemuan' ? 'bg-[#0075de] text-white' : 'text-[#615d59] hover:bg-[#f6f5f4]'"
                        @click="tab = 'pertemuan'"
                    >
                        Pertemuan ({{ props.pertemuan.length }}/{{ props.kelasKuliah.jumlah_pertemuan }})
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="tab === 'rekap' ? 'bg-[#0075de] text-white' : 'text-[#615d59] hover:bg-[#f6f5f4]'"
                        @click="tab = 'rekap'"
                    >
                        Rekap Kehadiran
                        <span v-if="jumlahBerisiko" class="ml-1 rounded-full bg-[#fdecea] px-1.5 text-xs text-[#b42318]">{{ jumlahBerisiko }}</span>
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="tab === 'ujian' ? 'bg-[#0075de] text-white' : 'text-[#615d59] hover:bg-[#f6f5f4]'"
                        @click="tab = 'ujian'"
                    >
                        Peserta Ujian
                        <span v-if="jumlahTidakMemenuhi" class="ml-1 rounded-full bg-[#fdecea] px-1.5 text-xs text-[#b42318]">{{
                            jumlahTidakMemenuhi
                        }}</span>
                    </button>
                </div>

                <div v-if="tab === 'pertemuan'" class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th :class="th">Ke</th>
                                    <th :class="th">Tanggal &amp; Jam</th>
                                    <th :class="th">Ruang</th>
                                    <th :class="th">Status</th>
                                    <th :class="th">Topik</th>
                                    <th :class="th">Hadir</th>
                                    <th :class="[th, 'text-right']">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="item in props.pertemuan" :key="item.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] font-medium text-black">
                                        {{ item.pertemuan_ke }}
                                        <span
                                            v-if="item.jenis !== 'kuliah'"
                                            class="ml-1 rounded bg-[#fff6e0] px-1.5 py-0.5 text-xs font-semibold text-[#8a5a00]"
                                            >{{ JENIS_PERTEMUAN[item.jenis] }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ formatTanggal(item.tanggal) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ item.ruang?.kode_ruang ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_PERTEMUAN[item.status].kelas">
                                            {{ STATUS_PERTEMUAN[item.status].label }}
                                        </span>
                                        <span
                                            v-if="item.catatan"
                                            class="mt-1 block max-w-[180px] truncate text-xs text-[#a39e98]"
                                            :title="item.catatan"
                                        >
                                            {{ item.catatan }}
                                        </span>
                                    </td>
                                    <td class="max-w-[220px] px-4 py-3 text-sm text-[#31302e]">
                                        <span class="line-clamp-2" :title="item.topik ?? ''">{{ item.topik ?? '-' }}</span>
                                        <span
                                            v-if="item.dosen_id && item.dosen_id !== props.kelasKuliah.dosen_id"
                                            class="block text-xs text-[#a39e98]"
                                            >Pengganti: {{ item.dosen?.user?.name }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        {{ item.jumlah_tercatat ? `${item.jumlah_hadir}/${item.jumlah_tercatat}` : '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3 text-sm font-medium">
                                            <Link :href="rute('presensi.pertemuan.show', item.id)" class="text-[#0075de] hover:underline">
                                                {{ item.status === 'berlangsung' ? 'Isi presensi' : 'Buka' }}
                                            </Link>
                                            <template v-if="bisaUbah">
                                                <button
                                                    type="button"
                                                    class="text-[#2a9d99] hover:underline"
                                                    :aria-label="`Ubah pertemuan ${item.pertemuan_ke}`"
                                                    @click="bukaSunting(item)"
                                                >
                                                    <Pencil class="size-4" />
                                                </button>
                                                <button
                                                    v-if="item.status === 'dijadwalkan'"
                                                    type="button"
                                                    class="text-[#dd5b00] hover:underline"
                                                    @click="bukaBatal(item)"
                                                >
                                                    Batalkan
                                                </button>
                                                <button
                                                    v-if="item.status === 'dibatalkan'"
                                                    type="button"
                                                    class="text-[#0075de] hover:underline"
                                                    @click="aktifkan(item)"
                                                >
                                                    Jadwalkan lagi
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pertemuan.length">
                                    <td colspan="7" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Pertemuan belum dibuat. Tekan "Buat pertemuan" untuk menyusunnya dari jadwal mingguan kelas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="tab === 'ujian'" class="flex flex-col gap-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="j in ['uts', 'uas'] as const" :key="j" class="rounded-xl border border-[#e6e6e6] bg-white p-4 shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jadwal {{ JENIS_PERTEMUAN[j] }}</p>
                            <template v-if="props.ujian[j]">
                                <p class="mt-1 font-medium text-black">{{ formatTanggal(props.ujian[j]!.tanggal) }}</p>
                                <p class="text-sm text-[#615d59]">
                                    {{ jam(props.ujian[j]!.jam_mulai) }}–{{ jam(props.ujian[j]!.jam_akhir) }} · Ruang
                                    {{ props.ujian[j]!.ruang ?? '-' }} · pertemuan ke-{{ props.ujian[j]!.pertemuan_ke }}
                                </p>
                                <a
                                    :href="rute('presensi.peserta-ujian', { kelasKuliah: props.kelasKuliah.id, jenis: j })"
                                    class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-[#0075de] hover:underline"
                                >
                                    <Download class="size-4" /> Daftar hadir {{ JENIS_PERTEMUAN[j] }} (PDF)
                                </a>
                            </template>
                            <p v-else class="mt-1 text-sm text-[#615d59]">
                                Belum ada pertemuan berjenis {{ JENIS_PERTEMUAN[j] }}. Ubah jenis salah satu pertemuan.
                            </p>
                        </div>
                    </div>
                    <p class="text-sm text-[#615d59]">
                        <template v-if="props.ujian.aktif">
                            Syarat kehadiran {{ props.minKehadiran }}% diberlakukan. UTS dihitung dari pertemuan kuliah sebelum UTS, UAS dari semua
                            pertemuan kuliah; angka masih sementara sampai pertemuan terakhir selesai.
                        </template>
                        <template v-else>
                            Syarat kehadiran ujian belum diberlakukan (Pengaturan Akademik), jadi semua mahasiswa boleh ikut ujian. Persentase tetap
                            ditampilkan sebagai informasi.
                        </template>
                    </p>
                    <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[760px] text-left text-sm">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th :class="th">Mahasiswa</th>
                                        <th :class="th">UTS</th>
                                        <th :class="th">UAS</th>
                                        <th v-if="props.bisaDispensasi" :class="[th, 'text-right']">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="mhs in props.peserta" :key="mhs.mahasiswa_id">
                                        <td class="px-4 py-2.5">
                                            <span class="block font-medium text-black">{{ mhs.nama }}</span>
                                            <span class="block text-xs text-[#a39e98]">{{ mhs.nim }}</span>
                                        </td>
                                        <td v-for="j in ['uts', 'uas'] as const" :key="j" class="px-4 py-2.5">
                                            <template v-if="mhs.ujian?.[j]">
                                                <span class="font-medium"
                                                    >{{ mhs.ujian[j]!.persen ?? '-' }}{{ mhs.ujian[j]!.persen !== null ? '%' : '' }}</span
                                                >
                                                <span class="text-xs text-[#a39e98]"> ({{ mhs.ujian[j]!.hadir }}/{{ mhs.ujian[j]!.dihitung }})</span>
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
                                        <td v-if="props.bisaDispensasi" class="px-4 py-2.5 text-right">
                                            <button
                                                type="button"
                                                class="text-sm font-medium text-[#0075de] hover:underline"
                                                @click="bukaDispensasi(mhs)"
                                            >
                                                Dispensasi
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.peserta.length">
                                        <td colspan="4" class="px-4 py-14 text-center text-sm text-[#615d59]">Belum ada mahasiswa di kelas ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col gap-3">
                    <p class="text-sm text-[#615d59]">
                        Dihitung dari {{ pertemuanDihitung }} pertemuan kuliah yang sudah selesai. Izin dan sakit dihitung tidak hadir; batas minimal
                        {{ props.minKehadiran }}%.
                    </p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span v-for="s in STATUS_PRESENSI" :key="s.value" class="rounded border px-1.5 py-0.5" :class="s.kelas"
                            >{{ s.singkat }} = {{ s.label }}</span
                        >
                    </div>
                    <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                        <div class="relative overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th :class="[th, 'sticky left-0 z-10 min-w-[200px] bg-[#f6f5f4]']">Mahasiswa</th>
                                        <th
                                            v-for="item in props.pertemuan"
                                            :key="item.id"
                                            class="px-1.5 py-3 text-center text-xs font-semibold text-[#a39e98]"
                                            :title="`${formatTanggal(item.tanggal)} — ${STATUS_PERTEMUAN[item.status].label}`"
                                        >
                                            {{ item.jenis === 'kuliah' ? item.pertemuan_ke : JENIS_PERTEMUAN[item.jenis] }}
                                        </th>
                                        <th :class="[th, 'text-right']">Kehadiran</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="mhs in props.peserta" :key="mhs.mahasiswa_id" :class="dibawahBatas(mhs.rekap) ? 'bg-[#fff8f7]' : ''">
                                        <td
                                            class="sticky left-0 z-10 bg-inherit px-4 py-2"
                                            :class="dibawahBatas(mhs.rekap) ? 'bg-[#fff8f7]' : 'bg-white'"
                                        >
                                            <span class="block font-medium text-black">{{ mhs.nama }}</span>
                                            <span class="block text-xs text-[#a39e98]">{{ mhs.nim }}</span>
                                        </td>
                                        <td v-for="item in props.pertemuan" :key="item.id" class="px-1.5 py-2 text-center">
                                            <span
                                                v-if="mhs.presensi[item.id]"
                                                class="inline-flex size-6 items-center justify-center rounded border text-xs font-semibold"
                                                :class="infoStatusPresensi(mhs.presensi[item.id])?.kelas"
                                                :title="infoStatusPresensi(mhs.presensi[item.id])?.label"
                                                >{{ infoStatusPresensi(mhs.presensi[item.id])?.singkat }}</span
                                            >
                                            <span v-else-if="item.status === 'dibatalkan'" class="text-xs text-[#d0ccc7]">×</span>
                                            <span v-else class="text-xs text-[#d0ccc7]">·</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right">
                                            <span class="font-semibold" :class="dibawahBatas(mhs.rekap) ? 'text-[#b42318]' : 'text-black'"
                                                >{{ mhs.rekap?.persen ?? '-' }}{{ mhs.rekap?.persen !== null && mhs.rekap ? '%' : '' }}</span
                                            >
                                            <span v-if="mhs.rekap" class="block text-xs text-[#a39e98]">
                                                {{ mhs.rekap.hadir + mhs.rekap.terlambat }}/{{ mhs.rekap.dihitung }} hadir
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.peserta.length">
                                        <td :colspan="props.pertemuan.length + 2" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                            Belum ada mahasiswa di kelas ini.
                                        </td>
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
                    <form class="flex w-full max-w-md flex-col gap-4 rounded-xl bg-white p-6" @submit.prevent="simpanDispensasi">
                        <div>
                            <h2 class="text-lg font-semibold">Dispensasi ujian — {{ dispensasiUntuk.nama }}</h2>
                            <p class="mt-1 text-sm text-[#615d59]">
                                Mahasiswa tetap boleh mengikuti ujian walau kehadirannya di bawah batas. Angka kehadiran tidak berubah.
                            </p>
                        </div>
                        <div class="flex gap-4">
                            <label v-for="j in ['uts', 'uas'] as const" :key="j" class="flex items-center gap-2 text-sm">
                                <input v-model="dispensasiForm.jenis" type="checkbox" :value="j" class="size-4 accent-[#0075de]" />
                                {{ JENIS_PERTEMUAN[j] }}
                            </label>
                        </div>
                        <InputError :message="dispensasiForm.errors.jenis" />
                        <div class="grid gap-1.5">
                            <Label for="alasan_dispensasi">Alasan</Label>
                            <Input
                                id="alasan_dispensasi"
                                v-model="dispensasiForm.alasan"
                                maxlength="255"
                                placeholder="mis. Rawat inap, surat RS terlampir"
                                :class="inp"
                                required
                            />
                            <InputError :message="dispensasiForm.errors.alasan ?? dispensasiForm.errors.mahasiswa_id" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="dispensasiUntuk = null">Batal</Button>
                            <Button
                                type="submit"
                                class="bg-[#0075de] text-white hover:bg-[#005bab]"
                                :disabled="dispensasiForm.processing || !dispensasiForm.jenis.length"
                                >Beri dispensasi</Button
                            >
                        </div>
                    </form>
                </div>

                <!-- Ubah pertemuan -->
                <div v-if="sunting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="sunting = null">
                    <form
                        class="flex max-h-[calc(100vh-2rem)] w-full max-w-lg flex-col gap-4 overflow-y-auto rounded-xl bg-white p-6"
                        @submit.prevent="simpanSunting"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">Ubah pertemuan ke-{{ sunting.pertemuan_ke }}</h2>
                            <p v-if="jadwalTerkunci" class="mt-1 text-sm text-[#615d59]">
                                Pertemuan sudah dimulai, jadi tanggal, jam, dan ruang tidak bisa diubah lagi.
                            </p>
                        </div>
                        <div class="grid content-start items-start gap-4 sm:grid-cols-3">
                            <div class="grid content-start gap-1.5 sm:col-span-3">
                                <Label for="tanggal">Tanggal</Label>
                                <Input id="tanggal" v-model="suntingForm.tanggal" type="date" :class="inp" :disabled="jadwalTerkunci" required />
                                <InputError :message="suntingForm.errors.tanggal" />
                            </div>
                            <div class="grid content-start gap-1.5">
                                <Label for="jam_mulai">Jam mulai</Label>
                                <Input id="jam_mulai" v-model="suntingForm.jam_mulai" type="time" :class="inp" :disabled="jadwalTerkunci" required />
                                <InputError :message="suntingForm.errors.jam_mulai" />
                            </div>
                            <div class="grid content-start gap-1.5">
                                <Label for="jam_akhir">Jam akhir</Label>
                                <Input id="jam_akhir" v-model="suntingForm.jam_akhir" type="time" :class="inp" :disabled="jadwalTerkunci" required />
                                <InputError :message="suntingForm.errors.jam_akhir" />
                            </div>
                            <div class="grid content-start gap-1.5">
                                <Label for="jenis">Jenis</Label>
                                <select id="jenis" v-model="suntingForm.jenis" :class="sel">
                                    <option v-for="(label, value) in JENIS_PERTEMUAN" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <InputError :message="suntingForm.errors.jenis" />
                            </div>
                            <div class="grid content-start gap-1.5 sm:col-span-3">
                                <Label for="ruang_id">Ruang</Label>
                                <select id="ruang_id" v-model="suntingForm.ruang_id" :class="sel" :disabled="jadwalTerkunci">
                                    <option value="">Tanpa ruang (daring)</option>
                                    <option v-for="r in props.ruangs" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                                <InputError :message="suntingForm.errors.ruang_id" />
                            </div>
                            <div class="grid content-start gap-1.5 sm:col-span-3">
                                <Label for="catatan">Catatan</Label>
                                <Input
                                    id="catatan"
                                    v-model="suntingForm.catatan"
                                    maxlength="255"
                                    placeholder="mis. Pengganti tanggal merah"
                                    :class="inp"
                                />
                                <InputError :message="suntingForm.errors.catatan" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="sunting = null">Batal</Button>
                            <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="suntingForm.processing"
                                >Simpan</Button
                            >
                        </div>
                    </form>
                </div>

                <!-- Batalkan pertemuan -->
                <div v-if="pembatalan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="pembatalan = null">
                    <form class="flex w-full max-w-md flex-col gap-4 rounded-xl bg-white p-6" @submit.prevent="simpanBatal">
                        <div>
                            <h2 class="text-lg font-semibold">Batalkan pertemuan ke-{{ pembatalan.pertemuan_ke }}?</h2>
                            <p class="mt-1 text-sm text-[#615d59]">
                                Pertemuan batal tidak dihitung dalam persentase kehadiran. Bila kuliah dipindah ke hari lain, ubah tanggalnya saja.
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="alasan">Alasan</Label>
                            <Input id="alasan" v-model="batalForm.catatan" maxlength="255" placeholder="mis. Libur nasional" :class="inp" required />
                            <InputError :message="batalForm.errors.catatan" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="pembatalan = null">Kembali</Button>
                            <Button type="submit" class="bg-[#dd5b00] text-white hover:bg-[#b84c00]" :disabled="batalForm.processing"
                                >Batalkan pertemuan</Button
                            >
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
