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
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Tagihan Mahasiswa</h1>
                        <p class="text-sm text-[#615d59]">Status pembayaran tiap mahasiswa per semester.</p>
                    </div>
                    <Button
                        class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                        :disabled="tahunAkademikId === semua"
                        @click="terbitkan()"
                    >
                        Terbitkan Tagihan Semester Ini
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="!props.adaJenisBiaya" class="rounded-xl border border-[#f6d7c4] bg-[#fdf6f1] px-4 py-3 text-sm text-[#dd5b00]">
                    Belum ada jenis biaya aktif, jadi tagihan belum bisa diterbitkan.
                    <Link :href="route('admin.jenis-biaya.index')" class="font-medium text-[#0075de] hover:underline">Atur jenis biaya</Link>
                </div>

                <div v-if="props.ringkasan.menunggu" class="rounded-xl border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]">
                    {{ props.ringkasan.menunggu }} bukti bayar menunggu verifikasi.
                    <button type="button" class="font-medium underline" @click="((status = 'menunggu_verifikasi'), kirim())">Tampilkan</button>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa Aktif</p>
                        <p class="text-[22px] font-bold text-black">{{ props.ringkasan.total }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Lunas</p>
                        <p class="text-[22px] font-bold text-[#1aae39]">{{ props.ringkasan.lunas }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Menunggu Verifikasi</p>
                        <p class="text-[22px] font-bold text-[#0075de]">{{ props.ringkasan.menunggu }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Belum Bayar</p>
                        <p class="text-[22px] font-bold text-[#dd5b00]">{{ props.ringkasan.belum_bayar }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Belum Terbit</p>
                        <p class="text-[22px] font-bold text-black">{{ props.ringkasan.belum_terbit }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:w-80">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="search"
                                placeholder="Cari nama atau NIM"
                                aria-label="Cari mahasiswa"
                                class="h-10 rounded-lg border-[#d8d5d2] bg-white pl-9 text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                            />
                        </div>
                        <p class="whitespace-nowrap text-sm text-[#615d59]">
                            <span class="font-medium text-black">{{ props.daftar.total }}</span> mahasiswa
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center">
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
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[1040px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Program Studi</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tagihan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">KRS</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Terakhir Diubah</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(baris, index) in props.daftar.data" :key="baris.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-[#615d59]">{{ (props.daftar.from ?? 0) + index }}</td>
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ baris.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ baris.nim }} · Angkatan {{ baris.angkatan }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ baris.prodi ?? '-' }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span v-if="baris.tagihan_id" class="block">{{ rupiah(baris.total) }}</span>
                                        <span v-else class="block text-[#a39e98]">Belum diterbitkan</span>
                                        <span v-if="baris.rincian_manual" class="block text-xs text-[#a39e98]">Rincian manual</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px]">
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
                                    <td class="px-4 py-3 text-[15px]">
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
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <template v-if="baris.diubah_pada">
                                            <span class="block text-xs text-[#615d59]">{{ waktu(baris.diubah_pada) }}</span>
                                            <span class="block text-xs text-[#a39e98]">{{ baris.diubah_oleh ?? 'sistem' }}</span>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum pernah diubah</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                :href="route('admin.tagihan.rincian', [baris.id, { tahun_akademik_id: tahunAkademikId }])"
                                                class="text-sm font-medium text-[#0075de] hover:underline"
                                            >
                                                Rincian
                                            </Link>
                                            <Button
                                                v-if="baris.status === 'menunggu_verifikasi'"
                                                variant="outline"
                                                class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#dd5b00]"
                                                @click="bukaTolak(baris)"
                                                >Tolak</Button
                                            >
                                            <Button
                                                v-if="baris.tagihan_id && baris.status !== 'lunas'"
                                                class="h-8 whitespace-nowrap rounded-lg bg-[#0075de] px-3 text-sm text-white hover:bg-[#005bab]"
                                                @click="konfirmasi = { baris, aksi: 'lunas' }"
                                                >Tandai Lunas</Button
                                            >
                                            <Button
                                                v-if="baris.status === 'lunas'"
                                                variant="outline"
                                                class="h-8 whitespace-nowrap rounded-lg border-[#d8d5d2] px-3 text-sm"
                                                @click="konfirmasi = { baris, aksi: 'batal-lunas' }"
                                                >Batal Lunas</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.daftar.data.length">
                                    <td colspan="8" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tidak ada mahasiswa yang cocok dengan filter.
                                    </td>
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
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold">Tolak bukti bayar</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{ tolakItem.nama }} bisa mengunggah ulang bukti bayar. Alasan penolakan ditampilkan ke mahasiswa.
                </p>
                <label class="mt-4 grid gap-2 text-sm">
                    <span class="font-medium">Alasan</span>
                    <Input v-model="alasan" placeholder="Mis. nominal tidak sesuai" class="h-10" />
                    <span v-if="alasanError" class="text-xs text-[#dd5b00]">{{ alasanError }}</span>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" class="rounded-full" @click="tolakItem = null">Batal</Button>
                    <Button class="rounded-full bg-[#dd5b00] text-white hover:bg-[#b84b00]" @click="tolak">Tolak</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
