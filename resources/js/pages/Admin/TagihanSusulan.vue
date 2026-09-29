<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { rupiah, STATUS_TAGIHAN_REMIDI, type Rincian, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    kelas: string | null;
    matkul: string | null;
    jenis: 'uts' | 'uas';
    total: number;
    rincian: Rincian[];
    status: StatusTagihanRemidi;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
    diverifikasi_oleh: string | null;
    diverifikasi_at: string | null;
    ikut_ujian_utama: boolean;
    batas_bayar: string;
    ujian_susulan: { tanggal: string; lewat: boolean } | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    tagihan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    ringkasan: { belum_ditagih: number; belum_bayar: number; menunggu: number; lunas: number };
    filter: { tahun_akademik_id: number | null; status: string; search: string };
    batasBayarHari: number;
    adaJenisBiaya: boolean;
    tahunAkademikOptions: Opsi[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const semua = 'all';
const tahunAkademikId = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const status = ref<string>(props.filter.status || semua);
const search = ref(props.filter.search);
let jedaCari: number | undefined;

const kirim = () =>
    router.get(
        route('admin.tagihan-susulan.index'),
        { tahun_akademik_id: tahunAkademikId.value, status: status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(search, () => {
    window.clearTimeout(jedaCari);
    jedaCari = window.setTimeout(kirim, 400);
});

watch(
    () => props.filter,
    (baru) => {
        tahunAkademikId.value = baru.tahun_akademik_id ?? '';
        status.value = baru.status || semua;
        search.value = baru.search;
    },
);

const terbitOpen = ref(false);
const terbitkan = () =>
    router.post(
        route('admin.tagihan-susulan.terbitkan'),
        { tahun_akademik_id: tahunAkademikId.value },
        { preserveScroll: true, onFinish: () => (terbitOpen.value = false) },
    );

const lunasItem = ref<Baris | null>(null);
const tandaiLunas = () => {
    if (!lunasItem.value) return;
    router.post(route('admin.tagihan-susulan.lunas', lunasItem.value.id), {}, { preserveScroll: true, onFinish: () => (lunasItem.value = null) });
};

const tolakItem = ref<Baris | null>(null);
const alasan = ref('');
const alasanError = ref('');
const bukaTolak = (baris: Baris) => {
    tolakItem.value = baris;
    alasan.value = '';
    alasanError.value = '';
};
const tolak = () => {
    if (!tolakItem.value) return;
    router.post(
        route('admin.tagihan-susulan.tolak', tolakItem.value.id),
        { alasan: alasan.value },
        {
            preserveScroll: true,
            onSuccess: () => (tolakItem.value = null),
            onError: (errors) => (alasanError.value = errors.alasan ?? ''),
        },
    );
};

const deskripsiLunas = (baris: Baris | null) =>
    baris
        ? `Tandai tagihan susulan ${baris.jenis.toUpperCase()} ${baris.matkul} milik ${baris.nama} sebesar ${rupiah(baris.total)} sebagai lunas?${baris.ada_bukti ? '' : ' Belum ada bukti yang diunggah; pastikan pembayaran sudah diterima.'}`
        : '';
</script>

<template>
    <Head title="Tagihan Susulan" />
    <AppLayout :breadcrumbs="[{ title: 'Tagihan Susulan', href: route('admin.tagihan-susulan.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Tagihan Susulan</h1>
                        <p class="deskripsi-halaman">
                            Diterbitkan dari pengajuan ujian susulan yang disetujui. Batas bayar tiap tagihan
                            {{ props.batasBayarHari }} hari sejak diterbitkan; yang belum lunas lewat batas gugur.
                        </p>
                    </div>
                    <Button :disabled="!props.ringkasan.belum_ditagih" @click="terbitOpen = true">
                        Terbitkan Tagihan ({{ props.ringkasan.belum_ditagih }})
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="!props.adaJenisBiaya" class="alert-gagal">
                    Belum ada jenis biaya kategori Susulan yang aktif.
                    <Link :href="route('admin.jenis-biaya.index')" class="font-medium text-[#0075de] hover:underline">Atur jenis biaya</Link>
                </div>
                <div v-if="props.ringkasan.menunggu" class="alert-info">
                    {{ props.ringkasan.menunggu }} bukti bayar menunggu verifikasi. Verifikasi sebelum ujian susulan berlangsung; mahasiswa baru bisa
                    ikut susulan setelah dinyatakan lunas.
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Menunggu Verifikasi</p>
                        <p class="text-2xl font-bold text-[#0075de]">{{ props.ringkasan.menunggu }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Belum Bayar</p>
                        <p class="text-2xl font-bold text-[#dd5b00]">{{ props.ringkasan.belum_bayar }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Lunas</p>
                        <p class="text-2xl font-bold text-[#1aae39]">{{ props.ringkasan.lunas }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Belum Ditagih</p>
                        <p class="text-2xl font-bold text-black dark:text-foreground">{{ props.ringkasan.belum_ditagih }}</p>
                    </div>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="status" label="Filter status" @change="kirim">
                        <option :value="semua">Semua Status</option>
                        <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                        <option value="belum_bayar">Belum Bayar</option>
                        <option value="ditolak">Ditolak</option>
                        <option value="lunas">Lunas</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.tagihan.total }}</span> tagihan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1000px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Mata Kuliah</th>
                                    <th>Tagihan</th>
                                    <th>Status</th>
                                    <th>Bukti</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(baris, index) in props.tagihan.data" :key="baris.id">
                                    <td class="kolom-no">{{ (props.tagihan.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ baris.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ baris.nim }}</span>
                                    </td>
                                    <td>
                                        <span class="block">Susulan {{ baris.jenis.toUpperCase() }} {{ baris.matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]">Kelas {{ baris.kelas }}</span>
                                        <span
                                            v-if="baris.ujian_susulan && !['lunas', 'dibatalkan'].includes(baris.status)"
                                            class="block text-xs"
                                            :class="baris.ujian_susulan.lewat ? 'text-[#b42318]' : 'text-[#dd5b00]'"
                                            >Ujian susulan {{ formatTanggal(baris.ujian_susulan.tanggal, false)
                                            }}{{ baris.ujian_susulan.lewat ? ' (sudah mulai)' : '' }}</span
                                        >
                                    </td>
                                    <td class="tabular-nums">
                                        <span class="block">{{ rupiah(baris.total) }}</span>
                                        <span v-for="r in baris.rincian" :key="r.nama" class="block text-xs text-[#a39e98]">
                                            {{ r.nama }}<template v-if="r.jumlah > 1"> · {{ r.jumlah }} × {{ rupiah(r.nominal_satuan) }}</template>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_TAGIHAN_REMIDI[baris.status].kelas"
                                            >{{ STATUS_TAGIHAN_REMIDI[baris.status].label }}</span
                                        >
                                        <span v-if="!['lunas', 'dibatalkan'].includes(baris.status)" class="mt-1 block text-xs text-[#a39e98]"
                                            >Batas {{ formatTanggal(baris.batas_bayar, false) }}</span
                                        >
                                        <span v-if="baris.ikut_ujian_utama" class="mt-1 block max-w-[220px] text-xs text-[#b42318]"
                                            >Sudah ikut ujian utama<template v-if="baris.status === 'lunas'">
                                                – pengembalian dana di luar sistem</template
                                            ></span
                                        >
                                        <span v-if="baris.alasan_tolak" class="mt-1 block max-w-[200px] text-xs text-[#a39e98]"
                                            >Ditolak: {{ baris.alasan_tolak }}</span
                                        >
                                        <span v-if="baris.status === 'lunas' && baris.diverifikasi_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(baris.diverifikasi_at, false) }} · {{ baris.diverifikasi_oleh }}</span
                                        >
                                    </td>
                                    <td>
                                        <template v-if="baris.ada_bukti">
                                            <a
                                                :href="route('berkas.bukti-susulan', baris.id)"
                                                target="_blank"
                                                rel="noopener"
                                                class="font-medium text-[#0075de] hover:underline"
                                                >Lihat bukti</a
                                            >
                                            <span class="block text-xs text-[#a39e98]">{{ formatTanggal(baris.bukti_diunggah_at, false) }}</span>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum ada</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div v-if="!['lunas', 'dibatalkan'].includes(baris.status)" class="aksi-tabel">
                                            <Button
                                                v-if="baris.status === 'menunggu_verifikasi'"
                                                variant="destructive"
                                                size="sm"
                                                @click="bukaTolak(baris)"
                                                >Tolak</Button
                                            >
                                            <Button size="sm" @click="lunasItem = baris">Tandai Lunas</Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.tagihan.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada tagihan susulan yang cocok.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.tagihan.links" :total="props.tagihan.total" />
            </div>
        </div>

        <AlertModal
            :open="terbitOpen"
            title="Terbitkan tagihan susulan?"
            :description="`${props.ringkasan.belum_ditagih} pengajuan disetujui akan ditagih sesuai tarif jenis biaya Susulan, batas bayar ${props.batasBayarHari} hari. Pengajuan yang mahasiswanya ternyata ikut ujian utama dilewati.`"
            confirm-text="Terbitkan"
            cancel-text="Batal"
            @update:open="terbitOpen = $event"
            @confirm="terbitkan"
            @cancel="terbitOpen = false"
        />
        <AlertModal
            :open="!!lunasItem"
            title="Tandai lunas?"
            :description="deskripsiLunas(lunasItem)"
            confirm-text="Tandai Lunas"
            cancel-text="Batal"
            @update:open="!$event && (lunasItem = null)"
            @confirm="tandaiLunas"
            @cancel="lunasItem = null"
        />
        <div v-if="tolakItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="tolakItem = null">
            <div class="kartu w-full max-w-md p-6 shadow-xl">
                <h3 class="judul-bagian">Tolak bukti bayar</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{ tolakItem.nama }} bisa mengunggah ulang sebelum batas bayar. Alasan penolakan ditampilkan ke mahasiswa.
                </p>
                <label class="mt-4 grid gap-2">
                    <span class="label-isian">Alasan</span>
                    <Input v-model="alasan" placeholder="Mis. nominal tidak sesuai" />
                    <span v-if="alasanError" class="text-xs text-[#dd5b00]">{{ alasanError }}</span>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" @click="tolakItem = null">Batal</Button>
                    <Button variant="destructive" @click="tolak">Tolak</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
