<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import LayarPresensiMandiri from '@/components/LayarPresensiMandiri.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFitur } from '@/composables/useFitur';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    JENIS_PERTEMUAN,
    STATUS_PRESENSI,
    formatJamDari,
    formatTanggal,
    jam,
    statusTampil,
    type JenisPertemuan,
    type StatusPertemuan,
    type StatusPresensi,
} from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCheck, Play, QrCode, Square, TriangleAlert } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

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
    dosen_masuk_at: string | null;
    dosen_keluar_at: string | null;
    topik: string | null;
    catatan: string | null;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    dosen?: { nidn: string; user?: { name: string } | null } | null;
};
type Baris = {
    mahasiswa_id: number;
    nim: string;
    nama: string;
    status: StatusPresensi;
    keterangan: string | null;
    metode: string;
    waktu_presensi: string | null;
    diubah_oleh: string | null;
    perangkat_bersama: boolean;
    memenuhi_syarat_ujian: boolean | null;
    pengajuan: { id: number; jenis: string; status: 'menunggu' | 'disetujui' | 'ditolak' } | null;
};

type RiwayatJadwal = {
    id: number;
    tanggal_lama: string;
    jam_mulai_lama: string;
    jam_akhir_lama: string;
    ruang_lama: string | null;
    dosen_lama: string | null;
    tanggal_baru: string;
    jam_mulai_baru: string;
    jam_akhir_baru: string;
    ruang_baru: string | null;
    dosen_baru: string | null;
    alasan: string;
    oleh: string | null;
    waktu: string | null;
};

const props = defineProps<{
    peran: Peran;
    kelasKuliah: {
        id: number;
        kode_kelas: string;
        dosen_id: number;
        mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
        dosen?: { user?: { name: string } | null } | null;
        tahun_akademik?: { tahun: string; semester: string } | null;
    };
    pertemuan: Pertemuan;
    presensi: Baris[];
    jumlahPeserta: number;
    bisaKelola: boolean;
    bisaAturJadwal: boolean;
    bisaDimulai: boolean;
    /** Detik menuju jam mulai menurut jam server; null bila sudah lewat atau pertemuan bukan terjadwal. */
    detikSampaiMulai: number | null;
    mandiriTerbuka: boolean;
    durasiMandiri: number;
    terkunci: boolean;
    dosenOptions: { id: number; name: string }[];
    riwayatJadwal: RiwayatJadwal[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const rute = rutePeran(props.peran);
const isAdmin = computed(() => props.peran === 'admin');
const status = computed(() => props.pertemuan.status);
const bisaIsi = computed(() => props.bisaKelola && !props.terkunci && (status.value === 'berlangsung' || status.value === 'selesai'));
// Dosen pengganti hanya boleh membuka pertemuan ini, bukan halaman kelasnya.
const pengganti = computed(() => props.bisaKelola && !props.bisaAturJadwal);

// Salinan lokal daftar hadir. Saat data dari server berganti (simpan jurnal, presensi mandiri, dsb.), baris
// yang sedang diubah dosen dan belum disimpan dipertahankan; baris lain ikut data terbaru dari server.
type BarisLokal = { mahasiswa_id: number; status: StatusPresensi; keterangan: string };
const baris = ref<BarisLokal[]>([]);
let dataServerSebelumnya = new Map<number, { status: StatusPresensi; keterangan: string }>();
watch(
    () => props.presensi,
    (data) => {
        const lokal = new Map(baris.value.map((item) => [item.mahasiswa_id, item]));
        baris.value = data.map((item) => {
            const milikDosen = lokal.get(item.mahasiswa_id);
            const sebelumnya = dataServerSebelumnya.get(item.mahasiswa_id);
            const belumDisimpan =
                milikDosen && sebelumnya && (milikDosen.status !== sebelumnya.status || milikDosen.keterangan.trim() !== sebelumnya.keterangan);

            return belumDisimpan ? milikDosen : { mahasiswa_id: item.mahasiswa_id, status: item.status, keterangan: item.keterangan ?? '' };
        });
        dataServerSebelumnya = new Map(data.map((item) => [item.mahasiswa_id, { status: item.status, keterangan: item.keterangan ?? '' }]));
    },
    { immediate: true },
);
const asli = computed(() => new Map(props.presensi.map((item) => [item.mahasiswa_id, item])));
const berubah = computed(() =>
    baris.value.filter((item) => {
        const awal = asli.value.get(item.mahasiswa_id);
        return awal && (awal.status !== item.status || (awal.keterangan ?? '') !== item.keterangan.trim());
    }),
);
// Peringatan sebelum meninggalkan halaman bila masih ada perubahan presensi yang belum disimpan.
const pesanBelumDisimpan = () => `Ada ${berubah.value.length} perubahan presensi yang belum disimpan. Tinggalkan halaman ini?`;
const hapusPenjagaNavigasi = router.on('before', (event) => {
    const kunjungan = event.detail.visit;
    // Hanya pindah halaman biasa; simpan jurnal/presensi dan muat ulang sebagian tidak dicegat.
    if (kunjungan.method !== 'get' || kunjungan.only.length > 0 || !berubah.value.length) return;
    if (!window.confirm(pesanBelumDisimpan())) event.preventDefault();
});
const cegahTutupTab = (event: BeforeUnloadEvent) => {
    if (berubah.value.length) event.preventDefault();
};
window.addEventListener('beforeunload', cegahTutupTab);
onUnmounted(() => {
    hapusPenjagaNavigasi();
    window.removeEventListener('beforeunload', cegahTutupTab);
});

const ringkasan = computed(() => STATUS_PRESENSI.map((s) => ({ ...s, jumlah: baris.value.filter((item) => item.status === s.value).length })));

const tandaiSemuaHadir = () =>
    baris.value.forEach((item) => {
        if (item.status === 'alpa') item.status = 'hadir';
    });

const menyimpan = ref(false);
const simpanPresensi = () =>
    router.put(
        rute('presensi.pertemuan.mahasiswa', props.pertemuan.id),
        { presensi: berubah.value.map((item) => ({ ...item, keterangan: item.keterangan.trim() || null })) },
        { preserveScroll: true, onStart: () => (menyimpan.value = true), onFinish: () => (menyimpan.value = false) },
    );

const mulai = () => router.post(rute('presensi.pertemuan.mulai', props.pertemuan.id), {}, { preserveScroll: true });

const jurnalForm = useForm({ topik: props.pertemuan.topik ?? '' });
watch(
    () => props.pertemuan.topik,
    (topik) => {
        if (!jurnalForm.isDirty) jurnalForm.defaults({ topik: topik ?? '' }).reset();
    },
);
const simpanJurnal = () => jurnalForm.put(rute('presensi.pertemuan.jurnal', props.pertemuan.id), { preserveScroll: true });
const selesaikan = () => {
    // Perubahan kehadiran yang belum disimpan ikut dikirim dulu agar tidak hilang saat pertemuan ditutup.
    const tutup = () => jurnalForm.post(rute('presensi.pertemuan.selesai', props.pertemuan.id), { preserveScroll: true });
    if (berubah.value.length) {
        router.put(
            rute('presensi.pertemuan.mahasiswa', props.pertemuan.id),
            { presensi: berubah.value.map((item) => ({ ...item, keterangan: item.keterangan.trim() || null })) },
            { preserveScroll: true, onSuccess: tutup },
        );
    } else {
        tutup();
    }
};

// Admin: tunjuk dosen pengganti sebelum pertemuan dimulai.
const penggantiForm = useForm({ dosen_id: props.pertemuan.dosen_id ?? ('' as number | string), alasan: '' });
const simpanPengganti = () =>
    penggantiForm
        .transform((data) => ({
            tanggal: props.pertemuan.tanggal.slice(0, 10),
            jam_mulai: jam(props.pertemuan.jam_mulai),
            jam_akhir: jam(props.pertemuan.jam_akhir),
            ruang_id: props.pertemuan.ruang_id,
            jenis: props.pertemuan.jenis,
            catatan: props.pertemuan.catatan,
            dosen_id: data.dosen_id || null,
            alasan: data.alasan,
        }))
        .put(rute('presensi.pertemuan.update', props.pertemuan.id), { preserveScroll: true, onSuccess: () => penggantiForm.reset('alasan') });

// Presensi mandiri (QR/PIN), hanya bila fitur presensi_qr aktif.
const presensiQr = useFitur().aktif('presensi_qr');
const mandiriForm = useForm({ menit: props.durasiMandiri });
const bukaMandiri = () => mandiriForm.post(rute('presensi.pertemuan.mandiri.buka', props.pertemuan.id), { preserveScroll: true });
const tutupMandiri = () => router.delete(rute('presensi.pertemuan.mandiri.tutup', props.pertemuan.id), { preserveScroll: true });
// Muat ulang daftar hadir saat ada yang presensi, kecuali dosen sedang menyunting (perubahannya belum disimpan).
// Hanya daftar hadir yang diminta ulang (server tidak menghitung bagian lain), dan tidak bertumpuk bila
// beberapa mahasiswa presensi berdekatan.
let sedangMemuat = false;
const muatUlangPresensi = () => {
    if (berubah.value.length || sedangMemuat) return;
    sedangMemuat = true;
    router.reload({ only: ['presensi'], onFinish: () => (sedangMemuat = false) });
};
const presensiTertutup = () => router.reload({ only: ['mandiriTerbuka', 'presensi'] });
const judulLayar = computed(() => `${props.kelasKuliah.mata_kuliah?.nama_matkul ?? ''} · Pertemuan ${props.pertemuan.pertemuan_ke}`);

// Hitung mundur ke jam mulai (dari jam server). Saat tiba, halaman meminta ulang izin mulai ke server.
const sisaDetik = ref(props.detikSampaiMulai);
let detak: number | undefined;
watch(
    () => props.detikSampaiMulai,
    (nilai) => {
        sisaDetik.value = nilai;
        window.clearInterval(detak);
        if (nilai === null) return;
        detak = window.setInterval(() => {
            if (sisaDetik.value === null) return;
            sisaDetik.value -= 1;
            if (sisaDetik.value <= 0) {
                window.clearInterval(detak);
                router.reload({ only: ['bisaDimulai', 'detikSampaiMulai', 'pertemuan'] });
            }
        }, 1000);
    },
    { immediate: true },
);
onUnmounted(() => window.clearInterval(detak));
const hitungMundur = computed(() => {
    const total = Math.max(sisaDetik.value ?? 0, 0);
    const jamSisa = Math.floor(total / 3600);
    const menit = Math.floor((total % 3600) / 60);
    const detik = total % 60;
    return jamSisa > 24 ? null : [jamSisa, menit, detik].map((n) => String(n).padStart(2, '0')).join(':');
});
const infoMulai = computed(() => {
    const waktu = `${formatTanggal(props.pertemuan.tanggal)} pukul ${jam(props.pertemuan.jam_mulai)}–${jam(props.pertemuan.jam_akhir)}`;
    if (props.pertemuan.terlewat) return `Jam pertemuan (${waktu}) sudah lewat. Pertemuan yang terlewat hanya bisa dicatat admin sebagai susulan.`;
    return `Pertemuan bisa dimulai ${waktu}, tidak bisa dibuka lebih awal dari jam mulai.`;
});
</script>

<template>
    <Head :title="`Pertemuan ${props.pertemuan.pertemuan_ke} · ${props.kelasKuliah.kode_kelas}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi', href: rute('presensi.index') },
            { title: props.kelasKuliah.kode_kelas, href: rute('presensi.kelas', props.kelasKuliah.id) },
            { title: `Pertemuan ${props.pertemuan.pertemuan_ke}`, href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman flex flex-wrap items-center gap-2">
                            Pertemuan {{ props.pertemuan.pertemuan_ke }}
                            <span
                                v-if="props.pertemuan.jenis !== 'kuliah'"
                                class="rounded bg-[#fff6e0] px-2 py-0.5 text-xs font-semibold tracking-normal text-[#8a5a00]"
                            >
                                {{ JENIS_PERTEMUAN[props.pertemuan.jenis] }}
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium tracking-normal" :class="statusTampil(props.pertemuan).kelas">
                                {{ statusTampil(props.pertemuan).label }}
                            </span>
                        </h1>
                        <p class="deskripsi-halaman">{{ props.kelasKuliah.mata_kuliah?.nama_matkul }} · {{ props.kelasKuliah.kode_kelas }}</p>
                        <p class="deskripsi-halaman">
                            {{ formatTanggal(props.pertemuan.tanggal) }} · {{ jam(props.pertemuan.jam_mulai) }}–{{ jam(props.pertemuan.jam_akhir) }} ·
                            {{ props.pertemuan.ruang ? `${props.pertemuan.ruang.kode_ruang} — ${props.pertemuan.ruang.nama_ruang}` : 'Tanpa ruang' }}
                        </p>
                        <p v-if="props.pertemuan.catatan" class="deskripsi-halaman">Catatan: {{ props.pertemuan.catatan }}</p>
                        <details v-if="props.riwayatJadwal.length" class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                            <summary class="cursor-pointer font-medium text-[#8a5a00]">Dijadwal ulang {{ props.riwayatJadwal.length }}×</summary>
                            <ul class="mt-2 space-y-2">
                                <li v-for="r in props.riwayatJadwal" :key="r.id" class="kartu px-3 py-2">
                                    <p class="text-[#31302e] dark:text-foreground">
                                        {{ formatTanggal(r.tanggal_lama) }} {{ r.jam_mulai_lama }}–{{ r.jam_akhir_lama
                                        }}{{ r.ruang_lama ? ` · ${r.ruang_lama}` : '' }} →
                                        <span class="font-medium text-black dark:text-foreground"
                                            >{{ formatTanggal(r.tanggal_baru) }} {{ r.jam_mulai_baru }}–{{ r.jam_akhir_baru
                                            }}{{ r.ruang_baru ? ` · ${r.ruang_baru}` : '' }}</span
                                        >
                                    </p>
                                    <p v-if="r.dosen_lama !== r.dosen_baru">Dosen: {{ r.dosen_lama ?? '-' }} → {{ r.dosen_baru ?? '-' }}</p>
                                    <p>Alasan: {{ r.alasan }}</p>
                                    <p class="teks-bantu">{{ r.oleh ?? 'Sistem' }} · {{ r.waktu ? formatTanggal(r.waktu) : '' }}</p>
                                </li>
                            </ul>
                        </details>
                    </div>
                    <Button v-if="!pengganti" as-child variant="outline">
                        <Link :href="rute('presensi.kelas', props.kelasKuliah.id)">Semua pertemuan</Link>
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="props.terkunci" class="alert-info">Tahun akademik kelas ini sudah tidak aktif. Presensi hanya bisa diubah admin.</div>

                <!-- Presensi dosen -->
                <section class="kartu p-6">
                    <h2 class="judul-bagian">Presensi dosen &amp; jurnal</h2>

                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="teks-bantu">Dosen</dt>
                            <dd class="font-medium text-black dark:text-foreground">
                                {{ props.pertemuan.dosen?.user?.name ?? props.kelasKuliah.dosen?.user?.name ?? '-' }}
                                <span
                                    v-if="props.pertemuan.dosen_id && props.pertemuan.dosen_id !== props.kelasKuliah.dosen_id"
                                    class="teks-bantu font-normal"
                                    >(pengganti)</span
                                >
                            </dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Jam masuk</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ formatJamDari(props.pertemuan.dosen_masuk_at) }}</dd>
                        </div>
                        <div>
                            <dt class="teks-bantu">Jam keluar</dt>
                            <dd class="font-medium text-black dark:text-foreground">{{ formatJamDari(props.pertemuan.dosen_keluar_at) }}</dd>
                        </div>
                    </dl>

                    <div v-if="status === 'dijadwalkan'" class="mt-4 flex flex-wrap items-center gap-3">
                        <Button v-if="props.bisaDimulai" size="lg" @click="mulai">
                            <Play /> {{ isAdmin ? 'Buka pertemuan' : 'Mulai kuliah' }}
                        </Button>
                        <div v-else-if="!props.terkunci && props.bisaKelola" class="text-sm text-[#615d59] dark:text-muted-foreground">
                            <p>{{ infoMulai }}</p>
                            <p v-if="hitungMundur" class="mt-1 font-medium text-black dark:text-foreground">
                                Tombol mulai muncul dalam <span class="font-mono">{{ hitungMundur }}</span>
                            </p>
                        </div>
                        <p v-if="isAdmin && props.bisaDimulai && props.pertemuan.terlewat" class="teks-bantu">
                            Pertemuan ini terlewat. Admin bisa membukanya untuk mencatat presensi susulan secara manual (tanpa QR/PIN); jam masuk
                            dosen tidak tercatat.
                        </p>
                    </div>

                    <form
                        v-if="isAdmin && status === 'dijadwalkan'"
                        class="mt-4 flex flex-wrap items-end gap-3 border-t border-[#e6e6e6] pt-4 dark:border-border"
                        @submit.prevent="simpanPengganti"
                    >
                        <div class="grid min-w-[260px] flex-1 gap-2">
                            <Label for="dosen_id" class="label-isian">Dosen yang mengajar</Label>
                            <select id="dosen_id" v-model="penggantiForm.dosen_id" class="isian isian-pilih">
                                <option v-for="d in props.dosenOptions" :key="d.id" :value="d.id">
                                    {{ d.name }}{{ d.id === props.kelasKuliah.dosen_id ? ' (pengampu)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="grid min-w-[260px] flex-1 gap-2">
                            <Label for="alasan_pengganti" class="label-isian">Alasan penggantian</Label>
                            <Input
                                id="alasan_pengganti"
                                v-model="penggantiForm.alasan"
                                maxlength="255"
                                placeholder="mis. Dosen pengampu dinas luar kota"
                            />
                        </div>
                        <Button type="submit" variant="outline" :disabled="penggantiForm.processing || !penggantiForm.isDirty">Simpan dosen</Button>
                        <InputError
                            class="w-full"
                            :message="
                                penggantiForm.errors.dosen_id ??
                                penggantiForm.errors.alasan ??
                                (penggantiForm.errors as Record<string, string | undefined>).tanggal
                            "
                        />
                    </form>

                    <form v-if="status === 'berlangsung' || status === 'selesai'" class="mt-4 grid gap-2" @submit.prevent="simpanJurnal">
                        <Label for="topik" class="label-isian">Topik / realisasi materi</Label>
                        <textarea
                            id="topik"
                            v-model="jurnalForm.topik"
                            rows="3"
                            :disabled="props.terkunci || !props.bisaKelola"
                            placeholder="Materi yang disampaikan pada pertemuan ini"
                            class="isian isian-area"
                        />
                        <InputError :message="jurnalForm.errors.topik" />
                        <div v-if="!props.terkunci && props.bisaKelola" class="flex flex-wrap justify-end gap-2">
                            <Button type="submit" variant="outline" :disabled="jurnalForm.processing || !jurnalForm.isDirty">Simpan jurnal</Button>
                            <Button v-if="status === 'berlangsung'" type="button" :disabled="jurnalForm.processing" @click="selesaikan">
                                <Square /> Selesaikan pertemuan
                            </Button>
                        </div>
                    </form>
                </section>

                <!-- Presensi mandiri -->
                <section v-if="presensiQr && status === 'berlangsung' && !props.terkunci && props.bisaKelola" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="judul-bagian">Presensi mandiri (QR / PIN)</h2>
                            <p class="mt-1 max-w-xl text-sm text-[#615d59] dark:text-muted-foreground">
                                Mahasiswa memindai QR atau mengetik PIN di menu Presensi. Kode berganti tiap 30 detik, jadi foto QR yang dikirim ke
                                teman cepat kedaluwarsa. Anda tetap bisa mengoreksi status secara manual.
                            </p>
                        </div>
                        <Button v-if="props.mandiriTerbuka" type="button" variant="outline" @click="tutupMandiri">Tutup</Button>
                    </div>

                    <form v-if="!props.mandiriTerbuka" class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="bukaMandiri">
                        <div class="grid gap-2">
                            <Label for="menit" class="label-isian">Dibuka selama (menit)</Label>
                            <Input id="menit" v-model="mandiriForm.menit" type="number" min="1" max="180" class="w-28" />
                        </div>
                        <Button type="submit" :disabled="mandiriForm.processing"> <QrCode /> Buka presensi mandiri </Button>
                        <InputError class="w-full" :message="mandiriForm.errors.menit" />
                    </form>

                    <div v-else class="mt-4 grid gap-4 md:grid-cols-[auto,1fr] md:items-start">
                        <LayarPresensiMandiri
                            :url-kode="rute('presensi.pertemuan.kode', props.pertemuan.id)"
                            :judul="judulLayar"
                            @hadir-berubah="muatUlangPresensi"
                            @tertutup="presensiTertutup"
                        />
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="bukaMandiri">
                            <div class="grid gap-2">
                                <Label for="menit" class="label-isian">Perpanjang dari sekarang (menit)</Label>
                                <Input id="menit" v-model="mandiriForm.menit" type="number" min="1" max="180" class="w-28" />
                            </div>
                            <Button type="submit" variant="outline" :disabled="mandiriForm.processing">Perpanjang</Button>
                        </form>
                    </div>
                </section>

                <!-- Presensi mahasiswa -->
                <section class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="judul-bagian">Presensi mahasiswa</h2>
                            <p v-if="status === 'dijadwalkan'" class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                                Daftar hadir {{ props.jumlahPeserta }} mahasiswa muncul setelah pertemuan dimulai.
                            </p>
                            <div v-else class="mt-2 flex flex-wrap gap-2 text-xs">
                                <span v-for="s in ringkasan" :key="s.value" class="rounded border px-2 py-0.5" :class="s.kelas"
                                    >{{ s.label }}: {{ s.jumlah }}</span
                                >
                            </div>
                        </div>
                        <Button v-if="bisaIsi && baris.length" type="button" variant="outline" size="sm" @click="tandaiSemuaHadir">
                            <CheckCheck /> Alpa → Hadir semua
                        </Button>
                    </div>

                    <div v-if="baris.length" class="tabel-wadah mt-4">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[780px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in baris" :key="item.mahasiswa_id">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <span class="block font-medium text-black dark:text-foreground">{{ props.presensi[index]?.nama }}</span>
                                            <span class="teks-bantu block">
                                                {{ props.presensi[index]?.nim }}
                                                <template v-if="['qr', 'pin'].includes(props.presensi[index]?.metode ?? '')">
                                                    · {{ props.presensi[index]?.metode.toUpperCase() }}
                                                    {{ formatJamDari(props.presensi[index]?.waktu_presensi) }}</template
                                                >
                                                <template v-else-if="props.presensi[index]?.diubah_oleh">
                                                    · diubah {{ props.presensi[index]?.diubah_oleh }}</template
                                                >
                                            </span>
                                            <span
                                                v-if="props.presensi[index]?.perangkat_bersama"
                                                class="mt-1 inline-flex items-center gap-1 rounded bg-[#fff6e0] px-1.5 py-0.5 text-xs text-[#8a5a00]"
                                                title="Perangkat yang sama dipakai presensi oleh mahasiswa lain di pertemuan ini"
                                            >
                                                <TriangleAlert class="size-3" /> Perangkat sama dengan mahasiswa lain
                                            </span>
                                            <span
                                                v-if="props.presensi[index]?.memenuhi_syarat_ujian === false"
                                                class="mt-1 inline-flex rounded bg-[#fdecea] px-1.5 py-0.5 text-xs text-[#b42318]"
                                                >Tidak memenuhi syarat kehadiran ujian</span
                                            >
                                            <span
                                                v-if="props.presensi[index]?.pengajuan"
                                                class="mt-1 inline-flex rounded bg-[#f6f5f4] px-1.5 py-0.5 text-xs text-[#615d59] dark:bg-muted dark:text-muted-foreground"
                                                >Pengajuan {{ props.presensi[index]?.pengajuan?.jenis }}:
                                                {{ props.presensi[index]?.pengajuan?.status }}</span
                                            >
                                        </td>
                                        <td>
                                            <!-- Tombol status presensi (kontrol khusus): ukuran seragam 36px, radius 8px. -->
                                            <div class="flex gap-1" role="radiogroup" :aria-label="`Status ${props.presensi[index]?.nama}`">
                                                <button
                                                    v-for="s in STATUS_PRESENSI"
                                                    :key="s.value"
                                                    type="button"
                                                    role="radio"
                                                    :aria-checked="item.status === s.value"
                                                    :title="s.label"
                                                    :disabled="!bisaIsi"
                                                    class="size-9 rounded-lg border text-sm font-semibold transition-colors disabled:cursor-not-allowed"
                                                    :class="
                                                        item.status === s.value
                                                            ? s.kelas
                                                            : 'border-[#e6e6e6] bg-white text-[#a39e98] hover:bg-[#f6f5f4] dark:border-border dark:bg-background dark:hover:bg-accent'
                                                    "
                                                    @click="item.status = s.value"
                                                >
                                                    {{ s.singkat }}
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <Input
                                                v-model="item.keterangan"
                                                maxlength="255"
                                                :disabled="!bisaIsi"
                                                :placeholder="item.status === 'izin' || item.status === 'sakit' ? 'Alasan izin/sakit' : ''"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="bisaIsi && baris.length" class="mt-4 flex flex-wrap items-center justify-end gap-3">
                        <span v-if="berubah.length" class="text-sm text-[#dd5b00]">{{ berubah.length }} perubahan belum disimpan</span>
                        <Button :disabled="menyimpan || !berubah.length" @click="simpanPresensi">Simpan presensi</Button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
