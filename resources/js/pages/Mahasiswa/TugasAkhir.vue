<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import DaftarSyarat from '@/components/tugas-akhir/DaftarSyarat.vue';
import InputBerkas from '@/components/tugas-akhir/InputBerkas.vue';
import KartuJadwal from '@/components/tugas-akhir/KartuJadwal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import {
    JENIS_PENGAJUAN,
    STATUS_PENGAJUAN,
    labelPeristiwa,
    type JadwalPendadaran,
    type JenisPengajuan,
    type KeadaanForm,
    type StatusPengajuan,
    type Syarat,
} from '@/lib/tugasAkhir';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
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
    tugasAkhir: { judul: string; bidang: string; pembimbing: string[]; status: 'berjalan' | 'selesai'; disahkan_at: string | null } | null;
    pengajuanTa: Tahap;
    pendaftaranPendadaran: Tahap & { jadwal: JadwalPendadaran | null };
    riwayat: Riwayat[];
    dosenOptions: { id: number; name: string }[];
}>();

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
    menunggu: 'Sedang diproses',
    perbaikan: 'Perlu perbaikan',
    baru: 'Silakan ajukan',
    belum_memenuhi: 'Belum memenuhi syarat',
};
const tahapan = computed(() => [
    { no: 1, judul: 'Tugas Akhir', keterangan: props.tugasAkhir ? 'Disahkan' : keterangan[ta.value.keadaan], aktif: true },
    { no: 2, judul: 'Pendadaran', keterangan: keterangan[pd.value.keadaan], aktif: pd.value.keadaan !== 'terkunci' },
    { no: 3, judul: 'Wisuda', keterangan: 'Terbuka setelah lulus pendadaran', aktif: false },
]);

const menunggu = (t: Tahap) => (t.pengajuan?.status === 'menunggu_pembimbing' ? 'menunggu persetujuan pembimbing' : 'menunggu diproses admin');

const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
const area =
    'min-h-28 w-full rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
const dokumen = 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png';
</script>

<template>
    <Head title="Tugas Akhir & Wisuda" />
    <AppLayout :breadcrumbs="[{ title: 'Tugas Akhir & Wisuda', href: route('mahasiswa.tugas-akhir') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Tugas Akhir & Wisuda</h1>
                    <p class="text-sm text-[#615d59]">Ajukan tugas akhir, daftar pendadaran, lalu daftar wisuda secara berurutan.</p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <ol class="grid gap-3 sm:grid-cols-3">
                    <li
                        v-for="t in tahapan"
                        :key="t.no"
                        class="flex items-start gap-3 rounded-xl border bg-white p-4"
                        :class="t.aktif ? 'border-[#0075de]/40' : 'border-[#e6e6e6] opacity-70'"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="t.aktif ? 'bg-[#0075de] text-white' : 'bg-[#f6f5f4] text-[#a39e98]'"
                            >{{ t.no }}</span
                        >
                        <span class="grid gap-0.5">
                            <span class="flex items-center gap-1.5 font-medium text-black"
                                >{{ t.judul }} <Lock v-if="!t.aktif" class="size-3.5 text-[#a39e98]"
                            /></span>
                            <span class="text-xs text-[#615d59]">{{ t.keterangan }}</span>
                        </span>
                    </li>
                </ol>

                <!-- Tahap 1: tugas akhir -->
                <section v-if="props.tugasAkhir" class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tugas Akhir Anda</h2>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="props.tugasAkhir.status === 'selesai' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f2f9ff] text-[#0075de]'"
                            >{{ props.tugasAkhir.status === 'selesai' ? 'Selesai' : 'Berjalan' }}</span
                        >
                    </div>
                    <p class="mt-3 text-lg font-semibold leading-snug text-black">{{ props.tugasAkhir.judul }}</p>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs uppercase tracking-[0.04em] text-[#a39e98]">Bidang</dt>
                            <dd class="mt-1 text-black">{{ props.tugasAkhir.bidang }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.04em] text-[#a39e98]">Pembimbing</dt>
                            <dd v-for="(nama, i) in props.tugasAkhir.pembimbing" :key="nama" class="mt-1 text-black">{{ i + 1 }}. {{ nama }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-[0.04em] text-[#a39e98]">Disahkan</dt>
                            <dd class="mt-1 text-black">{{ formatTanggal(props.tugasAkhir.disahkan_at, false) }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-else class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tahap 1 · Pengajuan Tugas Akhir/Skripsi</h2>
                    <DaftarSyarat class="mt-4" :syarat="ta.syarat" />
                    <div
                        v-if="ta.keadaan === 'belum_memenuhi'"
                        class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]"
                        role="status"
                    >
                        Anda belum memenuhi syarat pengajuan tugas akhir. Form terbuka setelah semua syarat di atas terpenuhi.
                    </div>
                    <div
                        v-else-if="ta.keadaan === 'menunggu'"
                        class="mt-4 rounded-lg border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]"
                        role="status"
                    >
                        Pengajuan dikirim {{ formatTanggal(ta.pengajuan?.diajukan_at, false) }} dan sedang {{ menunggu(ta) }}. Form terbuka lagi
                        setelah ada keputusan.
                    </div>
                    <div
                        v-else-if="ta.keadaan === 'perbaikan'"
                        class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]"
                        role="status"
                    >
                        <span class="font-medium">Diminta perbaikan:</span> {{ ta.pengajuan?.catatan }}
                    </div>
                    <div
                        v-else-if="ta.pengajuan?.status === 'ditolak'"
                        class="mt-4 rounded-lg border border-[#f3c5c0] bg-[#fdecea] px-4 py-3 text-sm text-[#b42318]"
                        role="status"
                    >
                        <span class="font-medium">Pengajuan sebelumnya ditolak:</span> {{ ta.pengajuan.catatan }} Anda bisa mengajukan lagi.
                    </div>

                    <form class="mt-5" @submit.prevent="kirimTa">
                        <fieldset :disabled="!terbuka(ta) || formTa.processing" class="grid gap-4 disabled:opacity-60">
                            <div class="grid gap-2">
                                <Label for="judul">Judul</Label>
                                <Input id="judul" v-model="formTa.judul" :class="inp" maxlength="300" required />
                                <InputError :message="formTa.errors.judul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bidang">Bidang / topik</Label>
                                <Input
                                    id="bidang"
                                    v-model="formTa.bidang"
                                    :class="inp"
                                    maxlength="150"
                                    placeholder="Mis. Sistem Informasi"
                                    required
                                />
                                <InputError :message="formTa.errors.bidang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ringkasan">Ringkasan proposal</Label>
                                <textarea
                                    id="ringkasan"
                                    v-model="formTa.ringkasan"
                                    :class="area"
                                    maxlength="5000"
                                    placeholder="Latar belakang, rumusan masalah, dan metode singkat"
                                    required
                                />
                                <InputError :message="formTa.errors.ringkasan" />
                            </div>
                            <div class="grid items-start gap-4 sm:grid-cols-2">
                                <div class="grid content-start gap-2">
                                    <Label for="usulan_pembimbing_1_id">Usulan pembimbing 1</Label>
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
                                    <Label for="usulan_pembimbing_2_id" class="flex items-center justify-between">
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
                                <Button type="submit" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">
                                    {{ ta.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Kirim Pengajuan' }}
                                </Button>
                            </div>
                        </fieldset>
                    </form>
                </section>

                <!-- Tahap 2: pendadaran -->
                <section v-if="pd.keadaan !== 'terkunci'" class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tahap 2 · Pendadaran</h2>

                    <template v-if="pd.jadwal">
                        <div class="mt-4 rounded-lg border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]" role="status">
                            Pendadaran Anda sudah dijadwalkan. Hadir tepat waktu dan bawa naskah.
                        </div>
                        <KartuJadwal class="mt-4" :jadwal="pd.jadwal" />
                    </template>

                    <template v-else>
                        <DaftarSyarat class="mt-4" :syarat="pd.syarat" />
                        <div
                            v-if="pd.keadaan === 'belum_memenuhi'"
                            class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]"
                            role="status"
                        >
                            Anda belum memenuhi syarat pendaftaran pendadaran. Form terbuka setelah semua syarat di atas terpenuhi.
                        </div>
                        <div
                            v-else-if="pd.keadaan === 'menunggu'"
                            class="mt-4 rounded-lg border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]"
                            role="status"
                        >
                            Pendaftaran dikirim {{ formatTanggal(pd.pengajuan?.diajukan_at, false) }} dan sedang {{ menunggu(pd) }}. Form terbuka lagi
                            setelah ada keputusan.
                        </div>
                        <div
                            v-else-if="pd.keadaan === 'perbaikan'"
                            class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]"
                            role="status"
                        >
                            <span class="font-medium">Diminta perbaikan:</span> {{ pd.pengajuan?.catatan }}
                        </div>
                        <div
                            v-else-if="pd.pengajuan?.status === 'ditolak'"
                            class="mt-4 rounded-lg border border-[#f3c5c0] bg-[#fdecea] px-4 py-3 text-sm text-[#b42318]"
                            role="status"
                        >
                            <span class="font-medium">Pendaftaran sebelumnya ditolak:</span> {{ pd.pengajuan.catatan }} Anda bisa mendaftar lagi.
                        </div>

                        <form class="mt-5" @submit.prevent="kirimPd">
                            <fieldset :disabled="!terbuka(pd) || formPd.processing" class="grid gap-4 disabled:opacity-60">
                                <div class="grid gap-2">
                                    <Label for="judul_final">Judul final</Label>
                                    <Input id="judul_final" v-model="formPd.judul" :class="inp" maxlength="300" required />
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
                                        label="Bukti bayar pendadaran"
                                        :accept="dokumen"
                                        :pengajuan-id="pd.pengajuan?.id"
                                        :sudah-ada="sudahAda(pd, 'bukti_bayar')"
                                        :wajib="pd.keadaan === 'baru'"
                                        :terkunci="!terbuka(pd)"
                                        :error="formPd.errors.bukti_bayar"
                                        @pilih="formPd.bukti_bayar = $event"
                                    />
                                </div>
                                <p class="text-xs text-[#615d59]">
                                    Pendaftaran diperiksa salah satu pembimbing dulu, lalu admin menjadwalkan pendadaran dan menetapkan tiga penguji.
                                </p>
                                <div v-if="terbuka(pd)" class="flex justify-end">
                                    <Button type="submit" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">
                                        {{ pd.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Daftar Pendadaran' }}
                                    </Button>
                                </div>
                            </fieldset>
                        </form>
                    </template>
                </section>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Riwayat Pengajuan</h2>
                    <p v-if="!props.riwayat.length" class="mt-3 text-sm text-[#615d59]">Belum ada pengajuan.</p>
                    <div v-for="r in props.riwayat" :key="r.id" class="mt-4 border-t border-[#f0efed] pt-4 first:border-0 first:pt-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-black">{{ JENIS_PENGAJUAN[r.jenis] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_PENGAJUAN[r.status].kelas">{{
                                STATUS_PENGAJUAN[r.status].label
                            }}</span>
                        </div>
                        <ol class="mt-2 grid gap-1.5 border-l border-[#e6e6e6] pl-4">
                            <li v-for="(h, i) in r.riwayat" :key="i" class="text-sm text-[#31302e]">
                                <span class="text-xs text-[#a39e98]">{{ formatTanggal(h.waktu, false) }} {{ h.waktu?.slice(11, 16) }}</span>
                                · {{ labelPeristiwa(h.status, i === 0) }}
                                <span v-if="h.status !== 'dikirim' && h.oleh" class="text-[#615d59]">oleh {{ h.oleh }}</span>
                                <span v-if="h.catatan" class="block text-xs text-[#615d59]">{{ h.catatan }}</span>
                            </li>
                        </ol>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
