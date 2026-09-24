<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PemindaiQr from '@/components/PemindaiQr.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    formatJamDari,
    formatTanggal,
    infoStatusPresensi,
    jam,
    JENIS_PERTEMUAN,
    statusTampil,
    type JenisPertemuan,
    type StatusPertemuan,
} from '@/lib/presensi';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown, CircleCheck, QrCode, RefreshCw } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type Rekap = { hadir: number; terlambat: number; izin: number; sakit: number; alpa: number; dihitung: number; persen: number | null };
type PertemuanSaya = {
    terlewat?: boolean;
    id: number;
    pertemuan_ke: number;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    jenis: JenisPertemuan;
    status: StatusPertemuan;
    topik: string | null;
    presensi: { status: string; waktu_presensi: string | null; metode: string; keterangan: string | null } | null;
    pengajuan: { jenis: string; alasan: string; status: 'menunggu' | 'disetujui' | 'ditolak'; catatan_dosen: string | null } | null;
    bisa_ajukan_izin: boolean;
};
type SyaratJenis = { persen: number | null; hadir: number; dihitung: number; memenuhi: boolean | null; dispensasi: { alasan: string } | null } | null;
type JadwalUjian = { pertemuan_ke: number; tanggal: string; jam_mulai: string; jam_akhir: string; ruang: string | null } | null;
type Kelas = {
    id: number;
    kode_kelas: string;
    nama_matkul: string | null;
    kode_matkul: string | null;
    dosen: string | null;
    rekap: Rekap | null;
    rencana: number;
    sisa_absen: number;
    ujian: { aktif: boolean; uts: JadwalUjian; uas: JadwalUjian; syarat: { uts: SyaratJenis; uas: SyaratJenis } | null };
    pertemuan: PertemuanSaya[];
};
type Terbuka = {
    id: number;
    pertemuan_ke: number;
    jam_mulai: string;
    jam_akhir: string;
    kode_kelas: string;
    nama_matkul: string;
    status_saya: string | null;
};

const props = defineProps<{
    tahunAkademiks: { id: number; name: string }[];
    tahunAkademikId: number | null;
    minKehadiran: number;
    batasIzinHari: number;
    terbuka: Terbuka[];
    kelas: Kelas[];
}>();

const page = usePage<{ flash?: { success?: string } }>();
const sudahHadir = (status: string | null) => status === 'hadir' || status === 'terlambat';

// Tiap kelas yang membuka presensi punya kolom PIN sendiri; pesan kesalahan tampil di kelas yang dikirim.
const form = useForm({ pertemuan_id: 0, kode: '' });
const pin = reactive<Record<number, string>>({});
const aktif = ref<number | null>(null);
const kirim = (pertemuanId: number) => {
    aktif.value = pertemuanId;
    form.pertemuan_id = pertemuanId;
    form.kode = pin[pertemuanId] ?? '';
    form.post(route('mahasiswa.presensi.check-in'), { preserveScroll: true, onSuccess: () => (pin[pertemuanId] = '') });
};

// Pindai QR dari dalam aplikasi: isi QR adalah tautan halaman konfirmasi, jadi pertemuan dan kodenya
// diambil dari tautan itu lalu langsung dikirim (lebih cepat daripada membuka halaman konfirmasi).
const pindaiTerbuka = ref(false);
const pindaiKe = ref(0);
const pindaiPesan = ref('');
const pemindai = ref<InstanceType<typeof PemindaiQr> | null>(null);
const pindaiForm = useForm({ pertemuan_id: 0, kode: '' });

const bukaPindai = () => {
    pindaiForm.clearErrors();
    pindaiPesan.value = '';
    pindaiKe.value++;
    pindaiTerbuka.value = true;
};
const tutupPindai = () => {
    pemindai.value?.berhenti();
    pindaiTerbuka.value = false;
};
const saatTerpindai = (teks: string) => {
    if (pindaiForm.processing || pindaiPesan.value || pindaiForm.hasErrors) return;

    let url: URL;
    try {
        url = new URL(teks);
    } catch {
        pindaiPesan.value = 'QR ini bukan QR presensi.';
        return;
    }
    // Alamat host tidak dibandingkan: dosen dan mahasiswa bisa membuka SIA lewat nama domain/IP berbeda.
    // Pertemuan dan kode tetap diperiksa server.
    const cocok = url.pathname.match(/\/mahasiswa\/presensi\/masuk\/(\d+)\/?$/);
    const kode = url.searchParams.get('k');
    if (!cocok || !kode) {
        pindaiPesan.value = 'QR ini bukan QR presensi.';
        return;
    }

    pemindai.value?.berhenti();
    pindaiForm.pertemuan_id = Number(cocok[1]);
    pindaiForm.kode = kode;
    pindaiForm.post(route('mahasiswa.presensi.check-in'), { preserveScroll: true, onSuccess: () => (pindaiTerbuka.value = false) });
};

// Pengajuan izin/sakit untuk satu pertemuan.
const izinUntuk = ref<(PertemuanSaya & { nama_matkul: string | null }) | null>(null);
const izinForm = useForm({ pertemuan_id: 0, jenis: 'izin', alasan: '', lampiran: [] as File[] });
const bukaIzin = (p: PertemuanSaya, k: Kelas) => {
    izinUntuk.value = { ...p, nama_matkul: k.nama_matkul };
    izinForm.reset();
    izinForm.clearErrors();
    izinForm.pertemuan_id = p.id;
};
const pilihLampiran = (event: Event) => (izinForm.lampiran = Array.from((event.target as HTMLInputElement).files ?? []).slice(0, 3));
const kirimIzin = () =>
    izinForm.post(route('mahasiswa.presensi.izin'), { preserveScroll: true, forceFormData: true, onSuccess: () => (izinUntuk.value = null) });
const errorLampiran = computed(() => Object.entries(izinForm.errors).find(([k]) => k.startsWith('lampiran'))?.[1]);
const labelPengajuan = { menunggu: 'menunggu persetujuan', disetujui: 'disetujui', ditolak: 'ditolak' } as const;

const muatUlang = () => router.reload({ only: ['terbuka'] });
const gantiTahun = (event: Event) =>
    router.get(route('mahasiswa.presensi'), { tahun_akademik_id: (event.target as HTMLSelectElement).value }, { preserveScroll: true });

const terbukaKelas = ref<number | null>(null);
const dibawahBatas = (rekap: Rekap | null) => rekap?.persen != null && rekap.persen < props.minKehadiran;
</script>

<template>
    <Head title="Presensi" />
    <AppLayout :breadcrumbs="[{ title: 'Presensi', href: route('mahasiswa.presensi') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Presensi</h1>
                    <p class="text-sm text-[#615d59]">
                        Pindai QR di layar kelas (tombol Pindai QR atau aplikasi kamera HP), atau ketik PIN dari dosen. Minimal kehadiran untuk ujian
                        {{ props.minKehadiran }}%; izin dan sakit dihitung tidak hadir.
                    </p>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="flex items-center gap-2 rounded-xl border border-[#b7e4c2] bg-[#e8f7ec] px-4 py-3 text-sm text-[#1a7f37]"
                    role="alert"
                >
                    <CircleCheck class="size-4 shrink-0" /> {{ page.props.flash.success }}
                </div>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Presensi sekarang</h2>
                        <Button type="button" variant="ghost" size="sm" @click="muatUlang"><RefreshCw class="mr-1 size-4" /> Muat ulang</Button>
                    </div>

                    <Button
                        type="button"
                        class="mt-3 h-12 w-full rounded-full bg-[#0075de] text-base text-white hover:bg-[#005bab] sm:w-auto sm:px-8"
                        @click="bukaPindai"
                    >
                        <QrCode class="mr-2 size-5" /> Pindai QR
                    </Button>

                    <div v-if="props.terbuka.length" class="mt-3 flex flex-col gap-3">
                        <div v-for="item in props.terbuka" :key="item.id" class="rounded-lg border border-[#e6e6e6] p-4">
                            <p class="font-medium text-black">{{ item.nama_matkul }}</p>
                            <p class="text-xs text-[#a39e98]">
                                {{ item.kode_kelas }} · Pertemuan {{ item.pertemuan_ke }} · {{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }}
                            </p>
                            <p v-if="sudahHadir(item.status_saya)" class="mt-3 flex items-center gap-1.5 text-sm font-medium text-[#1a7f37]">
                                <CircleCheck class="size-4" /> Anda sudah tercatat
                                {{ infoStatusPresensi(item.status_saya ?? '')?.label.toLowerCase() }}.
                            </p>
                            <form v-else class="mt-3 flex flex-wrap items-start gap-2" @submit.prevent="kirim(item.id)">
                                <div class="grid gap-1">
                                    <Input
                                        v-model="pin[item.id]"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        maxlength="6"
                                        placeholder="PIN 6 angka"
                                        :aria-label="`PIN presensi ${item.nama_matkul}`"
                                        class="h-11 w-44 rounded-lg border-[#dddddd] text-center font-mono text-lg tracking-[0.3em]"
                                    />
                                    <InputError v-if="aktif === item.id" :message="form.errors.kode" />
                                </div>
                                <Button
                                    type="submit"
                                    class="h-11 bg-[#0075de] px-5 text-white hover:bg-[#005bab]"
                                    :disabled="form.processing || (pin[item.id] ?? '').length < 6"
                                >
                                    Hadir
                                </Button>
                            </form>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-[#615d59]">
                        Belum ada kelas yang membuka presensi. Tunggu dosen membuka presensi mandiri, lalu tekan Muat ulang.
                    </p>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-black">Riwayat kehadiran</h2>
                    <select
                        v-if="props.tahunAkademiks.length"
                        :value="props.tahunAkademikId ?? ''"
                        aria-label="Tahun akademik"
                        class="h-10 rounded-lg border border-[#dddddd] bg-white px-3 text-sm"
                        @change="gantiTahun"
                    >
                        <option v-for="t in props.tahunAkademiks" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <div v-for="k in props.kelas" :key="k.id" class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <button
                        type="button"
                        class="flex w-full flex-wrap items-center gap-3 p-4 text-left hover:bg-[#f6f5f4]/60"
                        :aria-expanded="terbukaKelas === k.id"
                        @click="terbukaKelas = terbukaKelas === k.id ? null : k.id"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-black">{{ k.nama_matkul }}</p>
                            <p class="text-xs text-[#a39e98]">{{ k.kode_matkul }} · {{ k.kode_kelas }} · {{ k.dosen ?? '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xl font-bold" :class="dibawahBatas(k.rekap) ? 'text-[#b42318]' : 'text-black'">
                                {{ k.rekap?.persen != null ? `${k.rekap.persen}%` : '-' }}
                            </p>
                            <p class="text-xs text-[#a39e98]">
                                {{ k.rekap ? `${k.rekap.hadir + k.rekap.terlambat}/${k.rekap.dihitung} hadir` : 'belum ada pertemuan' }}
                            </p>
                        </div>
                        <ChevronDown class="size-4 text-[#a39e98] transition-transform" :class="terbukaKelas === k.id ? 'rotate-180' : ''" />
                        <p
                            v-if="k.rekap && k.rencana"
                            class="w-full text-xs"
                            :class="k.sisa_absen < 0 ? 'text-[#b42318]' : k.sisa_absen <= 1 ? 'text-[#dd5b00]' : 'text-[#615d59]'"
                        >
                            {{
                                k.sisa_absen < 0
                                    ? `Absen melebihi batas ${-k.sisa_absen} pertemuan; kehadiran tidak lagi bisa mencapai ${props.minKehadiran}%.`
                                    : `Sisa boleh tidak hadir: ${k.sisa_absen} dari ${k.rencana} pertemuan kuliah.`
                            }}
                        </p>
                    </button>

                    <div v-if="k.ujian.uts || k.ujian.uas" class="grid gap-2 border-t border-[#e6e6e6] px-4 py-3 text-sm sm:grid-cols-2">
                        <div v-for="j in ['uts', 'uas'] as const" :key="j">
                            <template v-if="k.ujian[j]">
                                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">{{ JENIS_PERTEMUAN[j] }}</p>
                                <p class="text-[#31302e]">
                                    {{ formatTanggal(k.ujian[j]!.tanggal) }} · {{ jam(k.ujian[j]!.jam_mulai) }}–{{ jam(k.ujian[j]!.jam_akhir) }}
                                    <template v-if="k.ujian[j]!.ruang"> · {{ k.ujian[j]!.ruang }}</template>
                                </p>
                                <p v-if="k.ujian.syarat?.[j]?.dispensasi" class="text-xs text-[#0b62b5]">Anda mendapat dispensasi untuk ujian ini.</p>
                                <p v-else-if="k.ujian.syarat?.[j]?.memenuhi === false" class="text-xs font-medium text-[#b42318]">
                                    Belum memenuhi syarat kehadiran ({{ k.ujian.syarat[j]!.persen }}% dari minimal {{ props.minKehadiran }}%).
                                </p>
                                <p v-else-if="k.ujian.syarat?.[j]?.memenuhi" class="text-xs text-[#1a7f37]">Memenuhi syarat kehadiran.</p>
                            </template>
                        </div>
                    </div>

                    <div v-if="terbukaKelas === k.id" class="border-t border-[#e6e6e6]">
                        <ul class="divide-y divide-[#e6e6e6]">
                            <li v-for="p in k.pertemuan" :key="p.id" class="flex flex-wrap items-start gap-3 px-4 py-3 text-sm">
                                <span class="w-8 shrink-0 font-medium text-black">{{ p.pertemuan_ke }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[#31302e]">
                                        {{ formatTanggal(p.tanggal) }} · {{ jam(p.jam_mulai) }}–{{ jam(p.jam_akhir) }}
                                        <span
                                            v-if="p.jenis !== 'kuliah'"
                                            class="ml-1 rounded bg-[#fff6e0] px-1.5 text-xs font-semibold text-[#8a5a00]"
                                            >{{ JENIS_PERTEMUAN[p.jenis] }}</span
                                        >
                                    </p>
                                    <p v-if="p.topik" class="text-xs text-[#a39e98]">{{ p.topik }}</p>
                                    <p v-if="p.presensi?.keterangan" class="text-xs text-[#a39e98]">Keterangan: {{ p.presensi.keterangan }}</p>
                                    <p
                                        v-if="p.pengajuan"
                                        class="text-xs"
                                        :class="p.pengajuan.status === 'ditolak' ? 'text-[#dd5b00]' : 'text-[#615d59]'"
                                    >
                                        Pengajuan {{ p.pengajuan.jenis }} {{ labelPengajuan[p.pengajuan.status]
                                        }}<template v-if="p.pengajuan.catatan_dosen">: {{ p.pengajuan.catatan_dosen }}</template>
                                    </p>
                                    <button
                                        v-if="p.bisa_ajukan_izin"
                                        type="button"
                                        class="mt-1 text-xs font-medium text-[#0075de] hover:underline"
                                        @click="bukaIzin(p, k)"
                                    >
                                        {{ p.pengajuan?.status === 'ditolak' ? 'Ajukan ulang izin/sakit' : 'Ajukan izin/sakit' }}
                                    </button>
                                </div>
                                <span
                                    v-if="p.presensi"
                                    class="rounded border px-2 py-0.5 text-xs font-medium"
                                    :class="infoStatusPresensi(p.presensi.status)?.kelas"
                                    :title="p.presensi.waktu_presensi ? `Pukul ${formatJamDari(p.presensi.waktu_presensi)}` : undefined"
                                    >{{ infoStatusPresensi(p.presensi.status)?.label }}</span
                                >
                                <span v-else class="rounded px-2 py-0.5 text-xs" :class="statusTampil(p).kelas">{{ statusTampil(p).label }}</span>
                            </li>
                            <li v-if="!k.pertemuan.length" class="px-4 py-6 text-center text-sm text-[#615d59]">Pertemuan belum dijadwalkan.</li>
                        </ul>
                    </div>
                </div>

                <p
                    v-if="!props.kelas.length"
                    class="rounded-xl border border-dashed border-[#e6e6e6] bg-white px-4 py-10 text-center text-sm text-[#615d59]"
                >
                    Belum ada kelas di KRS Anda untuk tahun akademik ini.
                </p>

                <!-- Pengajuan izin/sakit -->
                <div v-if="izinUntuk" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="izinUntuk = null">
                    <form
                        class="flex max-h-[calc(100vh-2rem)] w-full max-w-md flex-col gap-4 overflow-y-auto rounded-xl bg-white p-6"
                        @submit.prevent="kirimIzin"
                    >
                        <div>
                            <h2 class="text-lg font-semibold text-black">Ajukan izin/sakit</h2>
                            <p class="mt-1 text-sm text-[#615d59]">
                                {{ izinUntuk.nama_matkul }} · pertemuan {{ izinUntuk.pertemuan_ke }} ({{ formatTanggal(izinUntuk.tanggal) }}).
                                Diperiksa dosen pengampu; paling lambat {{ props.batasIzinHari }} hari sesudah pertemuan. Izin dan sakit tetap
                                dihitung tidak hadir.
                            </p>
                        </div>
                        <div class="flex gap-4 text-sm">
                            <label v-for="j in ['izin', 'sakit']" :key="j" class="flex items-center gap-2">
                                <input v-model="izinForm.jenis" type="radio" :value="j" class="size-4 accent-[#0075de]" />
                                {{ j === 'izin' ? 'Izin' : 'Sakit' }}
                            </label>
                        </div>
                        <div class="grid gap-1.5">
                            <label for="alasan_izin" class="text-sm font-medium text-black">Alasan</label>
                            <textarea
                                id="alasan_izin"
                                v-model="izinForm.alasan"
                                rows="3"
                                maxlength="1000"
                                required
                                class="rounded-[4px] border border-[#dddddd] px-3 py-2 text-[15px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                            />
                            <InputError :message="izinForm.errors.alasan ?? izinForm.errors.pertemuan_id" />
                        </div>
                        <div class="grid gap-1.5">
                            <label for="lampiran_izin" class="text-sm font-medium text-black">Lampiran (opsional)</label>
                            <input id="lampiran_izin" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm" @change="pilihLampiran" />
                            <p class="text-xs text-[#a39e98]">Mis. surat dokter. PDF/JPG/PNG, maksimal 3 berkas @5 MB.</p>
                            <InputError :message="errorLampiran" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="izinUntuk = null">Batal</Button>
                            <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="izinForm.processing">Kirim</Button>
                        </div>
                    </form>
                </div>

                <!-- Pemindai QR -->
                <div v-if="pindaiTerbuka" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="tutupPindai">
                    <div class="flex w-full max-w-sm flex-col gap-3 rounded-xl bg-white p-5">
                        <div>
                            <h2 class="text-lg font-semibold text-black">Pindai QR presensi</h2>
                            <p class="text-sm text-[#615d59]">Arahkan kamera ke QR di layar kelas.</p>
                        </div>
                        <PemindaiQr v-if="!pindaiForm.hasErrors" :key="pindaiKe" ref="pemindai" @hasil="saatTerpindai" />
                        <p v-if="pindaiForm.processing" class="text-center text-sm text-[#615d59]">Mencatat presensi…</p>
                        <p v-if="pindaiPesan" class="text-sm text-[#dd5b00]">{{ pindaiPesan }}</p>
                        <InputError :message="pindaiForm.errors.kode ?? pindaiForm.errors.pertemuan_id" />
                        <div class="flex justify-end gap-2">
                            <Button v-if="pindaiPesan || pindaiForm.hasErrors" type="button" variant="outline" @click="bukaPindai"
                                >Pindai ulang</Button
                            >
                            <Button type="button" variant="outline" @click="tutupPindai">Tutup</Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
