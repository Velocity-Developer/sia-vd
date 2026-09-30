<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFitur } from '@/composables/useFitur';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { JENIS_UJIAN, STATUS_PENGAJUAN_SUSULAN, type JenisUjian, type StatusPengajuanSusulan } from '@/lib/ujian';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Paperclip, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    kelas: string | null;
    matkul: string | null;
    jenis: JenisUjian;
    tanggal_ujian: string | null;
    alasan: string;
    jumlah_lampiran: number;
    status: StatusPengajuanSusulan;
    ikut_ujian_utama: boolean;
    catatan_admin: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    diajukan_at: string | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; status: string | null; search: string };
    jumlahMenunggu: number;
    tahunAkademikOptions: Opsi[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
// Tanpa fitur keuangan, remidi dan susulan tidak bersyarat bayar.
const keuangan = useFitur().aktif('keuangan');
const semua = 'all';
const tahun = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const status = ref<string>(props.filter.status ?? semua);
const search = ref(props.filter.search);
let jeda: number | undefined;

const kirim = () =>
    router.get(
        route('admin.ujian-susulan.index'),
        { tahun_akademik_id: tahun.value, status: status.value === semua ? null : status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const setujuiItem = ref<Baris | null>(null);
const setujui = () => {
    if (!setujuiItem.value) return;
    router.post(route('admin.ujian-susulan.setujui', setujuiItem.value.id), {}, { preserveScroll: true, onFinish: () => (setujuiItem.value = null) });
};

const tolakItem = ref<Baris | null>(null);
const catatan = ref('');
const catatanError = ref('');
const bukaTolak = (baris: Baris) => {
    tolakItem.value = baris;
    catatan.value = '';
    catatanError.value = '';
};
const tolak = () => {
    if (!tolakItem.value) return;
    router.post(
        route('admin.ujian-susulan.tolak', tolakItem.value.id),
        { catatan: catatan.value },
        { preserveScroll: true, onSuccess: () => (tolakItem.value = null), onError: (e) => (catatanError.value = e.catatan ?? '') },
    );
};
</script>

<template>
    <Head title="Ujian Susulan" />
    <AppLayout :breadcrumbs="[{ title: 'Ujian Susulan', href: route('admin.ujian-susulan.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Ujian Susulan</h1>
                        <p class="deskripsi-halaman">
                            Pengajuan mahasiswa yang tidak bisa mengikuti UTS/UAS.
                            <template v-if="keuangan">
                                Setelah disetujui, terbitkan tagihannya di Keuangan → Tagihan Susulan; jadwal susulan hanya tampil bagi yang sudah
                                lunas.
                            </template>
                            <template v-else>Setelah disetujui, jadwalkan susulannya di Jadwal Ujian.</template>
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="props.jumlahMenunggu" class="alert-info">{{ props.jumlahMenunggu }} pengajuan menunggu keputusan.</div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahun" label="Filter tahun akademik" @change="kirim">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="status" label="Filter status" @change="kirim">
                        <option :value="semua">Semua status</option>
                        <option value="menunggu">Menunggu</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="ditolak">Ditolak</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1020px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Ujian</th>
                                    <th>Alasan</th>
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
                                    </td>
                                    <td>
                                        <span class="block">{{ JENIS_UJIAN[b.jenis] }} {{ b.matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]"
                                            >Kelas {{ b.kelas }} · {{ formatTanggal(b.tanggal_ujian, false) }}</span
                                        >
                                    </td>
                                    <td class="max-w-[320px]">
                                        <span class="block whitespace-pre-line">{{ b.alasan }}</span>
                                        <span class="mt-1 flex flex-wrap gap-3">
                                            <a
                                                v-for="i in b.jumlah_lampiran"
                                                :key="i"
                                                :href="route('berkas.lampiran-susulan', [b.id, i - 1])"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> Lampiran {{ i }}</a
                                            >
                                        </span>
                                        <span class="mt-1 block text-xs text-[#a39e98]">Diajukan {{ formatTanggal(b.diajukan_at, false) }}</span>
                                    </td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_PENGAJUAN_SUSULAN[b.status].kelas"
                                            >{{ STATUS_PENGAJUAN_SUSULAN[b.status].label }}</span
                                        >
                                        <span v-if="b.ikut_ujian_utama && b.status === 'dibatalkan'" class="mt-1 block text-xs text-[#a39e98]"
                                            >Ikut ujian utama</span
                                        >
                                        <span v-if="b.catatan_admin" class="mt-1 block max-w-[220px] text-xs text-[#a39e98]">{{
                                            b.catatan_admin
                                        }}</span>
                                        <span v-if="b.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(b.diproses_at, false) }} · {{ b.diproses_oleh }}</span
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <div v-if="b.status === 'menunggu'" class="aksi-tabel">
                                            <Button variant="destructive" size="sm" @click="bukaTolak(b)">Tolak</Button>
                                            <Button size="sm" @click="setujuiItem = b">Setujui</Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuan.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada pengajuan ujian susulan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />
            </div>
        </div>

        <AlertModal
            :open="!!setujuiItem"
            title="Setujui pengajuan?"
            :description="
                setujuiItem
                    ? `Setujui ujian susulan ${JENIS_UJIAN[setujuiItem.jenis]} ${setujuiItem.matkul} untuk ${setujuiItem.nama}?${keuangan ? ' Tagihannya diterbitkan dari menu Tagihan Susulan.' : ''}`
                    : ''
            "
            confirm-text="Setujui"
            cancel-text="Batal"
            @update:open="!$event && (setujuiItem = null)"
            @confirm="setujui"
            @cancel="setujuiItem = null"
        />
        <div v-if="tolakItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="tolakItem = null">
            <div class="kartu w-full max-w-md p-6 shadow-xl">
                <h3 class="judul-bagian">Tolak pengajuan</h3>
                <p class="mt-2 text-sm text-[#615d59]">Alasan penolakan ditampilkan ke {{ tolakItem.nama }}.</p>
                <label class="mt-4 grid gap-2">
                    <span class="label-isian">Alasan</span>
                    <Input v-model="catatan" placeholder="Mis. bukti tidak sah" />
                    <span v-if="catatanError" class="text-xs text-[#dd5b00]">{{ catatanError }}</span>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" @click="tolakItem = null">Batal</Button>
                    <Button variant="destructive" @click="tolak">Tolak</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
