<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import {
    JENIS_PENGAJUAN,
    LABEL_LAMPIRAN,
    STATUS_PENGAJUAN,
    type JenisPengajuan,
    type KeadaanForm,
    type StatusPengajuan,
    type Syarat,
} from '@/lib/tugasAkhir';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, CircleX, Lock, Paperclip } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type IsianTa = {
    judul: string;
    bidang: string;
    ringkasan: string;
    usulan_pembimbing_1_id: number | null;
    usulan_pembimbing_2_id: number | null;
};
type Pengajuan = {
    id: number;
    status: StatusPengajuan;
    isian: IsianTa;
    lampiran: string[];
    catatan: string | null;
    diajukan_at: string | null;
    diproses_at: string | null;
};
type Riwayat = {
    id: number;
    jenis: JenisPengajuan;
    status: StatusPengajuan;
    diajukan_at: string | null;
    riwayat: { status: StatusPengajuan; catatan: string | null; oleh: string | null; waktu: string | null }[];
};

const props = defineProps<{
    tugasAkhir: { judul: string; bidang: string; pembimbing: string[]; status: 'berjalan' | 'selesai'; disahkan_at: string | null } | null;
    pengajuanTa: { keadaan: KeadaanForm; syarat: Syarat[]; pengajuan: Pengajuan | null };
    riwayat: Riwayat[];
    dosenOptions: { id: number; name: string }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const keadaan = computed(() => props.pengajuanTa.keadaan);
const pengajuan = computed(() => props.pengajuanTa.pengajuan);
// Form hanya bisa diisi saat pengajuan baru atau perbaikan; selain itu tampil terkunci.
const formTerbuka = computed(() => keadaan.value === 'baru' || keadaan.value === 'perbaikan');
const isianAwal = keadaan.value === 'perbaikan' || keadaan.value === 'menunggu' ? pengajuan.value?.isian : undefined;

const form = useForm({
    judul: isianAwal?.judul ?? '',
    bidang: isianAwal?.bidang ?? '',
    ringkasan: isianAwal?.ringkasan ?? '',
    usulan_pembimbing_1_id: isianAwal?.usulan_pembimbing_1_id ?? (null as number | null),
    usulan_pembimbing_2_id: isianAwal?.usulan_pembimbing_2_id ?? (null as number | null),
    proposal: null as File | null,
});
const inputProposal = ref<HTMLInputElement | null>(null);

const kirim = () =>
    form.post(route('mahasiswa.tugas-akhir.ajukan-ta'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.proposal = null;
            if (inputProposal.value) inputProposal.value.value = '';
        },
    });

const tahapan = computed(() => [
    { no: 1, judul: 'Tugas Akhir', keterangan: props.tugasAkhir ? 'Disahkan' : keteranganKeadaan[keadaan.value], aktif: true },
    { no: 2, judul: 'Pendadaran', keterangan: 'Terbuka setelah tugas akhir disahkan', aktif: false },
    { no: 3, judul: 'Wisuda', keterangan: 'Terbuka setelah lulus pendadaran', aktif: false },
]);
const keteranganKeadaan: Record<KeadaanForm, string> = {
    selesai: 'Disahkan',
    menunggu: 'Menunggu diproses admin',
    perbaikan: 'Perlu perbaikan',
    baru: 'Silakan ajukan',
    belum_memenuhi: 'Belum memenuhi syarat',
};

const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
const area =
    'min-h-28 w-full rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] disabled:cursor-not-allowed disabled:opacity-60';
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

                    <ul class="mt-4 grid gap-2">
                        <li v-for="s in props.pengajuanTa.syarat" :key="s.label" class="flex items-start gap-2 text-sm">
                            <CheckCircle2 v-if="s.terpenuhi" class="mt-0.5 size-4 shrink-0 text-[#1aae39]" />
                            <CircleX v-else class="mt-0.5 size-4 shrink-0 text-[#dd5b00]" />
                            <span>
                                <span class="text-black">{{ s.label }}</span>
                                <span v-if="s.keterangan" class="block text-xs text-[#615d59]">{{ s.keterangan }}</span>
                            </span>
                        </li>
                    </ul>

                    <div
                        v-if="keadaan === 'belum_memenuhi'"
                        class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]"
                        role="status"
                    >
                        Anda belum memenuhi syarat pengajuan tugas akhir. Form terbuka setelah semua syarat di atas terpenuhi.
                    </div>
                    <div
                        v-else-if="keadaan === 'menunggu'"
                        class="mt-4 rounded-lg border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]"
                        role="status"
                    >
                        Pengajuan dikirim {{ formatTanggal(pengajuan?.diajukan_at, false) }} dan sedang menunggu diproses admin. Form terbuka lagi
                        setelah admin memberi keputusan.
                    </div>
                    <div
                        v-else-if="keadaan === 'perbaikan'"
                        class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]"
                        role="status"
                    >
                        <span class="font-medium">Admin meminta perbaikan:</span> {{ pengajuan?.catatan }}
                    </div>
                    <div
                        v-else-if="pengajuan?.status === 'ditolak'"
                        class="mt-4 rounded-lg border border-[#f3c5c0] bg-[#fdecea] px-4 py-3 text-sm text-[#b42318]"
                        role="status"
                    >
                        <span class="font-medium">Pengajuan sebelumnya ditolak:</span> {{ pengajuan.catatan }} Anda bisa mengajukan lagi.
                    </div>

                    <form class="mt-5" @submit.prevent="kirim">
                        <fieldset :disabled="!formTerbuka || form.processing" class="grid gap-4 disabled:opacity-60">
                            <div class="grid gap-2">
                                <Label for="judul">Judul</Label>
                                <Input id="judul" v-model="form.judul" :class="inp" maxlength="300" required />
                                <InputError :message="form.errors.judul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bidang">Bidang / topik</Label>
                                <Input id="bidang" v-model="form.bidang" :class="inp" maxlength="150" placeholder="Mis. Sistem Informasi" required />
                                <InputError :message="form.errors.bidang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ringkasan">Ringkasan proposal</Label>
                                <textarea
                                    id="ringkasan"
                                    v-model="form.ringkasan"
                                    :class="area"
                                    maxlength="5000"
                                    placeholder="Latar belakang, rumusan masalah, dan metode singkat"
                                    required
                                />
                                <InputError :message="form.errors.ringkasan" />
                            </div>
                            <div class="grid items-start gap-4 sm:grid-cols-2">
                                <div class="grid content-start gap-2">
                                    <Label for="usulan_pembimbing_1_id">Usulan pembimbing 1</Label>
                                    <SearchSelect
                                        id="usulan_pembimbing_1_id"
                                        v-model="form.usulan_pembimbing_1_id"
                                        :options="props.dosenOptions"
                                        placeholder="Pilih dosen"
                                        search-placeholder="Cari dosen"
                                        required
                                    />
                                    <InputError :message="form.errors.usulan_pembimbing_1_id" />
                                </div>
                                <div class="grid content-start gap-2">
                                    <Label for="usulan_pembimbing_2_id" class="flex items-center justify-between">
                                        <span>Usulan pembimbing 2 <span class="font-normal text-[#a39e98]">(opsional)</span></span>
                                        <button
                                            v-if="form.usulan_pembimbing_2_id && formTerbuka"
                                            type="button"
                                            class="text-xs font-normal text-[#0075de] hover:underline"
                                            @click="form.usulan_pembimbing_2_id = null"
                                        >
                                            Kosongkan
                                        </button>
                                    </Label>
                                    <SearchSelect
                                        id="usulan_pembimbing_2_id"
                                        v-model="form.usulan_pembimbing_2_id"
                                        :options="props.dosenOptions"
                                        placeholder="Tanpa pembimbing 2"
                                        search-placeholder="Cari dosen"
                                    />
                                    <InputError :message="form.errors.usulan_pembimbing_2_id" />
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <Label for="proposal">Proposal (PDF, maks. 10 MB)</Label>
                                <a
                                    v-if="pengajuan && isianAwal && pengajuan.lampiran.includes('proposal')"
                                    :href="route('berkas.pengajuan-akademik', [pengajuan.id, 'proposal'])"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-sm text-[#0075de] hover:underline"
                                    ><Paperclip class="size-3.5" /> {{ LABEL_LAMPIRAN.proposal }} yang sudah dikirim</a
                                >
                                <input
                                    id="proposal"
                                    ref="inputProposal"
                                    type="file"
                                    accept="application/pdf,.pdf"
                                    class="text-sm file:mr-3 file:rounded-full file:border-0 file:bg-[#f2f9ff] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[#0075de]"
                                    :required="keadaan === 'baru'"
                                    @change="form.proposal = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                />
                                <span v-if="keadaan === 'perbaikan'" class="text-xs text-[#615d59]"
                                    >Kosongkan bila proposal tidak perlu diganti.</span
                                >
                                <InputError :message="form.errors.proposal" />
                            </div>
                            <div v-if="formTerbuka" class="flex justify-end">
                                <Button type="submit" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">
                                    {{ keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Kirim Pengajuan' }}
                                </Button>
                            </div>
                        </fieldset>
                    </form>
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
                                ·
                                {{ h.status === 'menunggu' ? (i === 0 ? 'Dikirim' : 'Dikirim ulang') : STATUS_PENGAJUAN[h.status].label }}
                                <span v-if="h.status !== 'menunggu' && h.oleh" class="text-[#615d59]">oleh {{ h.oleh }}</span>
                                <span v-if="h.catatan" class="block text-xs text-[#615d59]">{{ h.catatan }}</span>
                            </li>
                        </ol>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
