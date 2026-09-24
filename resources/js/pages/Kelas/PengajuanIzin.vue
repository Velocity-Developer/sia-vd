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
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Pengajuan Izin &amp; Sakit</h1>
                    <p class="text-sm text-[#615d59]">
                        Pengajuan dari mahasiswa di kelas yang Anda ampu. Bila disetujui, status presensi pertemuan itu menjadi Izin atau Sakit (tetap
                        dihitung tidak hadir).
                    </p>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div class="flex gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium sm:w-fit">
                    <button
                        v-for="t in tabs"
                        :key="t.value"
                        type="button"
                        class="flex-1 rounded-md px-4 py-2 sm:flex-none"
                        :class="props.status === t.value ? 'bg-[#0075de] text-white' : 'text-[#615d59] hover:bg-[#f6f5f4]'"
                        @click="gantiStatus(t.value)"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <div v-for="item in props.pengajuan.data" :key="item.id" class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-black">
                                {{ item.nama }} <span class="text-sm font-normal text-[#a39e98]">· {{ item.nim }}</span>
                            </p>
                            <p class="text-sm text-[#615d59]">
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

                    <p class="mt-3 whitespace-pre-line text-[15px] text-[#31302e]">{{ item.alasan }}</p>
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
                    <p class="mt-2 text-xs text-[#a39e98]">
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
                        class="mt-3 rounded-lg bg-[#fff6e0] px-3 py-2 text-sm text-[#8a5a00]"
                    >
                        Mahasiswa ini sudah tercatat {{ infoStatusPresensi(item.status_presensi)?.label.toLowerCase() }} di pertemuan tersebut.
                        Menyetujui pengajuan tidak akan mengubah status hadirnya.
                    </p>
                    <div v-if="item.status === 'menunggu'" class="mt-4 flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" class="text-[#dd5b00]" @click="bukaTolak(item)">Tolak</Button>
                        <Button type="button" class="bg-[#1a7f37] text-white hover:bg-[#146c2e]" @click="setujui(item)">Setujui</Button>
                    </div>
                </div>

                <p
                    v-if="!props.pengajuan.data.length"
                    class="rounded-xl border border-dashed border-[#e6e6e6] bg-white px-4 py-10 text-center text-sm text-[#615d59]"
                >
                    Tidak ada pengajuan {{ tabs.find((t) => t.value === props.status)?.label.toLowerCase() }}.
                </p>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />

                <div v-if="penolakan" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="penolakan = null">
                    <form class="flex w-full max-w-md flex-col gap-4 rounded-xl bg-white p-6" @submit.prevent="tolak">
                        <div>
                            <h2 class="text-lg font-semibold">Tolak pengajuan {{ penolakan.nama }}?</h2>
                            <p class="mt-1 text-sm text-[#615d59]">
                                Catatan ini ditampilkan kepada mahasiswa. Ia masih bisa mengajukan ulang selama batas waktu belum lewat.
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <Input
                                v-model="tolakForm.catatan_dosen"
                                maxlength="255"
                                placeholder="mis. Lampirkan surat dokter"
                                class="h-10"
                                required
                            />
                            <InputError :message="tolakForm.errors.catatan_dosen" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="penolakan = null">Kembali</Button>
                            <Button type="submit" class="bg-[#dd5b00] text-white hover:bg-[#b84c00]" :disabled="tolakForm.processing">Tolak</Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
