<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import DaftarSyarat from '@/components/tugas-akhir/DaftarSyarat.vue';
import InputBerkas from '@/components/tugas-akhir/InputBerkas.vue';
import KartuJadwal from '@/components/tugas-akhir/KartuJadwal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFitur } from '@/composables/useFitur';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import {
    HASIL_PENDADARAN,
    JENIS_PENGAJUAN,
    STATUS_PENGAJUAN,
    labelPeristiwa,
    type HasilPendadaran,
    type JadwalPendadaran,
    type JenisPengajuan,
    type KeadaanForm,
    type StatusPengajuan,
    type Syarat,
} from '@/lib/tugasAkhir';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Pengajuan = {
    id: number;
    status: StatusPengajuan;
    isian: Record<string, any>;
    lampiran: string[];
    catatan: string | null;
    diajukan_at: string | null;
    diproses_at: string | null;
};
type Tahap = { keadaan: KeadaanForm; syarat: Syarat[]; pengajuan: Pengajuan | null };
type Riwayat = {
    id: number;
    jenis: JenisPengajuan;
    status: StatusPengajuan;
    diajukan_at: string | null;
    riwayat: { status: string; catatan: string | null; oleh: string | null; waktu: string | null }[];
};

const props = defineProps<{
    bagian: 'tugas_akhir' | 'wisuda';
    tugasAkhir: {
        id: number;
        judul: string;
        bidang: string;
        pembimbing: string[];
        status: 'berjalan' | 'selesai';
        disahkan_at: string | null;
        naskah_diunggah_at: string | null;
        bisa_unggah_naskah: boolean;
    } | null;
    pengajuanTa: Tahap;
    pendaftaranPendadaran: Tahap & { jadwal: JadwalPendadaran | null; hasil: (HasilPendadaran & { id: number; tanggal: string }) | null };
    pendaftaranWisuda: Tahap & {
        periodeOptions: { id: number; nama: string; tanggal_acara: string; tempat: string | null; batas_daftar: string; sisa_kuota: number | null }[];
        dataIjazah: { nama_ijazah: string | null; tempat_lahir: string | null; tanggal_lahir: string | null };
        ukuranToga: string[];
        wisuda: {
            id: number;
            periode: { nama: string; tanggal_acara: string; tempat: string | null } | null;
            nomor_skl: string | null;
            skl_terbit_at: string | null;
            tanggal_lulus: string | null;
            ipk: number | null;
            predikat: string | null;
        } | null;
    };
    riwayat: Riwayat[];
    dosenOptions: { id: number; name: string }[];
    biaya: Record<'pendadaran' | 'wisuda', { nama: string; nominal: number }[]>;
}>();
const rupiah = (n: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
const infoBiaya = (kunci: 'pendadaran' | 'wisuda') =>
    props.biaya[kunci].length ? ' — ' + props.biaya[kunci].map((b) => `${b.nama} ${rupiah(b.nominal)}`).join(', ') : '';

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const terbuka = (t: Tahap) => t.keadaan === 'baru' || t.keadaan === 'perbaikan';
// Isian lama ditampilkan saat perbaikan (untuk diubah) dan saat menunggu (terkunci).
const isianLama = (t: Tahap) => (t.keadaan === 'perbaikan' || t.keadaan === 'menunggu' ? t.pengajuan : null);
const sudahAda = (t: Tahap, kunci: string) => !!isianLama(t)?.lampiran.includes(kunci);

const ta = computed(() => props.pengajuanTa);
const lamaTa = isianLama(props.pengajuanTa)?.isian;
const formTa = useForm({
    judul: lamaTa?.judul ?? '',
    bidang: lamaTa?.bidang ?? '',
    ringkasan: lamaTa?.ringkasan ?? '',
    usulan_pembimbing_1_id: (lamaTa?.usulan_pembimbing_1_id ?? null) as number | null,
    usulan_pembimbing_2_id: (lamaTa?.usulan_pembimbing_2_id ?? null) as number | null,
    proposal: null as File | null,
});
// Input berkas dipasang ulang (dikosongkan) setelah terkirim.
const versiBerkas = ref(0);
const kirimTa = () =>
    formTa.post(route('mahasiswa.tugas-akhir.ajukan-ta'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formTa.proposal = null;
            versiBerkas.value++;
        },
    });

const pd = computed(() => props.pendaftaranPendadaran);
const lamaPd = isianLama(props.pendaftaranPendadaran)?.isian;
const formPd = useForm({
    judul: lamaPd?.judul ?? props.tugasAkhir?.judul ?? '',
    naskah: null as File | null,
    persetujuan_pembimbing: null as File | null,
    bukti_bayar: null as File | null,
});
const kirimPd = () =>
    formPd.post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formPd.naskah = formPd.persetujuan_pembimbing = formPd.bukti_bayar = null;
            versiBerkas.value++;
        },
    });

const keterangan: Record<KeadaanForm, string> = {
    terkunci: 'Terbuka setelah tahap sebelumnya selesai',
    selesai: 'Selesai',
    terjadwal: 'Sudah dijadwalkan',
    terdaftar: 'Terdaftar sebagai peserta',
    menunggu: 'Sedang diproses',
    perbaikan: 'Perlu perbaikan',
    baru: 'Silakan ajukan',
    belum_memenuhi: 'Belum memenuhi syarat',
};
// Tanpa fitur pendadaran: tidak ada pembimbing maupun tahap pendadaran; TA selesai saat nilai MK TA/Skripsi lulus.
const pendadaran = useFitur().aktif('pendadaran');
const keteranganTa = () => {
    if (!props.tugasAkhir) return keterangan[ta.value.keadaan];
    if (pendadaran) return 'Disahkan';
    return props.tugasAkhir.status === 'selesai' ? 'Nilai lulus' : 'Menunggu nilai';
};
const tahapan = computed(() => [
    { no: 1, judul: 'Tugas Akhir', keterangan: keteranganTa(), aktif: true },
    ...(pendadaran
        ? [
              {
                  no: 2,
                  judul: 'Pendadaran',
                  keterangan: pd.value.jadwal?.status === 'revisi' ? 'Revisi naskah' : keterangan[pd.value.keadaan],
                  aktif: pd.value.keadaan !== 'terkunci',
              },
          ]
        : []),
    {
        no: pendadaran ? 3 : 2,
        judul: 'Wisuda',
        keterangan: ws.value.wisuda?.nomor_skl ? 'SKL terbit' : keterangan[ws.value.keadaan],
        aktif: ws.value.keadaan !== 'terkunci',
    },
]);

const formRevisi = useForm({ naskah_revisi: null as File | null });
const kirimRevisi = () =>
    formRevisi.post(route('mahasiswa.tugas-akhir.revisi'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formRevisi.naskah_revisi = null;
            versiBerkas.value++;
        },
    });

const ws = computed(() => props.pendaftaranWisuda);
const lamaWs = isianLama(props.pendaftaranWisuda)?.isian;
// Data ijazah diisi dari profil; mahasiswa mengoreksinya di sini bila ada yang salah.
const formWs = useForm({
    periode_wisuda_id: (lamaWs?.periode_wisuda_id ?? props.pendaftaranWisuda.periodeOptions[0]?.id ?? null) as number | null,
    nama_ijazah: lamaWs?.nama_ijazah ?? props.pendaftaranWisuda.dataIjazah.nama_ijazah ?? '',
    tempat_lahir: lamaWs?.tempat_lahir ?? props.pendaftaranWisuda.dataIjazah.tempat_lahir ?? '',
    tanggal_lahir: lamaWs?.tanggal_lahir ?? props.pendaftaranWisuda.dataIjazah.tanggal_lahir ?? '',
    ukuran_toga: lamaWs?.ukuran_toga ?? '',
    pas_foto: null as File | null,
    bebas_pinjam: null as File | null,
    surat_lunas: null as File | null,
});
const kirimWs = () =>
    formWs.post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formWs.pas_foto = formWs.bebas_pinjam = formWs.surat_lunas = null;
            versiBerkas.value++;
        },
    });
const berkasWisuda = [
    { kunci: 'pas_foto', label: 'Pas foto (JPG/PNG, maks. 2 MB)', accept: 'image/jpeg,image/png,.jpg,.jpeg,.png' },
    { kunci: 'bebas_pinjam', label: 'Surat bebas pustaka', accept: 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png' },
    { kunci: 'surat_lunas', label: 'Surat keterangan lunas', accept: 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png' },
] as const;

// Halaman Pengajuan Judul & Upload TA atau Pengajuan Wisuda (data sama, bagian berbeda).
const halamanTa = props.bagian === 'tugas_akhir';
const judulHalaman = halamanTa ? 'Pengajuan Judul & Upload TA' : 'Pengajuan Wisuda';
const deskripsiHalaman = halamanTa
    ? pendadaran
        ? 'Ajukan judul tugas akhir, unggah naskah TA, lalu daftar pendadaran.'
        : 'Ajukan judul tugas akhir; setelah judul disahkan, unggah naskah TA Anda.'
    : 'Daftar wisuda setelah tugas akhir Anda selesai.';

const formNaskah = useForm({ naskah_ta: null as File | null });
const kirimNaskah = () =>
    formNaskah.post(route('mahasiswa.tugas-akhir.naskah'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formNaskah.naskah_ta = null;
            versiBerkas.value++;
        },
    });

const menunggu = (t: Tahap) => (t.pengajuan?.status === 'menunggu_pembimbing' ? 'menunggu persetujuan pembimbing' : 'menunggu diproses admin');

const dokumen = 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png';
</script>

<template>
    <Head :title="judulHalaman" />
    <AppLayout :breadcrumbs="[{ title: judulHalaman, href: route(halamanTa ? 'mahasiswa.tugas-akhir' : 'mahasiswa.wisuda') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ judulHalaman }}</h1>
                        <p class="deskripsi-halaman">{{ deskripsiHalaman }}</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <ol class="grid gap-3" :class="pendadaran ? 'sm:grid-cols-3' : 'sm:grid-cols-2'">
                    <li
                        v-for="t in tahapan"
                        :key="t.no"
                        class="kartu flex items-start gap-3 p-4"
                        :class="t.aktif ? 'border-[#0075de]/40 dark:border-[#0075de]/40' : 'opacity-70'"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="t.aktif ? 'bg-[#0075de] text-white' : 'bg-[#f6f5f4] text-[#a39e98] dark:bg-muted'"
                            >{{ t.no }}</span
                        >
                        <span class="grid gap-0.5">
                            <span class="flex items-center gap-1.5 text-sm font-medium text-black dark:text-foreground"
                                >{{ t.judul }} <Lock v-if="!t.aktif" class="size-3.5 text-[#a39e98]"
                            /></span>
                            <span class="teks-bantu">{{ t.keterangan }}</span>
                        </span>
                    </li>
                </ol>

                <!-- Tahap 1: tugas akhir -->
                <section v-if="halamanTa && props.tugasAkhir" class="kartu p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="judul-bagian">Tugas Akhir Anda</h2>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="props.tugasAkhir.status === 'selesai' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f2f9ff] text-[#0075de]'"
                            >{{ props.tugasAkhir.status === 'selesai' ? 'Selesai' : 'Berjalan' }}</span
                        >
                    </div>
                    <p class="mt-3 text-lg font-semibold leading-snug text-black dark:text-foreground">{{ props.tugasAkhir.judul }}</p>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="teks-bantu uppercase tracking-[0.04em]">Bidang</dt>
                            <dd class="mt-1 text-black dark:text-foreground">{{ props.tugasAkhir.bidang }}</dd>
                        </div>
                        <div v-if="props.tugasAkhir.pembimbing.length">
                            <dt class="teks-bantu uppercase tracking-[0.04em]">Pembimbing</dt>
                            <dd v-for="(nama, i) in props.tugasAkhir.pembimbing" :key="nama" class="mt-1 text-black dark:text-foreground">
                                {{ i + 1 }}. {{ nama }}
                            </dd>
                        </div>
                        <div>
                            <dt class="teks-bantu uppercase tracking-[0.04em]">Disahkan</dt>
                            <dd class="mt-1 text-black dark:text-foreground">{{ formatTanggal(props.tugasAkhir.disahkan_at, false) }}</dd>
                        </div>
                    </dl>
                    <p v-if="!pendadaran && props.tugasAkhir.status !== 'selesai'" class="alert-info mt-4" role="status">
                        Sidang dilaksanakan di luar sistem. Tugas akhir dinyatakan selesai setelah nilai mata kuliah TA/Skripsi Anda lulus; sesudah
                        itu pendaftaran wisuda terbuka.
                    </p>

                    <form class="mt-5 grid gap-3 rounded-lg border border-[#e6e6e6] p-4 dark:border-border" @submit.prevent="kirimNaskah">
                        <p class="text-sm font-medium text-black dark:text-foreground">Upload Naskah TA</p>
                        <p v-if="props.tugasAkhir.naskah_diunggah_at" class="text-sm text-[#31302e] dark:text-foreground">
                            Naskah diunggah {{ formatTanggal(props.tugasAkhir.naskah_diunggah_at, false) }}.
                            <a
                                :href="route('berkas.naskah-ta', props.tugasAkhir.id)"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-[#0075de] hover:underline"
                                >Lihat naskah</a
                            >
                        </p>
                        <p v-else class="teks-bantu">
                            Belum ada naskah. Unggah naskah TA/Skripsi Anda dalam satu berkas PDF; naskah ini juga menjadi naskah final syarat wisuda.
                        </p>
                        <template v-if="props.tugasAkhir.bisa_unggah_naskah">
                            <InputBerkas
                                id="naskah_ta"
                                :key="`naskah_ta-${versiBerkas}`"
                                :label="props.tugasAkhir.naskah_diunggah_at ? 'Ganti naskah (PDF, maks. 20 MB)' : 'Naskah TA (PDF, maks. 20 MB)'"
                                accept="application/pdf,.pdf"
                                :wajib="true"
                                :error="formNaskah.errors.naskah_ta"
                                @pilih="formNaskah.naskah_ta = $event"
                            />
                            <div class="flex justify-end">
                                <Button type="submit" :disabled="!formNaskah.naskah_ta || formNaskah.processing">
                                    {{ props.tugasAkhir.naskah_diunggah_at ? 'Ganti Naskah' : 'Upload Naskah' }}
                                </Button>
                            </div>
                        </template>
                        <p v-else class="teks-bantu">
                            Naskah tidak bisa diganti selama pendaftaran wisuda diproses atau setelah Anda terdaftar sebagai peserta wisuda.
                        </p>
                    </form>
                </section>

                <section v-else-if="halamanTa" class="kartu p-6">
                    <h2 class="judul-bagian">Pengajuan Judul Tugas Akhir/Skripsi</h2>
                    <DaftarSyarat class="mt-4" :syarat="ta.syarat" />
                    <div v-if="ta.keadaan === 'belum_memenuhi'" class="alert-gagal mt-4" role="status">
                        Anda belum memenuhi syarat pengajuan tugas akhir. Form terbuka setelah semua syarat di atas terpenuhi.
                    </div>
                    <div v-else-if="ta.keadaan === 'menunggu'" class="alert-info mt-4" role="status">
                        Pengajuan dikirim {{ formatTanggal(ta.pengajuan?.diajukan_at, false) }} dan sedang {{ menunggu(ta) }}. Form terbuka lagi
                        setelah ada keputusan.
                    </div>
                    <div v-else-if="ta.keadaan === 'perbaikan'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Diminta perbaikan:</span> {{ ta.pengajuan?.catatan }}
                    </div>
                    <div v-else-if="ta.pengajuan?.status === 'ditolak'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Pengajuan sebelumnya ditolak:</span> {{ ta.pengajuan.catatan }} Anda bisa mengajukan lagi.
                    </div>

                    <form class="mt-5" @submit.prevent="kirimTa">
                        <fieldset :disabled="!terbuka(ta) || formTa.processing" class="grid gap-4 disabled:opacity-60">
                            <div class="grid gap-2">
                                <Label for="judul" class="label-isian">Judul</Label>
                                <Input id="judul" v-model="formTa.judul" maxlength="300" required />
                                <InputError :message="formTa.errors.judul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bidang" class="label-isian">Bidang / topik</Label>
                                <Input id="bidang" v-model="formTa.bidang" maxlength="150" placeholder="Mis. Sistem Informasi" required />
                                <InputError :message="formTa.errors.bidang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ringkasan" class="label-isian">Ringkasan proposal</Label>
                                <textarea
                                    id="ringkasan"
                                    v-model="formTa.ringkasan"
                                    class="isian isian-area min-h-28"
                                    maxlength="5000"
                                    placeholder="Latar belakang, rumusan masalah, dan metode singkat"
                                    required
                                />
                                <InputError :message="formTa.errors.ringkasan" />
                            </div>
                            <div v-if="pendadaran" class="grid items-start gap-4 sm:grid-cols-2">
                                <div class="grid content-start gap-2">
                                    <Label for="usulan_pembimbing_1_id" class="label-isian">Usulan pembimbing 1</Label>
                                    <SearchSelect
                                        id="usulan_pembimbing_1_id"
                                        v-model="formTa.usulan_pembimbing_1_id"
                                        :options="props.dosenOptions"
                                        placeholder="Pilih dosen"
                                        search-placeholder="Cari dosen"
                                        required
                                    />
                                    <InputError :message="formTa.errors.usulan_pembimbing_1_id" />
                                </div>
                                <div class="grid content-start gap-2">
                                    <Label for="usulan_pembimbing_2_id" class="label-isian flex items-center justify-between">
                                        <span>Usulan pembimbing 2 <span class="font-normal text-[#a39e98]">(opsional)</span></span>
                                        <button
                                            v-if="formTa.usulan_pembimbing_2_id && terbuka(ta)"
                                            type="button"
                                            class="text-xs font-normal text-[#0075de] hover:underline"
                                            @click="formTa.usulan_pembimbing_2_id = null"
                                        >
                                            Kosongkan
                                        </button>
                                    </Label>
                                    <SearchSelect
                                        id="usulan_pembimbing_2_id"
                                        v-model="formTa.usulan_pembimbing_2_id"
                                        :options="props.dosenOptions"
                                        placeholder="Tanpa pembimbing 2"
                                        search-placeholder="Cari dosen"
                                    />
                                    <InputError :message="formTa.errors.usulan_pembimbing_2_id" />
                                </div>
                            </div>
                            <InputBerkas
                                id="proposal"
                                :key="`proposal-${versiBerkas}`"
                                label="Proposal (PDF, maks. 10 MB)"
                                accept="application/pdf,.pdf"
                                :pengajuan-id="ta.pengajuan?.id"
                                :sudah-ada="sudahAda(ta, 'proposal')"
                                :wajib="ta.keadaan === 'baru'"
                                :terkunci="!terbuka(ta)"
                                :error="formTa.errors.proposal"
                                @pilih="formTa.proposal = $event"
                            />
                            <div v-if="terbuka(ta)" class="flex justify-end">
                                <Button type="submit">
                                    {{ ta.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Kirim Pengajuan' }}
                                </Button>
                            </div>
                        </fieldset>
                    </form>
                </section>

                <!-- Tahap 2: pendadaran -->
                <section v-if="halamanTa && pendadaran && pd.keadaan !== 'terkunci'" class="kartu p-6">
                    <h2 class="judul-bagian">Tahap 2 · Pendadaran</h2>

                    <div v-if="pd.hasil?.hasil" class="mt-4 rounded-lg border border-[#e6e6e6] px-4 py-3 text-sm dark:border-border">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-black dark:text-foreground"
                                >Hasil pendadaran {{ formatTanggal(pd.hasil.tanggal, false) }}</span
                            >
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="HASIL_PENDADARAN[pd.hasil.hasil].kelas">{{
                                HASIL_PENDADARAN[pd.hasil.hasil].label
                            }}</span>
                            <span class="text-[#615d59] dark:text-muted-foreground">Nilai {{ pd.hasil.nilai_akhir }} ({{ pd.hasil.huruf }})</span>
                        </div>
                        <p v-if="pd.hasil.catatan_hasil" class="mt-1 whitespace-pre-line text-[#31302e] dark:text-foreground">
                            {{ pd.hasil.catatan_hasil }}
                        </p>
                        <p v-if="pd.hasil.revisi_disahkan_at" class="mt-1 text-[#1aae39]">
                            Revisi disahkan {{ formatTanggal(pd.hasil.revisi_disahkan_at, false) }}. Tugas akhir selesai.
                        </p>
                        <p v-if="pd.hasil.hasil === 'tidak_lulus' && !pd.jadwal" class="mt-1 text-[#dd5b00]">
                            Anda bisa mendaftar pendadaran ulang di bawah ini.
                        </p>
                    </div>

                    <template v-if="pd.jadwal">
                        <div v-if="pd.jadwal.status === 'dijadwalkan'" class="alert-info mt-4" role="status">
                            Pendadaran Anda sudah dijadwalkan. Hadir tepat waktu dan bawa naskah.
                        </div>
                        <KartuJadwal class="mt-4" :jadwal="pd.jadwal" />

                        <form
                            v-if="pd.jadwal.status === 'revisi'"
                            class="mt-5 grid gap-3 rounded-lg border border-[#f4cfb6] bg-[#fdf3ec] p-4 dark:border-orange-900 dark:bg-orange-950/40"
                            @submit.prevent="kirimRevisi"
                        >
                            <p class="text-sm font-medium text-[#a84400] dark:text-orange-300">Revisi naskah</p>
                            <p v-if="pd.hasil?.revisi_diunggah_at" class="text-sm text-[#31302e] dark:text-foreground">
                                Naskah revisi dikirim {{ formatTanggal(pd.hasil.revisi_diunggah_at, false) }} dan menunggu pengesahan ketua penguji.
                                <a
                                    :href="route('berkas.naskah-revisi', pd.jadwal.id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-[#0075de] hover:underline"
                                    >Lihat naskah</a
                                >
                            </p>
                            <template v-else>
                                <p v-if="pd.hasil?.catatan_revisi" class="text-sm text-[#dd5b00]">
                                    <span class="font-medium">Revisi dikembalikan:</span> {{ pd.hasil.catatan_revisi }}
                                </p>
                                <InputBerkas
                                    id="naskah_revisi"
                                    :key="`naskah_revisi-${versiBerkas}`"
                                    label="Naskah revisi (PDF, maks. 20 MB)"
                                    accept="application/pdf,.pdf"
                                    :wajib="true"
                                    :error="formRevisi.errors.naskah_revisi"
                                    @pilih="formRevisi.naskah_revisi = $event"
                                />
                                <div class="flex justify-end">
                                    <Button type="submit" :disabled="formRevisi.processing">Kirim Revisi</Button>
                                </div>
                            </template>
                        </form>
                    </template>

                    <template v-else-if="pd.keadaan !== 'selesai'">
                        <DaftarSyarat class="mt-4" :syarat="pd.syarat" />
                        <div v-if="pd.keadaan === 'belum_memenuhi'" class="alert-gagal mt-4" role="status">
                            Anda belum memenuhi syarat pendaftaran pendadaran. Form terbuka setelah semua syarat di atas terpenuhi.
                        </div>
                        <div v-else-if="pd.keadaan === 'menunggu'" class="alert-info mt-4" role="status">
                            Pendaftaran dikirim {{ formatTanggal(pd.pengajuan?.diajukan_at, false) }} dan sedang {{ menunggu(pd) }}. Form terbuka lagi
                            setelah ada keputusan.
                        </div>
                        <div v-else-if="pd.keadaan === 'perbaikan'" class="alert-gagal mt-4" role="status">
                            <span class="font-medium">Diminta perbaikan:</span> {{ pd.pengajuan?.catatan }}
                        </div>
                        <div v-else-if="pd.pengajuan?.status === 'ditolak'" class="alert-gagal mt-4" role="status">
                            <span class="font-medium">Pendaftaran sebelumnya ditolak:</span> {{ pd.pengajuan.catatan }} Anda bisa mendaftar lagi.
                        </div>

                        <form class="mt-5" @submit.prevent="kirimPd">
                            <fieldset :disabled="!terbuka(pd) || formPd.processing" class="grid gap-4 disabled:opacity-60">
                                <div class="grid gap-2">
                                    <Label for="judul_final" class="label-isian">Judul final</Label>
                                    <Input id="judul_final" v-model="formPd.judul" maxlength="300" required />
                                    <InputError :message="formPd.errors.judul" />
                                </div>
                                <div class="grid items-start gap-4 sm:grid-cols-3">
                                    <InputBerkas
                                        id="naskah"
                                        :key="`naskah-${versiBerkas}`"
                                        label="Naskah (PDF, maks. 20 MB)"
                                        accept="application/pdf,.pdf"
                                        :pengajuan-id="pd.pengajuan?.id"
                                        :sudah-ada="sudahAda(pd, 'naskah')"
                                        :wajib="pd.keadaan === 'baru'"
                                        :terkunci="!terbuka(pd)"
                                        :error="formPd.errors.naskah"
                                        @pilih="formPd.naskah = $event"
                                    />
                                    <InputBerkas
                                        id="persetujuan_pembimbing"
                                        :key="`persetujuan_pembimbing-${versiBerkas}`"
                                        label="Lembar persetujuan pembimbing"
                                        :accept="dokumen"
                                        :pengajuan-id="pd.pengajuan?.id"
                                        :sudah-ada="sudahAda(pd, 'persetujuan_pembimbing')"
                                        :wajib="pd.keadaan === 'baru'"
                                        :terkunci="!terbuka(pd)"
                                        :error="formPd.errors.persetujuan_pembimbing"
                                        @pilih="formPd.persetujuan_pembimbing = $event"
                                    />
                                    <InputBerkas
                                        id="bukti_bayar"
                                        :key="`bukti_bayar-${versiBerkas}`"
                                        :label="`Bukti bayar pendadaran${infoBiaya('pendadaran')}`"
                                        :accept="dokumen"
                                        :pengajuan-id="pd.pengajuan?.id"
                                        :sudah-ada="sudahAda(pd, 'bukti_bayar')"
                                        :wajib="pd.keadaan === 'baru'"
                                        :terkunci="!terbuka(pd)"
                                        :error="formPd.errors.bukti_bayar"
                                        @pilih="formPd.bukti_bayar = $event"
                                    />
                                </div>
                                <p class="teks-bantu">
                                    Pendaftaran diperiksa salah satu pembimbing dulu, lalu admin menjadwalkan pendadaran dan menetapkan tiga penguji.
                                </p>
                                <div v-if="terbuka(pd)" class="flex justify-end">
                                    <Button type="submit">
                                        {{ pd.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Daftar Pendadaran' }}
                                    </Button>
                                </div>
                            </fieldset>
                        </form>
                    </template>
                </section>

                <!-- Tahap 3: wisuda -->
                <section v-if="!halamanTa && ws.keadaan === 'terkunci'" class="kartu p-6">
                    <h2 class="judul-bagian">Pendaftaran Wisuda</h2>
                    <div class="alert-info mt-4" role="status">
                        Pendaftaran wisuda terbuka setelah tugas akhir Anda selesai ({{
                            pendadaran ? 'lulus pendadaran' : 'nilai mata kuliah TA/Skripsi lulus'
                        }}). Pantau tugas akhir Anda di
                        <Link :href="route('mahasiswa.tugas-akhir')" class="font-medium text-[#0075de] hover:underline"
                            >Pengajuan Judul & Upload TA</Link
                        >.
                    </div>
                </section>

                <section v-if="!halamanTa && ws.keadaan !== 'terkunci'" class="kartu p-6">
                    <h2 class="judul-bagian">Pendaftaran Wisuda</h2>

                    <template v-if="ws.wisuda">
                        <div class="alert-info mt-4" role="status">
                            Anda terdaftar sebagai peserta {{ ws.wisuda.periode?.nama }} pada {{ formatTanggal(ws.wisuda.periode?.tanggal_acara)
                            }}<span v-if="ws.wisuda.periode?.tempat">, {{ ws.wisuda.periode.tempat }}</span
                            >.
                        </div>
                        <dl v-if="ws.wisuda.nomor_skl" class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="teks-bantu uppercase tracking-[0.04em]">Surat Keterangan Lulus</dt>
                                <dd class="mt-1">
                                    <a
                                        :href="route('berkas.skl', ws.wisuda.id)"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 font-medium text-[#0075de] hover:underline"
                                        ><FileText class="size-4" /> Unduh SKL</a
                                    >
                                    <span class="teks-bantu block">No. {{ ws.wisuda.nomor_skl }}</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="teks-bantu uppercase tracking-[0.04em]">Tanggal lulus</dt>
                                <dd class="mt-1 text-black dark:text-foreground">{{ formatTanggal(ws.wisuda.tanggal_lulus, false) }}</dd>
                            </div>
                            <div>
                                <dt class="teks-bantu uppercase tracking-[0.04em]">IPK & predikat</dt>
                                <dd class="mt-1 text-black dark:text-foreground">{{ ws.wisuda.ipk?.toFixed(2) }} · {{ ws.wisuda.predikat }}</dd>
                            </div>
                        </dl>
                        <p v-else class="mt-3 text-sm text-[#615d59] dark:text-muted-foreground">
                            Surat keterangan lulus (SKL) diterbitkan admin dan bisa diunduh di sini.
                        </p>
                    </template>

                    <template v-else>
                        <DaftarSyarat class="mt-4" :syarat="ws.syarat" />
                        <div v-if="ws.keadaan === 'belum_memenuhi'" class="alert-gagal mt-4" role="status">
                            Anda belum memenuhi syarat pendaftaran wisuda. Form terbuka setelah semua syarat di atas terpenuhi.
                        </div>
                        <div v-else-if="ws.keadaan === 'menunggu'" class="alert-info mt-4" role="status">
                            Pendaftaran dikirim {{ formatTanggal(ws.pengajuan?.diajukan_at, false) }} dan sedang menunggu diproses admin.
                        </div>
                        <div v-else-if="ws.keadaan === 'perbaikan'" class="alert-gagal mt-4" role="status">
                            <span class="font-medium">Diminta perbaikan:</span> {{ ws.pengajuan?.catatan }}
                        </div>
                        <div v-else-if="ws.pengajuan?.status === 'ditolak'" class="alert-gagal mt-4" role="status">
                            <span class="font-medium">Pendaftaran sebelumnya ditolak:</span> {{ ws.pengajuan.catatan }} Anda bisa mendaftar lagi.
                        </div>

                        <form class="mt-5" @submit.prevent="kirimWs">
                            <fieldset :disabled="!terbuka(ws) || formWs.processing" class="grid gap-4 disabled:opacity-60">
                                <div class="grid gap-2">
                                    <Label for="periode_wisuda_id" class="label-isian">Periode wisuda</Label>
                                    <select id="periode_wisuda_id" v-model="formWs.periode_wisuda_id" class="isian isian-pilih" required>
                                        <option v-for="p in ws.periodeOptions" :key="p.id" :value="p.id">
                                            {{ p.nama }} — {{ formatTanggal(p.tanggal_acara, false) }} (daftar s.d.
                                            {{ formatTanggal(p.batas_daftar, false)
                                            }}{{ p.sisa_kuota !== null ? `, sisa ${p.sisa_kuota} kursi` : '' }})
                                        </option>
                                    </select>
                                    <InputError :message="formWs.errors.periode_wisuda_id" />
                                </div>
                                <div class="rounded-lg border border-[#e6e6e6] p-4 dark:border-border">
                                    <p class="text-sm font-medium text-black dark:text-foreground">Data ijazah</p>
                                    <p class="teks-bantu">Diisi dari profil Anda. Periksa dengan teliti dan koreksi bila ada yang salah.</p>
                                    <div class="mt-3 grid items-start gap-4 sm:grid-cols-3">
                                        <div class="grid content-start gap-2">
                                            <Label for="nama_ijazah" class="label-isian">Nama lengkap</Label>
                                            <Input id="nama_ijazah" v-model="formWs.nama_ijazah" maxlength="150" required />
                                            <InputError :message="formWs.errors.nama_ijazah" />
                                        </div>
                                        <div class="grid content-start gap-2">
                                            <Label for="tempat_lahir" class="label-isian">Tempat lahir</Label>
                                            <Input id="tempat_lahir" v-model="formWs.tempat_lahir" maxlength="100" required />
                                            <InputError :message="formWs.errors.tempat_lahir" />
                                        </div>
                                        <div class="grid content-start gap-2">
                                            <Label for="tanggal_lahir" class="label-isian">Tanggal lahir</Label>
                                            <Input id="tanggal_lahir" v-model="formWs.tanggal_lahir" type="date" required />
                                            <InputError :message="formWs.errors.tanggal_lahir" />
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-2 sm:w-48">
                                    <Label for="ukuran_toga" class="label-isian">Ukuran toga</Label>
                                    <select id="ukuran_toga" v-model="formWs.ukuran_toga" class="isian isian-pilih" required>
                                        <option value="" disabled>Pilih ukuran</option>
                                        <option v-for="u in ws.ukuranToga" :key="u" :value="u">{{ u }}</option>
                                    </select>
                                    <InputError :message="formWs.errors.ukuran_toga" />
                                </div>
                                <p v-if="props.tugasAkhir?.naskah_diunggah_at" class="text-sm text-[#31302e] dark:text-foreground">
                                    Naskah final memakai naskah TA yang Anda unggah
                                    {{ formatTanggal(props.tugasAkhir.naskah_diunggah_at, false) }}
                                    (<a
                                        :href="route('berkas.naskah-ta', props.tugasAkhir.id)"
                                        target="_blank"
                                        rel="noopener"
                                        class="font-medium text-[#0075de] hover:underline"
                                        >lihat</a
                                    >). Ganti di
                                    <Link :href="route('mahasiswa.tugas-akhir')" class="font-medium text-[#0075de] hover:underline"
                                        >Pengajuan Judul & Upload TA</Link
                                    >
                                    sebelum mendaftar bila perlu.
                                </p>
                                <div class="grid items-start gap-4 sm:grid-cols-2">
                                    <InputBerkas
                                        v-for="b in berkasWisuda"
                                        :id="b.kunci"
                                        :key="`${b.kunci}-${versiBerkas}`"
                                        :label="b.label"
                                        :accept="b.accept"
                                        :pengajuan-id="ws.pengajuan?.id"
                                        :sudah-ada="sudahAda(ws, b.kunci)"
                                        :wajib="ws.keadaan === 'baru'"
                                        :terkunci="!terbuka(ws)"
                                        :error="formWs.errors[b.kunci]"
                                        @pilih="formWs[b.kunci] = $event"
                                    />
                                </div>
                                <div v-if="terbuka(ws)" class="flex justify-end">
                                    <Button type="submit">
                                        {{ ws.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Daftar Wisuda' }}
                                    </Button>
                                </div>
                            </fieldset>
                        </form>
                    </template>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Riwayat Pengajuan</h2>
                    <p v-if="!props.riwayat.length" class="mt-3 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada pengajuan.</p>
                    <div
                        v-for="r in props.riwayat"
                        :key="r.id"
                        class="mt-4 border-t border-[#e6e6e6] pt-4 first:border-0 first:pt-0 dark:border-border"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-black dark:text-foreground">{{ JENIS_PENGAJUAN[r.jenis] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_PENGAJUAN[r.status].kelas">{{
                                STATUS_PENGAJUAN[r.status].label
                            }}</span>
                        </div>
                        <ol class="mt-2 grid gap-1.5 border-l border-[#e6e6e6] pl-4 dark:border-border">
                            <li v-for="(h, i) in r.riwayat" :key="i" class="text-sm text-[#31302e] dark:text-foreground">
                                <span class="text-xs text-[#a39e98]">{{ formatTanggal(h.waktu, false) }} {{ h.waktu?.slice(11, 16) }}</span>
                                · {{ labelPeristiwa(h.status, i === 0) }}
                                <span v-if="h.status !== 'dikirim' && h.oleh" class="text-[#615d59]">oleh {{ h.oleh }}</span>
                                <span v-if="h.catatan" class="teks-bantu block">{{ h.catatan }}</span>
                            </li>
                        </ol>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
