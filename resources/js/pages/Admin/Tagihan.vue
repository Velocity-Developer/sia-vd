<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_TAGIHAN_REMIDI } from '@/lib/tagihanRemidi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    angkatan: number | null;
    semester: number | null;
    tagihan_id: number | null;
    status: 'belum_terbit' | 'belum_bayar' | 'menunggu_verifikasi' | 'ditolak' | 'lunas';
    total: number;
    rincian_manual: boolean;
    tanggal_lunas: string | null;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
    diverifikasi_oleh: string | null;
    diubah_oleh: string | null;
    diubah_pada: string | null;
    krs_tersimpan: boolean;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    daftar: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    ringkasan: { total: number; terbit: number; belum_terbit: number; lunas: number; menunggu: number; belum_bayar: number };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; angkatan: number | null; status: string; search: string };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    angkatanOptions: number[];
    adaJenisBiaya: boolean;
}>();

const page = usePage<{ flash?: { success?: string; error?: string; tagihan_konfirmasi?: string } }>();

const semua = 'all';
const tahunAkademikId = ref<number | string>(props.filter.tahun_akademik_id ?? semua);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const angkatan = ref<number | string>(props.filter.angkatan ?? semua);
const status = ref<string>(props.filter.status || semua);
const search = ref(props.filter.search);
let jedaCari: number | undefined;

const kirim = () => {
    router.get(
        route('admin.tagihan.index'),
        {
            tahun_akademik_id: tahunAkademikId.value,
            prodi_id: prodiId.value,
            angkatan: angkatan.value,
            status: status.value,
            search: search.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

watch(search, () => {
    window.clearTimeout(jedaCari);
    jedaCari = window.setTimeout(kirim, 400);
});

watch(
    () => props.filter,
    (baru) => {
        tahunAkademikId.value = baru.tahun_akademik_id ?? semua;
        prodiId.value = baru.prodi_id ?? semua;
        angkatan.value = baru.angkatan ?? semua;
        status.value = baru.status || semua;
        search.value = baru.search;
    },
);

const STATUS = { ...STATUS_TAGIHAN_REMIDI, belum_terbit: { label: 'Belum Terbit', kelas: 'bg-[#f6f5f4] text-[#615d59]' } };

// Aksi pembayaran satu tagihan: lunas, batal lunas, atau tolak bukti (dengan alasan).
const konfirmasi = ref<{ baris: Baris; aksi: 'lunas' | 'batal-lunas' } | null>(null);
const deskripsiKonfirmasi = (k: { baris: Baris; aksi: 'lunas' | 'batal-lunas' } | null) => {
    if (!k) return '';
    if (k.aksi === 'batal-lunas') {
        return `Batalkan status lunas tagihan ${k.baris.nama}? ${k.baris.ada_bukti ? 'Tagihan kembali menunggu verifikasi bukti.' : 'Tagihan kembali belum bayar.'}`;
    }
    return `Tandai tagihan ${k.baris.nama} sebesar ${rupiah(k.baris.total)} sebagai lunas?${k.baris.ada_bukti ? '' : ' Belum ada bukti yang diunggah; pastikan pembayaran sudah diterima.'}`;
};
const jalankanKonfirmasi = () => {
    const k = konfirmasi.value;
    if (!k?.baris.tagihan_id) return;
    router.post(
        route(k.aksi === 'lunas' ? 'admin.tagihan.lunas' : 'admin.tagihan.batal-lunas', k.baris.tagihan_id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (konfirmasi.value = null),
        },
    );
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
    if (!tolakItem.value?.tagihan_id) return;
    router.post(
        route('admin.tagihan.tolak', tolakItem.value.tagihan_id),
        { alasan: alasan.value },
        {
            preserveScroll: true,
            onSuccess: () => (tolakItem.value = null),
            onError: (errors) => (alasanError.value = errors.alasan ?? ''),
        },
    );
};

const bukaKunciKrs = (baris: Baris) => {
    if (!confirm(`Buka kunci KRS ${baris.nama}? Mahasiswa bisa mengubah kelasnya lagi selama periode KRS masih berjalan.`)) return;

    router.delete(route('admin.tagihan.buka-kunci-krs', baris.id), {
        data: { tahun_akademik_id: tahunAkademikId.value },
        preserveScroll: true,
        preserveState: true,
    });
};

const terbitkan = (paksa = false) => {
    if (
        !paksa &&
        !confirm(
            'Terbitkan tagihan untuk semua mahasiswa aktif pada semester ini? Tagihan yang sudah dibayar, sudah ada bukti bayar, atau rinciannya diketik manual tidak diubah.',
        )
    )
        return;

    router.post(
        route('admin.tagihan.terbitkan'),
        { tahun_akademik_id: tahunAkademikId.value, paksa },
        {
            preserveScroll: true,
            onSuccess: () => {
                // Nilai semester lalu belum lengkap: kuota SKS sebagian mahasiswa memakai angka
                // "tanpa IPS", jadi admin memastikan dulu sebelum tagihan benar-benar terbit.
                const peringatan = page.props.flash?.tagihan_konfirmasi;

                if (peringatan && confirm(`${peringatan}\n\nTerbitkan sekarang?`)) {
                    terbitkan(true);
                }
            },
        },
    );
};

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
const tanggal = (nilai: string | null) => (nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(nilai)) : '-');
const waktu = (nilai: string | null) =>
    nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(nilai.replace(' ', 'T'))) : null;
</script>

<template>
    <Head title="Tagihan Mahasiswa" />
    <AppLayout :breadcrumbs="[{ title: 'Tagihan Mahasiswa', href: route('admin.tagihan.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Tagihan Mahasiswa</h1>
                        <p class="deskripsi-halaman">Status pembayaran tiap mahasiswa per semester.</p>
                    </div>
                    <Button :disabled="tahunAkademikId === semua" @click="terbitkan()">Terbitkan Tagihan Semester Ini</Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>
                <div v-if="!props.adaJenisBiaya" class="alert-gagal">
                    Belum ada jenis biaya aktif, jadi tagihan belum bisa diterbitkan.
                    <Link :href="route('admin.jenis-biaya.index')" class="font-medium text-[#0075de] hover:underline">Atur jenis biaya</Link>
                </div>

                <div v-if="props.ringkasan.menunggu" class="alert-info">
                    {{ props.ringkasan.menunggu }} bukti bayar menunggu verifikasi.
                    <button type="button" class="font-medium underline" @click="((status = 'menunggu_verifikasi'), kirim())">Tampilkan</button>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Mahasiswa Aktif</p>
                        <p class="text-2xl font-bold text-black dark:text-foreground">{{ props.ringkasan.total }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Lunas</p>
                        <p class="text-2xl font-bold text-[#1aae39]">{{ props.ringkasan.lunas }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Menunggu Verifikasi</p>
                        <p class="text-2xl font-bold text-[#0075de]">{{ props.ringkasan.menunggu }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Belum Bayar</p>
                        <p class="text-2xl font-bold text-[#dd5b00]">{{ props.ringkasan.belum_bayar }}</p>
                    </div>
                    <div class="kartu px-4 py-3">
                        <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Belum Terbit</p>
                        <p class="text-2xl font-bold text-black dark:text-foreground">{{ props.ringkasan.belum_terbit }}</p>
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
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="kirim">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="angkatan" label="Filter angkatan" @change="kirim">
                        <option :value="semua">Semua Angkatan</option>
                        <option v-for="item in props.angkatanOptions" :key="item" :value="item">{{ item }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="status" label="Filter status pembayaran" @change="kirim">
                        <option :value="semua">Semua Status</option>
                        <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                        <option value="belum_bayar">Belum Bayar</option>
                        <option value="ditolak">Ditolak</option>
                        <option value="lunas">Lunas</option>
                        <option value="belum_terbit">Belum Terbit</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.daftar.total }}</span> mahasiswa
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1040px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Program Studi</th>
                                    <th>Tagihan</th>
                                    <th>Status</th>
                                    <th>KRS</th>
                                    <th>Terakhir Diubah</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(baris, index) in props.daftar.data" :key="baris.id">
                                    <td class="kolom-no">{{ (props.daftar.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ baris.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ baris.nim }} · Angkatan {{ baris.angkatan }}</span>
                                    </td>
                                    <td>{{ baris.prodi ?? '-' }}</td>
                                    <td class="tabular-nums">
                                        <span v-if="baris.tagihan_id" class="block">{{ rupiah(baris.total) }}</span>
                                        <span v-else class="block text-[#a39e98]">Belum diterbitkan</span>
                                        <span v-if="baris.rincian_manual" class="block text-xs text-[#a39e98]">Rincian manual</span>
                                    </td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS[baris.status].kelas"
                                        >
                                            {{ STATUS[baris.status].label }}
                                        </span>
                                        <span v-if="baris.tanggal_lunas" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ tanggal(baris.tanggal_lunas)
                                            }}<template v-if="baris.diverifikasi_oleh"> · {{ baris.diverifikasi_oleh }}</template></span
                                        >
                                        <span v-if="baris.status === 'ditolak'" class="mt-1 block max-w-[200px] text-xs text-[#a39e98]"
                                            >Ditolak: {{ baris.alasan_tolak }}</span
                                        >
                                        <a
                                            v-if="baris.ada_bukti && baris.tagihan_id"
                                            :href="route('berkas.bukti-semester', baris.tagihan_id)"
                                            target="_blank"
                                            rel="noopener"
                                            class="mt-1 block text-xs font-medium text-[#0075de] hover:underline"
                                            >Lihat bukti · {{ tanggal(baris.bukti_diunggah_at) }}</a
                                        >
                                    </td>
                                    <td>
                                        <template v-if="baris.krs_tersimpan">
                                            <span class="whitespace-nowrap rounded-full bg-[#f6f5f4] px-2 py-0.5 text-xs font-medium text-[#31302e]">
                                                Tersimpan
                                            </span>
                                            <button
                                                type="button"
                                                class="mt-1 block text-xs font-medium text-[#0075de] hover:underline"
                                                @click="bukaKunciKrs(baris)"
                                            >
                                                Buka kunci
                                            </button>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum disimpan</span>
                                    </td>
                                    <td>
                                        <template v-if="baris.diubah_pada">
                                            <span class="block text-xs text-[#615d59]">{{ waktu(baris.diubah_pada) }}</span>
                                            <span class="block text-xs text-[#a39e98]">{{ baris.diubah_oleh ?? 'sistem' }}</span>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum pernah diubah</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="sm">
                                                <Link :href="route('admin.tagihan.rincian', [baris.id, { tahun_akademik_id: tahunAkademikId }])"
                                                    >Rincian</Link
                                                >
                                            </Button>
                                            <Button
                                                v-if="baris.status === 'menunggu_verifikasi'"
                                                variant="destructive"
                                                size="sm"
                                                @click="bukaTolak(baris)"
                                                >Tolak</Button
                                            >
                                            <Button
                                                v-if="baris.tagihan_id && baris.status !== 'lunas'"
                                                size="sm"
                                                @click="konfirmasi = { baris, aksi: 'lunas' }"
                                                >Tandai Lunas</Button
                                            >
                                            <Button
                                                v-if="baris.status === 'lunas'"
                                                variant="outline"
                                                size="sm"
                                                @click="konfirmasi = { baris, aksi: 'batal-lunas' }"
                                                >Batal Lunas</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.daftar.data.length" class="baris-kosong">
                                    <td colspan="8" class="tabel-kosong">Tidak ada mahasiswa yang cocok dengan filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.daftar.links" :total="props.daftar.total" />
            </div>
        </div>

        <AlertModal
            :open="!!konfirmasi"
            :title="konfirmasi?.aksi === 'batal-lunas' ? 'Batalkan status lunas?' : 'Tandai lunas?'"
            :description="deskripsiKonfirmasi(konfirmasi)"
            :confirm-text="konfirmasi?.aksi === 'batal-lunas' ? 'Batalkan Lunas' : 'Tandai Lunas'"
            cancel-text="Batal"
            @update:open="!$event && (konfirmasi = null)"
            @confirm="jalankanKonfirmasi"
            @cancel="konfirmasi = null"
        />
        <div v-if="tolakItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="tolakItem = null">
            <div class="kartu w-full max-w-md p-6 shadow-xl">
                <h3 class="judul-bagian">Tolak bukti bayar</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{ tolakItem.nama }} bisa mengunggah ulang bukti bayar. Alasan penolakan ditampilkan ke mahasiswa.
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
