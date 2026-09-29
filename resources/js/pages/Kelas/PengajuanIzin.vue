<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatJamDari, formatTanggal, infoStatusPresensi, jam } from '@/lib/presensi';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip } from 'lucide-vue-next';
import { ref } from 'vue';

type Pengajuan = {
    id: number;
    jenis: 'izin' | 'sakit';
    alasan: string;
    jumlah_lampiran: number;
    status: 'menunggu' | 'disetujui' | 'ditolak';
    catatan_dosen: string | null;
    diajukan_at: string | null;
    diproses_at: string | null;
    pemroses: string | null;
    nim: string;
    nama: string;
    status_presensi: string | null;
    pertemuan: { id: number; pertemuan_ke: number; tanggal: string; jam_mulai: string; kode_kelas: string; nama_matkul: string };
};

const props = defineProps<{
    peran: Peran;
    status: 'menunggu' | 'disetujui' | 'ditolak';
    pengajuan: { data: Pengajuan[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const rute = rutePeran(props.peran);
const tabs = [
    { value: 'menunggu', label: 'Menunggu' },
    { value: 'disetujui', label: 'Disetujui' },
    { value: 'ditolak', label: 'Ditolak' },
] as const;
const gantiStatus = (status: string) => router.get(rute('presensi.izin.index'), { status }, { preserveScroll: true });

const setujui = (item: Pengajuan) => router.put(rute('presensi.izin.proses', item.id), { keputusan: 'disetujui' }, { preserveScroll: true });

const penolakan = ref<Pengajuan | null>(null);
const tolakForm = useForm({ keputusan: 'ditolak', catatan_dosen: '' });
const bukaTolak = (item: Pengajuan) => {
    penolakan.value = item;
    tolakForm.reset();
    tolakForm.clearErrors();
};
const tolak = () => {
    if (!penolakan.value) return;
    tolakForm.put(rute('presensi.izin.proses', penolakan.value.id), { preserveScroll: true, onSuccess: () => (penolakan.value = null) });
};

const lampiran = (item: Pengajuan) => Array.from({ length: item.jumlah_lampiran }, (_, i) => route('berkas.izin', [item.id, i]));
</script>

<template>
    <Head title="Pengajuan Izin" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Presensi', href: rute('presensi.index') },
            { title: 'Pengajuan Izin', href: rute('presensi.izin.index') },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pengajuan Izin &amp; Sakit</h1>
                        <p class="deskripsi-halaman">
                            Pengajuan dari mahasiswa di kelas yang Anda ampu. Bila disetujui, status presensi pertemuan itu menjadi Izin atau Sakit
                            (tetap dihitung tidak hadir).
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div class="flex gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium dark:border-border dark:bg-card sm:w-fit">
                    <button
                        v-for="t in tabs"
                        :key="t.value"
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="
                            props.status === t.value
                                ? 'bg-[#0075de] text-white'
                                : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                        "
                        @click="gantiStatus(t.value)"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <div v-for="item in props.pengajuan.data" :key="item.id" class="kartu p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-black dark:text-foreground">
                                {{ item.nama }} <span class="font-normal text-[#a39e98]">· {{ item.nim }}</span>
                            </p>
                            <p class="text-sm text-[#615d59] dark:text-muted-foreground">
                                {{ item.pertemuan.nama_matkul }} · {{ item.pertemuan.kode_kelas }} ·
                                <Link :href="rute('presensi.pertemuan.show', item.pertemuan.id)" class="text-[#0075de] hover:underline"
                                    >Pertemuan {{ item.pertemuan.pertemuan_ke }}</Link
                                >
                                ({{ formatTanggal(item.pertemuan.tanggal) }}, {{ jam(item.pertemuan.jam_mulai) }})
                            </p>
                        </div>
                        <span class="rounded border px-2 py-0.5 text-xs font-medium" :class="infoStatusPresensi(item.jenis)?.kelas">
                            {{ infoStatusPresensi(item.jenis)?.label }}
                        </span>
                    </div>

                    <p class="mt-3 whitespace-pre-line text-sm text-[#31302e] dark:text-foreground">{{ item.alasan }}</p>
                    <div v-if="item.jumlah_lampiran" class="mt-2 flex flex-wrap gap-3 text-sm">
                        <a
                            v-for="(url, i) in lampiran(item)"
                            :key="url"
                            :href="url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 text-[#0075de] hover:underline"
                        >
                            <Paperclip class="size-4" /> Lampiran {{ i + 1 }}
                        </a>
                    </div>
                    <p class="teks-bantu mt-2">
                        Diajukan {{ formatTanggal(item.diajukan_at, false) }} {{ formatJamDari(item.diajukan_at) }}
                        <template v-if="item.status_presensi">
                            · status presensi saat ini: {{ infoStatusPresensi(item.status_presensi)?.label }}</template
                        >
                        <template v-if="item.diproses_at">
                            · diproses {{ item.pemroses }} {{ formatTanggal(item.diproses_at, false) }}
                            {{ formatJamDari(item.diproses_at) }}</template
                        >
                    </p>
                    <p v-if="item.catatan_dosen" class="mt-1 text-sm text-[#dd5b00]">Catatan: {{ item.catatan_dosen }}</p>

                    <p
                        v-if="item.status === 'menunggu' && (item.status_presensi === 'hadir' || item.status_presensi === 'terlambat')"
                        class="alert-gagal mt-3"
                    >
                        Mahasiswa ini sudah tercatat {{ infoStatusPresensi(item.status_presensi)?.label.toLowerCase() }} di pertemuan tersebut.
                        Menyetujui pengajuan tidak akan mengubah status hadirnya.
                    </p>
                    <div v-if="item.status === 'menunggu'" class="mt-4 flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="destructive" @click="bukaTolak(item)">Tolak</Button>
                        <Button type="button" @click="setujui(item)">Setujui</Button>
                    </div>
                </div>

                <p v-if="!props.pengajuan.data.length" class="kartu tabel-kosong">
                    Tidak ada pengajuan {{ tabs.find((t) => t.value === props.status)?.label.toLowerCase() }}.
                </p>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />

                <div v-if="penolakan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="penolakan = null">
                    <form class="kartu flex w-full max-w-md flex-col gap-4 p-6" @submit.prevent="tolak">
                        <div>
                            <h2 class="judul-bagian">Tolak pengajuan {{ penolakan.nama }}?</h2>
                            <p class="teks-bantu mt-1">
                                Catatan ini ditampilkan kepada mahasiswa. Ia masih bisa mengajukan ulang selama batas waktu belum lewat.
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Input
                                v-model="tolakForm.catatan_dosen"
                                maxlength="255"
                                placeholder="mis. Lampirkan surat dokter"
                                aria-label="Catatan penolakan"
                                required
                            />
                            <InputError :message="tolakForm.errors.catatan_dosen" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="penolakan = null">Kembali</Button>
                            <Button type="submit" variant="destructive" :disabled="tolakForm.processing">Tolak</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
