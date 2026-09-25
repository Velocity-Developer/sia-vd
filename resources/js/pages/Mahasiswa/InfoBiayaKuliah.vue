<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { STATUS_TAGIHAN_REMIDI, type Rincian, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

type Item = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };
type Tagihan = { id: number; tahun_akademik: string; status: string; total: number; tanggal_lunas: string | null; items: Item[] };

type Dasar = {
    tarif_per_sks: number;
    kuota_sks: number;
    prodi: string | null;
    angkatan: number | null;
    ips: number | null;
    ips_tahun_akademik: string | null;
    sks_diambil: number;
    sisa_sks: number;
};

type TagihanRemidi = {
    id: number;
    matkul: string | null;
    kelas: string | null;
    tahun_akademik: string;
    rincian: Rincian[];
    total: number;
    status: StatusTagihanRemidi;
    batas_bayar: string | null;
    boleh_unggah: boolean;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
};

const props = defineProps<{
    semesterBerjalan: Tagihan | null;
    tahunAktif: string | null;
    riwayat: Tagihan[];
    dasar: Dasar | null;
    tagihanRemidi: TagihanRemidi[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const formBukti = useForm<{ bukti: File | null }>({ bukti: null });
const unggahUntuk = ref<number | null>(null);
// Input berkas bawaan tidak ikut kosong saat form direset, jadi dirender ulang lewat key.
const kunciInput = ref(0);
const pilihBukti = (tagihan: TagihanRemidi, event: Event) => {
    formBukti.clearErrors();
    formBukti.bukti = (event.target as HTMLInputElement).files?.[0] ?? null;
    unggahUntuk.value = tagihan.id;
};
const kirimBukti = (tagihan: TagihanRemidi) =>
    formBukti.post(route('mahasiswa.tagihan-remidi.bukti', tagihan.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            formBukti.reset();
            unggahUntuk.value = null;
            kunciInput.value++;
        },
    });

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
const tanggal = (nilai: string | null) => (nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(nilai)) : null);
</script>

<template>
    <Head title="Info Biaya Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Info Biaya Kuliah', href: route('mahasiswa.info-biaya-kuliah') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Info Biaya Kuliah</h1>
                    <p class="text-sm text-[#615d59]">Tagihan semester berjalan dan riwayat pembayaran Anda.</p>
                </div>

                <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Semester Berjalan</p>
                            <p class="text-lg font-semibold text-black">{{ props.semesterBerjalan?.tahun_akademik ?? props.tahunAktif ?? '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Total Tagihan</p>
                            <p class="text-[22px] font-bold text-black">{{ rupiah(props.semesterBerjalan?.total ?? 0) }}</p>
                            <span
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="props.semesterBerjalan?.status === 'lunas' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#fdf1e9] text-[#dd5b00]'"
                            >
                                {{ props.semesterBerjalan?.status === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                            </span>
                        </div>
                    </div>

                    <p v-if="props.semesterBerjalan?.tanggal_lunas" class="mt-3 text-sm text-[#615d59]">
                        Dinyatakan lunas pada {{ tanggal(props.semesterBerjalan.tanggal_lunas) }}.
                    </p>

                    <div v-if="props.semesterBerjalan?.items?.length" class="mt-4 overflow-hidden rounded-lg border border-[#e6e6e6]">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[560px] text-left">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Komponen</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Nominal</th>
                                        <th class="px-4 py-2.5 text-center text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                                            Jumlah
                                        </th>
                                        <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                                            Subtotal
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="(item, index) in props.semesterBerjalan.items" :key="index">
                                        <td class="px-4 py-2.5 text-[15px] text-black">
                                            <span class="block">{{ item.nama }}</span>
                                            <span v-if="item.cara_hitung === 'per_sks'" class="block text-xs text-[#a39e98]">
                                                {{ item.jumlah }} SKS (kuota maksimal Anda) × {{ rupiah(item.nominal_satuan) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-[15px] text-[#31302e]">{{ rupiah(item.nominal_satuan) }}</td>
                                        <td class="px-4 py-2.5 text-center text-[15px] text-[#31302e]">{{ item.jumlah }}</td>
                                        <td class="px-4 py-2.5 text-right text-[15px] font-medium text-black">{{ rupiah(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <p v-else class="mt-4 rounded-lg border border-dashed border-[#e6e6e6] px-4 py-6 text-center text-sm text-[#615d59]">
                        Tagihan semester ini belum diterbitkan. Hubungi bagian keuangan bila Anda merasa seharusnya sudah ada.
                    </p>
                </div>

                <div v-if="props.dasar" class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-black">Dari Mana Angka Ini?</h2>
                    <p class="mt-1 text-sm text-[#615d59]">
                        Biaya semester dihitung dari tarif per SKS yang berlaku untuk Anda dikali jatah SKS semester ini.
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Tarif per SKS</p>
                            <p class="mt-1 text-[17px] font-bold text-black">{{ rupiah(props.dasar.tarif_per_sks) }}</p>
                            <p class="text-xs text-[#615d59]">
                                {{ props.dasar.prodi ?? 'Program studi Anda'
                                }}<span v-if="props.dasar.angkatan">, angkatan {{ props.dasar.angkatan }}</span>
                            </p>
                        </div>
                        <div class="rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">SKS yang Harus Diambil</p>
                            <p class="mt-1 text-[17px] font-bold text-black">{{ props.dasar.kuota_sks }} SKS</p>
                            <p class="text-xs text-[#615d59]">
                                {{
                                    props.dasar.ips !== null
                                        ? `Jatah SKS dari IPS ${props.dasar.ips.toFixed(2)} (${props.dasar.ips_tahun_akademik})`
                                        : 'Jatah SKS untuk mahasiswa yang belum punya IPS'
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Total Semester Ini</p>
                            <p class="mt-1 text-[17px] font-bold text-black">{{ rupiah(props.dasar.tarif_per_sks * props.dasar.kuota_sks) }}</p>
                            <p class="text-xs text-[#615d59]">{{ props.dasar.kuota_sks }} SKS × {{ rupiah(props.dasar.tarif_per_sks) }}</p>
                        </div>
                    </div>

                    <p
                        class="mt-4 rounded-lg border px-4 py-3 text-sm"
                        :class="
                            props.dasar.sisa_sks > 0 ? 'border-[#f6d7c4] bg-[#fdf6f1] text-[#dd5b00]' : 'border-[#c9ecd2] bg-[#f2fbf4] text-[#1aae39]'
                        "
                    >
                        <template v-if="props.dasar.sisa_sks > 0">
                            Anda baru mengambil {{ props.dasar.sks_diambil }} SKS di KRS. Masih ada
                            <span class="font-semibold">{{ props.dasar.sisa_sks }} SKS</span> yang sudah ikut ditagihkan tetapi belum Anda ambil.
                        </template>
                        <template v-else> Anda sudah mengambil {{ props.dasar.sks_diambil }} SKS, sesuai jatah yang ditagihkan. </template>
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <div v-if="props.tagihanRemidi.length" class="rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="border-b border-[#e6e6e6] px-5 py-4">
                        <h2 class="text-lg font-semibold text-black">Tagihan Remidi</h2>
                        <p class="text-sm text-[#615d59]">
                            Unggah bukti bayar sebelum batas bayar. Jadwal remidi terbuka setelah admin memverifikasi.
                        </p>
                    </div>
                    <div class="divide-y divide-[#e6e6e6]">
                        <div v-for="tagihan in props.tagihanRemidi" :key="tagihan.id" class="flex flex-col gap-3 px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-black">{{ tagihan.matkul }}</p>
                                    <p class="text-xs text-[#a39e98]">Kelas {{ tagihan.kelas }} · {{ tagihan.tahun_akademik }}</p>
                                    <p v-for="r in tagihan.rincian" :key="r.nama" class="text-xs text-[#615d59]">
                                        {{ r.nama }}<template v-if="r.jumlah > 1"> · {{ r.jumlah }} SKS × {{ rupiah(r.nominal_satuan) }}</template>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-black">{{ rupiah(tagihan.total) }}</p>
                                    <span
                                        class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="STATUS_TAGIHAN_REMIDI[tagihan.status].kelas"
                                        >{{ STATUS_TAGIHAN_REMIDI[tagihan.status].label }}</span
                                    >
                                    <p v-if="tagihan.batas_bayar && tagihan.status !== 'lunas'" class="mt-1 text-xs text-[#a39e98]">
                                        Batas bayar {{ formatTanggal(tagihan.batas_bayar) }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="tagihan.status === 'ditolak' || (tagihan.status === 'gugur' && tagihan.alasan_tolak)"
                                class="text-sm text-[#b42318]"
                            >
                                Bukti ditolak: {{ tagihan.alasan_tolak }}
                            </p>
                            <p v-if="tagihan.status === 'gugur'" class="text-sm text-[#615d59]">
                                Batas bayar sudah lewat, Anda tidak terdaftar sebagai peserta remidi mata kuliah ini.
                            </p>
                            <p v-if="tagihan.ada_bukti" class="text-sm text-[#615d59]">
                                Bukti diunggah {{ formatTanggal(tagihan.bukti_diunggah_at) }}.
                                <a
                                    :href="route('berkas.bukti-remidi', tagihan.id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="font-medium text-[#0075de] hover:underline"
                                    >Lihat bukti</a
                                >
                            </p>
                            <div v-if="tagihan.boleh_unggah" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <input
                                    :id="`bukti-${tagihan.id}`"
                                    :key="`bukti-${tagihan.id}-${kunciInput}`"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="text-sm file:mr-3 file:rounded-full file:border file:border-[#dddddd] file:bg-white file:px-3 file:py-1.5 file:text-sm"
                                    @change="pilihBukti(tagihan, $event)"
                                />
                                <Button
                                    size="sm"
                                    class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                                    :disabled="unggahUntuk !== tagihan.id || !formBukti.bukti || formBukti.processing"
                                    @click="kirimBukti(tagihan)"
                                    >{{ tagihan.ada_bukti ? 'Ganti Bukti' : 'Kirim Bukti' }}</Button
                                >
                            </div>
                            <InputError v-if="unggahUntuk === tagihan.id" :message="formBukti.errors.bukti" />
                        </div>
                    </div>
                </div>

                <div v-if="props.riwayat.length" class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="border-b border-[#e6e6e6] px-5 py-4">
                        <h2 class="text-lg font-semibold text-black">Riwayat Semester Sebelumnya</h2>
                    </div>
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Semester</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Total</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tanggal Lunas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="tagihan in props.riwayat" :key="tagihan.id">
                                    <td class="px-4 py-3 text-[15px] font-medium text-black">{{ tagihan.tahun_akademik }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ rupiah(tagihan.total) }}</td>
                                    <td class="px-4 py-3 text-[15px]">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="tagihan.status === 'lunas' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#fdf1e9] text-[#dd5b00]'"
                                        >
                                            {{ tagihan.status === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">{{ tanggal(tagihan.tanggal_lunas) ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
