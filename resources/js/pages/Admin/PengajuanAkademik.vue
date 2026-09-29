<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import ModalJadwalPendadaran from '@/components/tugas-akhir/ModalJadwalPendadaran.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import {
    HASIL_PENDADARAN,
    JENIS_PENGAJUAN,
    LABEL_LAMPIRAN,
    STATUS_PENGAJUAN,
    type HasilPendadaran,
    type JadwalPendadaran,
    type JenisPengajuan,
    type StatusPengajuan,
} from '@/lib/tugasAkhir';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    isian: Record<string, any>;
    usulan_pembimbing: string[];
    lampiran: string[];
    pembimbing: string[];
    disetujui_pembimbing: string | null;
    disetujui_pembimbing_at: string | null;
    jadwal: (JadwalPendadaran & HasilPendadaran) | null;
    periode_wisuda: string | null;
    koreksi: string[];
    status: StatusPengajuan;
    catatan: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    diajukan_at: string | null;
};

const props = defineProps<{
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { jenis: JenisPengajuan; status: string | null; search: string };
    jenisTersedia: JenisPengajuan[];
    jumlahMenunggu: Partial<Record<JenisPengajuan, number>>;
    dosenOptions: { id: number; name: string }[];
    ruangOptions: { id: number; name: string }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'all';
const status = ref<string>(props.filter.status ?? semua);
const search = ref(props.filter.search);
let jeda: number | undefined;

const kirim = () =>
    router.get(
        route('admin.pengajuan-akademik.index'),
        { jenis: props.filter.jenis, status: status.value === semua ? null : status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const terbuka = ref<number | null>(null);

// Setujui TA: admin mengesahkan judul dan menetapkan pembimbing (awalnya diisi dari usulan mahasiswa).
const setujuiItem = ref<Baris | null>(null);
const setujuiForm = useForm({ judul: '', pembimbing_1_id: null as number | null, pembimbing_2_id: null as number | null });
// Pendadaran disetujui sekaligus dijadwalkan lewat modal tersendiri.
const jadwalkanItem = ref<Baris | null>(null);
// Wisuda cukup dikonfirmasi: mahasiswa masuk daftar peserta periode pilihannya.
const wisudaItem = ref<Baris | null>(null);
const setujuiWisuda = () => {
    if (!wisudaItem.value) return;
    router.post(
        route('admin.pengajuan-akademik.setujui', wisudaItem.value.id),
        {},
        { preserveScroll: true, onFinish: () => (wisudaItem.value = null) },
    );
};
const bukaSetujui = (b: Baris) => {
    if (props.filter.jenis === 'pendadaran') {
        jadwalkanItem.value = b;
        return;
    }
    if (props.filter.jenis === 'wisuda') {
        wisudaItem.value = b;
        return;
    }
    setujuiItem.value = b;
    setujuiForm.clearErrors();
    setujuiForm.judul = b.isian.judul ?? '';
    setujuiForm.pembimbing_1_id = b.isian.usulan_pembimbing_1_id ?? null;
    setujuiForm.pembimbing_2_id = b.isian.usulan_pembimbing_2_id ?? null;
};
const setujui = () => {
    if (!setujuiItem.value) return;
    setujuiForm.post(route('admin.pengajuan-akademik.setujui', setujuiItem.value.id), {
        preserveScroll: true,
        onSuccess: () => (setujuiItem.value = null),
    });
};

// Perlu perbaikan dan tolak sama-sama wajib bercatatan yang ditampilkan ke mahasiswa.
const kembalikanItem = ref<{ baris: Baris; aksi: 'perbaikan' | 'tolak' } | null>(null);
const catatanForm = useForm({ catatan: '' });
const bukaKembalikan = (baris: Baris, aksi: 'perbaikan' | 'tolak') => {
    kembalikanItem.value = { baris, aksi };
    catatanForm.reset();
    catatanForm.clearErrors();
};
const kembalikan = () => {
    if (!kembalikanItem.value) return;
    const { baris, aksi } = kembalikanItem.value;
    catatanForm.post(route(`admin.pengajuan-akademik.${aksi}`, baris.id), { preserveScroll: true, onSuccess: () => (kembalikanItem.value = null) });
};
</script>

<template>
    <Head title="Pengajuan TA & Wisuda" />
    <AppLayout :breadcrumbs="[{ title: 'Pengajuan TA & Wisuda', href: route('admin.pengajuan-akademik.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pengajuan TA & Wisuda</h1>
                        <p class="deskripsi-halaman">
                            Setujui, minta perbaikan, atau tolak pengajuan mahasiswa. Selama menunggu keputusan, mahasiswa tidak bisa mengirim
                            pengajuan baru.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <nav class="flex gap-1 overflow-x-auto border-b border-[#e6e6e6] dark:border-border" aria-label="Jenis pengajuan">
                    <Link
                        v-for="j in props.jenisTersedia"
                        :key="j"
                        :href="route('admin.pengajuan-akademik.index', { jenis: j })"
                        class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
                        :class="j === props.filter.jenis ? 'border-[#0075de] text-[#0075de]' : 'border-transparent text-[#615d59] hover:text-black'"
                    >
                        {{ JENIS_PENGAJUAN[j] }}
                        <span v-if="props.jumlahMenunggu[j]" class="rounded-full bg-[#0075de] px-1.5 text-xs text-white">{{
                            props.jumlahMenunggu[j]
                        }}</span>
                    </Link>
                </nav>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="status" label="Filter status" @change="kirim">
                        <option :value="semua">Semua status</option>
                        <option v-for="(s, kunci) in STATUS_PENGAJUAN" :key="kunci" :value="kunci">{{ s.label }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1000px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Pengajuan</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.pengajuan.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.pengajuan.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td class="max-w-[440px]">
                                        <template v-if="props.filter.jenis === 'wisuda'">
                                            <span class="block font-medium text-black">{{ b.periode_wisuda }}</span>
                                            <span class="block text-xs text-[#615d59]"
                                                >Ijazah:
                                                <span :class="{ 'font-medium text-[#dd5b00]': b.koreksi.includes('nama_ijazah') }">{{
                                                    b.isian.nama_ijazah
                                                }}</span
                                                >,
                                                <span :class="{ 'font-medium text-[#dd5b00]': b.koreksi.includes('tempat_lahir') }">{{
                                                    b.isian.tempat_lahir
                                                }}</span
                                                >,
                                                <span :class="{ 'font-medium text-[#dd5b00]': b.koreksi.includes('tanggal_lahir') }">{{
                                                    formatTanggal(b.isian.tanggal_lahir, false)
                                                }}</span>
                                                · toga {{ b.isian.ukuran_toga }}</span
                                            >
                                            <span v-if="b.koreksi.length" class="block text-xs text-[#dd5b00]"
                                                >Berbeda dari profil (koreksi mahasiswa) — periksa sebelum menyetujui.</span
                                            >
                                        </template>
                                        <span v-else class="block font-medium text-black">{{ b.isian.judul }}</span>
                                        <span v-if="b.isian.bidang" class="block text-xs text-[#615d59]">Bidang: {{ b.isian.bidang }}</span>
                                        <span v-if="b.usulan_pembimbing.length" class="block text-xs text-[#615d59]"
                                            >Usulan pembimbing: {{ b.usulan_pembimbing.join(', ') }}</span
                                        >
                                        <span v-if="b.pembimbing.length" class="block text-xs text-[#615d59]"
                                            >Pembimbing: {{ b.pembimbing.join(', ') }}</span
                                        >
                                        <span v-if="b.disetujui_pembimbing" class="block text-xs text-[#1aae39]"
                                            >Disetujui {{ b.disetujui_pembimbing }} · {{ formatTanggal(b.disetujui_pembimbing_at, false) }}</span
                                        >
                                        <button
                                            v-if="b.isian.ringkasan"
                                            type="button"
                                            class="mt-1 text-xs font-medium text-[#0075de] hover:underline"
                                            @click="terbuka = terbuka === b.id ? null : b.id"
                                        >
                                            {{ terbuka === b.id ? 'Sembunyikan ringkasan' : 'Lihat ringkasan' }}
                                        </button>
                                        <span
                                            v-if="terbuka === b.id"
                                            class="mt-1 block whitespace-pre-line rounded-lg bg-[#f6f5f4] p-3 text-sm dark:bg-muted"
                                            >{{ b.isian.ringkasan }}</span
                                        >
                                        <span class="mt-1 flex flex-wrap gap-3">
                                            <a
                                                v-for="k in b.lampiran"
                                                :key="k"
                                                :href="route('berkas.pengajuan-akademik', [b.id, k])"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> {{ LABEL_LAMPIRAN[k] ?? k }}</a
                                            >
                                        </span>
                                        <span class="mt-1 block text-xs text-[#a39e98]">Dikirim {{ formatTanggal(b.diajukan_at, false) }}</span>
                                    </td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_PENGAJUAN[b.status].kelas"
                                            >{{ STATUS_PENGAJUAN[b.status].label }}</span
                                        >
                                        <span v-if="b.catatan" class="mt-1 block max-w-[220px] text-xs text-[#615d59]">{{ b.catatan }}</span>
                                        <span v-if="b.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(b.diproses_at, false) }} · {{ b.diproses_oleh }}</span
                                        >
                                        <span v-if="b.jadwal" class="mt-1 block text-xs text-[#31302e]"
                                            >Pendadaran {{ formatTanggal(b.jadwal.tanggal, false) }}, {{ b.jadwal.jam_mulai }}–{{
                                                b.jadwal.jam_akhir
                                            }}
                                            · {{ b.jadwal.ruang }}</span
                                        >
                                        <span v-if="b.jadwal?.hasil" class="mt-1 block text-xs">
                                            <span class="rounded-full px-2 py-0.5 font-medium" :class="HASIL_PENDADARAN[b.jadwal.hasil].kelas">{{
                                                HASIL_PENDADARAN[b.jadwal.hasil].label
                                            }}</span>
                                            {{ b.jadwal.nilai_akhir }} ({{ b.jadwal.huruf }})
                                        </span>
                                        <a
                                            v-if="b.jadwal?.nomor_surat"
                                            :href="route('berkas.surat-pendadaran', b.jadwal.id)"
                                            target="_blank"
                                            rel="noopener"
                                            class="mt-1 block text-xs font-medium text-[#0075de] hover:underline"
                                            >Surat {{ b.jadwal.nomor_surat }}</a
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <div v-if="b.status === 'menunggu'" class="aksi-tabel">
                                            <Button variant="outline" size="sm" @click="bukaKembalikan(b, 'perbaikan')">Perbaikan</Button>
                                            <Button variant="destructive" size="sm" @click="bukaKembalikan(b, 'tolak')">Tolak</Button>
                                            <Button size="sm" @click="bukaSetujui(b)">Setujui</Button>
                                        </div>
                                        <p v-else-if="b.status === 'menunggu_pembimbing'" class="text-xs text-[#a39e98]">
                                            Menunggu persetujuan pembimbing
                                        </p>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuan.data.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">
                                        Belum ada pengajuan {{ JENIS_PENGAJUAN[props.filter.jenis].toLowerCase() }}.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />
            </div>
        </div>

        <AlertModal
            :open="!!wisudaItem"
            title="Setujui pendaftaran wisuda?"
            :description="
                wisudaItem ? `${wisudaItem.nama} masuk daftar peserta ${wisudaItem.periode_wisuda}. SKL diterbitkan dari menu Periode Wisuda.` : ''
            "
            confirm-text="Setujui"
            cancel-text="Batal"
            @update:open="!$event && (wisudaItem = null)"
            @confirm="setujuiWisuda"
            @cancel="wisudaItem = null"
        />
        <ModalJadwalPendadaran
            v-if="jadwalkanItem"
            :pengajuan="{ id: jadwalkanItem.id, nama: jadwalkanItem.nama, judul: jadwalkanItem.isian.judul, pembimbing: jadwalkanItem.pembimbing }"
            :dosen-options="props.dosenOptions"
            :ruang-options="props.ruangOptions"
            @tutup="jadwalkanItem = null"
        />
        <div v-if="setujuiItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="setujuiItem = null">
            <form class="kartu w-full max-w-lg p-6 shadow-xl" @submit.prevent="setujui">
                <h3 class="judul-bagian">Setujui tugas akhir</h3>
                <p class="mt-1 text-sm text-[#615d59]">Sahkan judul dan tetapkan pembimbing {{ setujuiItem.nama }}. Boleh berbeda dari usulan.</p>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="setujui_judul" class="label-isian">Judul disahkan</Label>
                        <textarea id="setujui_judul" v-model="setujuiForm.judul" maxlength="300" rows="3" class="isian isian-area" required />
                        <InputError :message="setujuiForm.errors.judul" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pembimbing_1_id" class="label-isian">Pembimbing 1</Label>
                        <SearchSelect
                            id="pembimbing_1_id"
                            v-model="setujuiForm.pembimbing_1_id"
                            :options="props.dosenOptions"
                            placeholder="Pilih dosen"
                            search-placeholder="Cari dosen"
                            required
                        />
                        <InputError :message="setujuiForm.errors.pembimbing_1_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pembimbing_2_id" class="label-isian flex items-center justify-between">
                            <span>Pembimbing 2 <span class="font-normal text-[#a39e98]">(opsional)</span></span>
                            <button
                                v-if="setujuiForm.pembimbing_2_id"
                                type="button"
                                class="text-xs font-normal text-[#0075de] hover:underline"
                                @click="setujuiForm.pembimbing_2_id = null"
                            >
                                Kosongkan
                            </button>
                        </Label>
                        <SearchSelect
                            id="pembimbing_2_id"
                            v-model="setujuiForm.pembimbing_2_id"
                            :options="props.dosenOptions"
                            placeholder="Tanpa pembimbing 2"
                            search-placeholder="Cari dosen"
                        />
                        <InputError :message="setujuiForm.errors.pembimbing_2_id" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="setujuiItem = null">Batal</Button>
                    <Button type="submit" :disabled="setujuiForm.processing">Setujui</Button>
                </div>
            </form>
        </div>

        <div v-if="kembalikanItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="kembalikanItem = null">
            <form class="kartu w-full max-w-md p-6 shadow-xl" @submit.prevent="kembalikan">
                <h3 class="judul-bagian">{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta perbaikan' : 'Tolak pengajuan' }}</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{
                        kembalikanItem.aksi === 'perbaikan'
                            ? `${kembalikanItem.baris.nama} bisa memperbaiki isian lalu mengirim ulang.`
                            : `${kembalikanItem.baris.nama} bisa mengajukan lagi dengan form baru.`
                    }}
                    Catatan ditampilkan ke mahasiswa.
                </p>
                <label class="mt-4 grid gap-2">
                    <span class="label-isian">Catatan</span>
                    <textarea
                        v-model="catatanForm.catatan"
                        rows="3"
                        maxlength="1000"
                        class="isian isian-area"
                        :placeholder="kembalikanItem.aksi === 'perbaikan' ? 'Mis. perjelas rumusan masalah' : 'Mis. topik di luar bidang prodi'"
                        required
                    />
                    <InputError :message="catatanForm.errors.catatan" />
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="kembalikanItem = null">Batal</Button>
                    <Button
                        type="submit"
                        :disabled="catatanForm.processing"
                        :variant="kembalikanItem.aksi === 'perbaikan' ? 'default' : 'destructive'"
                        >{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta Perbaikan' : 'Tolak' }}</Button
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>
