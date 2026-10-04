<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Peserta = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    judul: string | null;
    ukuran_toga: string | null;
    pengajuan_id: number;
    status_mahasiswa: string | null;
    nomor_skl: string | null;
    skl_terbit_at: string | null;
    ipk: number | null;
    predikat: string | null;
};

const props = defineProps<{
    periode: { id: number; nama: string; tanggal_acara: string; tempat: string | null; batas_daftar: string; kuota: number | null; dibuka: boolean };
    peserta: Peserta[];
}>();
const page = usePage<{ flash?: { success?: string; error?: string } }>();

const belumSkl = computed(() => props.peserta.filter((p) => !p.nomor_skl).length);

// SKL mengubah status mahasiswa menjadi Lulus, jadi selalu dikonfirmasi.
const sklItem = ref<Peserta | 'semua' | null>(null);
const terbitkan = () => {
    const item = sklItem.value;
    if (!item) return;
    const url = item === 'semua' ? route('admin.periode-wisuda.skl-massal', props.periode.id) : route('admin.wisuda.skl', item.id);
    router.post(url, {}, { preserveScroll: true, onFinish: () => (sklItem.value = null) });
};
</script>

<template>
    <Head :title="`Wisuda ${props.periode.nama}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Periode Wisuda', href: route('admin.periode-wisuda.index') },
            { title: props.periode.nama, href: route('admin.periode-wisuda.show', props.periode.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Daftar Mahasiswa Wisuda</h1>
                        <p class="deskripsi-halaman">
                            {{ props.periode.nama }} · {{ formatTanggal(props.periode.tanggal_acara)
                            }}<span v-if="props.periode.tempat"> · {{ props.periode.tempat }}</span> · {{ props.peserta.length
                            }}{{ props.periode.kuota ? ` / ${props.periode.kuota}` : '' }} peserta
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <a :href="route('admin.periode-wisuda.cetak', props.periode.id)"><Download /> Cetak Daftar</a>
                        </Button>
                        <Button :disabled="!belumSkl" @click="sklItem = 'semua'">Generate SKL Semua ({{ belumSkl }})</Button>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1000px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Tugas Akhir</th>
                                    <th>Toga</th>
                                    <th>SKL</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, index) in props.peserta" :key="p.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ p.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ p.nim }} · {{ p.prodi }}</span>
                                        <span class="block text-xs text-[#a39e98]">Status: {{ p.status_mahasiswa }}</span>
                                    </td>
                                    <td class="max-w-[360px]">{{ p.judul }}</td>
                                    <td>{{ p.ukuran_toga }}</td>
                                    <td>
                                        <template v-if="p.nomor_skl">
                                            <a
                                                :href="route('berkas.skl', p.id)"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 font-medium text-[#0075de] hover:underline"
                                                ><FileText class="size-3.5" /> {{ p.nomor_skl }}</a
                                            >
                                            <span class="block text-xs text-[#a39e98]">IPK {{ p.ipk?.toFixed(2) }} · {{ p.predikat }}</span>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum terbit</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <Button v-if="!p.nomor_skl" size="sm" @click="sklItem = p">Generate SKL</Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.peserta.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">
                                        Belum ada peserta. Setujui pendaftaran di
                                        <Link :href="route('admin.daftar-wisuda.index')" class="text-[#0075de] hover:underline"
                                            >Pengajuan & Pendaftaran → Daftar Wisuda</Link
                                        >.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <AlertModal
            :open="!!sklItem"
            title="Generate SKL?"
            :description="
                sklItem === 'semua'
                    ? `SKL diterbitkan untuk ${belumSkl} peserta dan status mereka menjadi Lulus.`
                    : sklItem
                      ? `SKL ${sklItem.nama} diterbitkan dan statusnya menjadi Lulus. IPK dan predikat dibekukan saat ini.`
                      : ''
            "
            confirm-text="Generate"
            cancel-text="Batal"
            @update:open="!$event && (sklItem = null)"
            @confirm="terbitkan"
            @cancel="sklItem = null"
        />
    </AppLayout>
</template>
