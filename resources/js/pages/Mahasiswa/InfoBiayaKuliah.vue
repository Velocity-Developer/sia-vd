<script setup lang="ts">
import DaftarTagihanBerbukti, { type TagihanBerbukti } from '@/components/DaftarTagihanBerbukti.vue';
import UnggahBuktiBayar from '@/components/UnggahBuktiBayar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_TAGIHAN_REMIDI, type Rincian, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { Head, Link, usePage } from '@inertiajs/vue3';

type Item = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };
type Tagihan = {
    id: number;
    tahun_akademik: string;
    status: 'belum_bayar' | 'menunggu_verifikasi' | 'lunas' | 'ditolak';
    total: number;
    tanggal_lunas: string | null;
    boleh_unggah: boolean;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
    items: Item[];
};

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
    tagihanSusulan: TagihanBerbukti[];
    biayaTugasAkhir: Record<'pendadaran' | 'wisuda', { nama: string; nominal: number; keterangan: string | null }[]>;
}>();
const biayaInfo = [
    { kunci: 'pendadaran', judul: 'Pendadaran' },
    { kunci: 'wisuda', judul: 'Wisuda' },
] as const;

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
const tanggal = (nilai: string | null) => (nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(nilai)) : null);
const statusTagihan = (tagihan: Tagihan | null) => STATUS_TAGIHAN_REMIDI[tagihan?.status ?? 'belum_bayar'];
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

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
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
                                v-if="props.semesterBerjalan"
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="statusTagihan(props.semesterBerjalan).kelas"
                            >
                                {{ statusTagihan(props.semesterBerjalan).label }}
                            </span>
                        </div>
                    </div>

                    <p v-if="props.semesterBerjalan?.tanggal_lunas" class="mt-3 text-sm text-[#615d59]">
                        Dinyatakan lunas pada {{ tanggal(props.semesterBerjalan.tanggal_lunas) }}.
                    </p>
                    <p v-if="props.semesterBerjalan?.status === 'menunggu_verifikasi'" class="mt-3 text-sm text-[#005bab]">
                        Bukti bayar sedang diperiksa bagian keuangan. Anda masih bisa menggantinya bila salah unggah.
                    </p>
                    <p v-if="props.semesterBerjalan?.status === 'ditolak'" class="mt-3 text-sm text-[#b42318]">
                        Bukti ditolak: {{ props.semesterBerjalan.alasan_tolak }}. Silakan unggah ulang.
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

                    <div
                        v-if="props.semesterBerjalan && (props.semesterBerjalan.ada_bukti || props.semesterBerjalan.boleh_unggah)"
                        class="mt-4 space-y-2"
                    >
                        <p v-if="props.semesterBerjalan.ada_bukti" class="text-sm text-[#615d59]">
                            Bukti diunggah {{ tanggal(props.semesterBerjalan.bukti_diunggah_at) }}.
                            <a
                                :href="route('berkas.bukti-semester', props.semesterBerjalan.id)"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-[#0075de] hover:underline"
                                >Lihat bukti</a
                            >
                        </p>
                        <template v-if="props.semesterBerjalan.boleh_unggah">
                            <p class="text-sm text-[#615d59]">Sudah membayar? Unggah bukti transfer atau kuitansi (PDF/JPG/PNG, maks 5 MB).</p>
                            <UnggahBuktiBayar
                                rute="mahasiswa.tagihan-semester.bukti"
                                :id="props.semesterBerjalan.id"
                                :ada-bukti="props.semesterBerjalan.ada_bukti"
                            />
                        </template>
                    </div>
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

                <DaftarTagihanBerbukti
                    judul="Tagihan Remidi"
                    keterangan="Unggah bukti bayar sebelum batas bayar. Jadwal remidi terbuka setelah admin memverifikasi."
                    :tagihan="props.tagihanRemidi.map((t) => ({ ...t, judul: t.matkul }))"
                    rute-unggah="mahasiswa.tagihan-remidi.bukti"
                    rute-bukti="berkas.bukti-remidi"
                    pesan-gugur="Batas bayar sudah lewat, Anda tidak terdaftar sebagai peserta remidi mata kuliah ini."
                />
                <DaftarTagihanBerbukti
                    judul="Tagihan Ujian Susulan"
                    keterangan="Unggah bukti bayar sebelum batas bayar. Jadwal ujian susulan muncul di Jadwal Ujian setelah admin memverifikasi."
                    :tagihan="props.tagihanSusulan"
                    rute-unggah="mahasiswa.tagihan-susulan.bukti"
                    rute-bukti="berkas.bukti-susulan"
                    pesan-gugur="Batas bayar sudah lewat, hak ujian susulan Anda gugur."
                    pesan-dibatalkan="Anda tercatat mengikuti ujian utama, jadi tagihan susulan ini dibatalkan."
                />

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-black">Biaya Pendadaran & Wisuda</h2>
                    <p class="mt-1 text-sm text-[#615d59]">
                        Informasi saja, tidak ditagihkan di sini. Bayar sesuai nominal, lalu unggah bukti bayarnya di form pendaftaran pendadaran atau
                        wisuda (menu
                        <Link :href="route('mahasiswa.tugas-akhir')" class="font-medium text-[#0075de] hover:underline">Tugas Akhir & Wisuda</Link>).
                    </p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div v-for="b in biayaInfo" :key="b.kunci" class="rounded-lg border border-[#e6e6e6] p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">{{ b.judul }}</p>
                            <template v-if="props.biayaTugasAkhir[b.kunci].length">
                                <div v-for="item in props.biayaTugasAkhir[b.kunci]" :key="item.nama" class="mt-2">
                                    <p class="flex justify-between gap-2 text-sm">
                                        <span class="text-[#31302e]">{{ item.nama }}</span>
                                        <span class="font-semibold text-black">{{ rupiah(item.nominal) }}</span>
                                    </p>
                                    <p v-if="item.keterangan" class="text-xs text-[#a39e98]">{{ item.keterangan }}</p>
                                </div>
                            </template>
                            <p v-else class="mt-2 text-sm text-[#615d59]">Belum ada informasi biaya. Hubungi bagian keuangan.</p>
                        </div>
                    </div>
                </section>

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
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusTagihan(tagihan).kelas"
                                        >
                                            {{ statusTagihan(tagihan).label }}
                                        </span>
                                        <span v-if="tagihan.status === 'ditolak'" class="mt-1 block text-xs text-[#b42318]"
                                            >Ditolak: {{ tagihan.alasan_tolak }}</span
                                        >
                                        <UnggahBuktiBayar
                                            v-if="tagihan.boleh_unggah"
                                            class="mt-2"
                                            rute="mahasiswa.tagihan-semester.bukti"
                                            :id="tagihan.id"
                                            :ada-bukti="tagihan.ada_bukti"
                                        />
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
