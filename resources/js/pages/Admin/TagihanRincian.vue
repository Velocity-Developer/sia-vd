<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UnggahBuktiBayar from '@/components/UnggahBuktiBayar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_TAGIHAN_REMIDI } from '@/lib/tagihanRemidi';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Item = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };
type Tagihan = {
    id: number;
    status: 'belum_bayar' | 'menunggu_verifikasi' | 'lunas' | 'ditolak';
    total: number;
    rincian_manual: boolean;
    tanggal_lunas: string | null;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
    diverifikasi_oleh: string | null;
    diverifikasi_at: string | null;
    items: Item[];
};

const props = defineProps<{
    mahasiswa: { id: number; nama: string | null; nim: string | null; prodi: string | null; angkatan: number | null };
    tahunAkademik: string | null;
    tahunAkademikId: number | null;
    tagihan: Tagihan | null;
    sks: number;
    kuota: number;
    krsTersimpan: boolean;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const lunas = computed(() => props.tagihan?.status === 'lunas');
const tanggal = (nilai: string | null) => (nilai ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(nilai)) : '-');

/** Rincian diketik sebagai teks karena isian number mengembalikan string. */
const form = useForm({
    tahun_akademik_id: props.tahunAkademikId,
    items: (props.tagihan?.items ?? []).map((item) => ({ nama: item.nama, subtotal: String(item.subtotal) })),
});

const totalBaru = computed(() => form.items.reduce((jumlah, item) => jumlah + Number(item.subtotal || 0), 0));

// Galat baris bernama "items.0.nama"; dibaca lewat peta agar tidak perlu indeks bertipe longgar.
const galat = (index: number, kolom: 'nama' | 'subtotal'): string | undefined =>
    (form.errors as Record<string, string | undefined>)[`items.${index}.${kolom}`];

const tambahBaris = () => form.items.push({ nama: '', subtotal: '0' });
const hapusBaris = (index: number) => form.items.splice(index, 1);

const simpan = () => {
    form.transform((data) => ({
        ...data,
        items: data.items.map((item) => ({ nama: item.nama, subtotal: Number(item.subtotal || 0) })),
    })).put(route('admin.tagihan.rincian.simpan', props.mahasiswa.id), { preserveScroll: true });
};

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
</script>

<template>
    <Head title="Rincian Tagihan" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Tagihan Mahasiswa', href: route('admin.tagihan.index') },
            { title: 'Rincian', href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.mahasiswa.nama }}</h1>
                        <p class="deskripsi-halaman">
                            {{ props.mahasiswa.nim }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan }} ·
                            {{ props.tahunAkademik ?? 'Tahun akademik tidak dipilih' }}
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.tagihan.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="kartu p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Status</p>
                            <span
                                v-if="props.tagihan"
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="STATUS_TAGIHAN_REMIDI[props.tagihan.status].kelas"
                            >
                                {{ STATUS_TAGIHAN_REMIDI[props.tagihan.status].label }}
                            </span>
                            <span v-else class="mt-1 inline-block rounded-full bg-[#f6f5f4] px-2 py-0.5 text-xs font-medium text-[#615d59]"
                                >Belum Terbit</span
                            >
                        </div>
                        <div class="text-right">
                            <p class="teks-bantu font-medium uppercase tracking-[0.06em]">Total Tagihan</p>
                            <p class="text-2xl font-bold tabular-nums text-black dark:text-foreground">{{ rupiah(props.tagihan?.total ?? 0) }}</p>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-[#615d59]">
                        Kuota SKS (dasar biaya per SKS): <span class="font-medium text-black">{{ props.kuota }} SKS</span> · SKS yang sudah diambil:
                        {{ props.sks }} SKS · KRS {{ props.krsTersimpan ? 'sudah disimpan mahasiswa (terkunci)' : 'belum disimpan mahasiswa' }}.
                    </p>

                    <div v-if="props.tagihan" class="mt-4 space-y-2 border-t border-[#e6e6e6] pt-4 text-sm text-[#615d59]">
                        <p v-if="props.tagihan.tanggal_lunas">
                            Lunas {{ tanggal(props.tagihan.tanggal_lunas)
                            }}<template v-if="props.tagihan.diverifikasi_oleh"> · diverifikasi {{ props.tagihan.diverifikasi_oleh }}</template
                            >.
                        </p>
                        <p v-if="props.tagihan.status === 'ditolak'" class="text-[#dd5b00]">Bukti ditolak: {{ props.tagihan.alasan_tolak }}</p>
                        <p v-if="props.tagihan.ada_bukti">
                            Bukti diunggah {{ tanggal(props.tagihan.bukti_diunggah_at) }}.
                            <a
                                :href="route('berkas.bukti-semester', props.tagihan.id)"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-[#0075de] hover:underline"
                                >Lihat bukti</a
                            >
                        </p>
                        <p v-else>Belum ada bukti bayar.</p>
                        <template v-if="!lunas">
                            <p>Mahasiswa menyerahkan bukti langsung (mis. kuitansi loket)? Unggah di sini; tagihan langsung ditandai lunas.</p>
                            <UnggahBuktiBayar
                                rute="admin.tagihan.bukti"
                                :id="props.tagihan.id"
                                :ada-bukti="false"
                                label-kirim="Unggah & Tandai Lunas"
                            />
                        </template>
                    </div>
                </div>

                <form class="kartu p-6" @submit.prevent="simpan">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="judul-bagian">Rincian Tagihan</h2>
                            <p class="text-sm text-[#615d59]">
                                Boleh diketik manual, mis. keringanan atau biaya tambahan. Total ikut menyesuaikan. Rincian manual tidak ditimpa saat
                                tagihan diterbitkan ulang.
                            </p>
                            <p v-if="props.tagihan?.rincian_manual" class="teks-bantu font-medium">Rincian saat ini diketik manual.</p>
                            <p v-if="lunas" class="text-xs font-medium text-[#dd5b00]">
                                Tagihan sudah lunas, rincian tidak bisa diubah. Batalkan status lunasnya dulu dari daftar tagihan.
                            </p>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="tambahBaris"> <Plus /> Tambah Baris </Button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="grid content-start gap-3 rounded-lg border border-[#e6e6e6] p-3 dark:border-border sm:grid-cols-[1fr_200px_auto] sm:items-end"
                        >
                            <div class="grid gap-2">
                                <Label :for="`nama-${index}`" class="label-isian">Komponen</Label>
                                <Input :id="`nama-${index}`" v-model="item.nama" placeholder="SPP Tetap" required />
                                <InputError :message="galat(index, 'nama')" />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`subtotal-${index}`" class="label-isian">Nominal</Label>
                                <Input :id="`subtotal-${index}`" v-model="item.subtotal" type="number" min="0" required />
                                <span class="teks-bantu">{{ rupiah(Number(item.subtotal)) }}</span>
                                <InputError :message="galat(index, 'subtotal')" />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="justify-self-end text-[#dd5b00]"
                                title="Hapus baris"
                                aria-label="Hapus baris"
                                @click="hapusBaris(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>

                        <p
                            v-if="!form.items.length"
                            class="rounded-lg border border-dashed border-[#e6e6e6] px-4 py-6 text-center text-sm text-[#615d59] dark:border-border"
                        >
                            Belum ada rincian. Tambahkan baris (tagihan ikut diterbitkan saat disimpan), atau terbitkan tagihan massal dari halaman
                            daftar.
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[#e6e6e6] pt-4 dark:border-border">
                        <p class="text-sm text-[#615d59]">
                            Total baru: <span class="text-base font-bold tabular-nums text-black">{{ rupiah(totalBaru) }}</span>
                        </p>
                        <Button type="submit" :disabled="form.processing || !props.tahunAkademikId || lunas || !form.items.length">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            Simpan Rincian
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
