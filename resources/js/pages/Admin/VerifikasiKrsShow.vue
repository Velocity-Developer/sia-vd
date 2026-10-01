<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import DialogBukaKunciKrs from '@/components/DialogBukaKunciKrs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_KRS, type RingkasanKrs } from '@/lib/krs';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Kelas = {
    id: number;
    kode_matkul: string | null;
    nama_matkul: string | null;
    semester_matkul: number | null;
    jenis: string | null;
    sks: number;
    kode_kelas: string | null;
    dosen: string | null;
    jadwal: string[];
};

const props = defineProps<{
    krs: RingkasanKrs & { id: number; tahun_akademik_id: number; tahun_akademik: string };
    mahasiswa: {
        user_id: number;
        nama: string | null;
        nim: string | null;
        prodi: string | null;
        angkatan: string | null;
        semester: number | null;
        status: string | null;
    };
    kelas: Kelas[];
    maksSks: number;
    ipsSebelumnya: { ips: number; tahun_akademik: string } | null;
    masaRevisiBerjalan: boolean;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const totalSks = computed(() => props.kelas.reduce((jumlah, k) => jumlah + k.sks, 0));
const status = computed(() => (props.krs.status ? STATUS_KRS[props.krs.status] : null));

const konfirmasiSetujui = ref(false);
const setujui = () =>
    router.post(route('admin.verifikasi-krs.setujui', props.krs.id), {}, { preserveScroll: true, onFinish: () => (konfirmasiSetujui.value = false) });
const revisiOpen = ref(false);
</script>

<template>
    <Head :title="`KRS ${mahasiswa.nama}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Verifikasi KRS', href: route('admin.verifikasi-krs.index', { tahun_akademik_id: krs.tahun_akademik_id }) },
            { title: mahasiswa.nama ?? 'KRS', href: route('admin.verifikasi-krs.show', krs.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">KRS {{ mahasiswa.nama }}</h1>
                        <p class="deskripsi-halaman">{{ mahasiswa.nim }} · {{ mahasiswa.prodi }} · {{ krs.tahun_akademik }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="krs.status !== 'perlu_revisi' || !krs.bisa_direvisi" variant="outline" @click="revisiOpen = true">
                            Kembalikan untuk Revisi
                        </Button>
                        <Button v-if="krs.status !== 'disetujui'" :disabled="!kelas.length" @click="konfirmasiSetujui = true">Setujui KRS</Button>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <section class="kartu grid gap-4 p-6 sm:grid-cols-4">
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.08em]">Status</p>
                        <span v-if="status" class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium" :class="status.kelas">{{
                            status.label
                        }}</span>
                        <p v-if="krs.diverifikasi_oleh" class="teks-bantu mt-1">
                            {{ formatTanggal(krs.diverifikasi_pada, false) }} · {{ krs.diverifikasi_oleh }}
                        </p>
                    </div>
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.08em]">Diajukan</p>
                        <p class="mt-1 text-sm font-medium">{{ formatTanggal(krs.disimpan_pada, false) }}</p>
                    </div>
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.08em]">Semester / Angkatan</p>
                        <p class="mt-1 text-sm font-medium">{{ mahasiswa.semester ?? '-' }} / {{ mahasiswa.angkatan ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.08em]">SKS Diambil</p>
                        <p class="mt-1 text-sm font-medium" :class="totalSks > maksSks ? 'text-[#b42318]' : ''">{{ totalSks }} / {{ maksSks }} SKS</p>
                        <p class="teks-bantu">
                            {{ ipsSebelumnya ? `IPS ${ipsSebelumnya.ips.toFixed(2)} (${ipsSebelumnya.tahun_akademik})` : 'Belum ada IPS sebelumnya' }}
                        </p>
                    </div>
                    <div v-if="krs.status === 'perlu_revisi'" class="sm:col-span-4">
                        <p class="alert-info">
                            <template v-if="krs.catatan_revisi">Catatan revisi: {{ krs.catatan_revisi }}. </template>
                            <template v-if="krs.bisa_direvisi"
                                >Mahasiswa bisa mengubah KRS sampai {{ formatTanggal(krs.batas_revisi, false) }}.</template
                            >
                            <template v-else>Masa revisi sudah berakhir; KRS terkunci dengan isi terakhir.</template>
                        </p>
                    </div>
                    <div v-else-if="krs.status === 'diajukan' && !masaRevisiBerjalan" class="sm:col-span-4">
                        <p class="alert-info">Masa revisi sudah berakhir. KRS tetap terkunci dan masih bisa disetujui.</p>
                    </div>
                </section>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th>SKS</th>
                                    <th>Dosen</th>
                                    <th>Jadwal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(k, index) in kelas" :key="k.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground"
                                            >{{ k.kode_matkul }} — {{ k.nama_matkul }}</span
                                        >
                                        <span class="block text-xs text-[#a39e98]"
                                            >Semester {{ k.semester_matkul ?? '-' }} · {{ k.jenis ?? '-' }}</span
                                        >
                                    </td>
                                    <td>{{ k.kode_kelas }}</td>
                                    <td>{{ k.sks }}</td>
                                    <td>{{ k.dosen ?? '-' }}</td>
                                    <td>
                                        <div v-for="(j, i) in k.jadwal" :key="i">{{ j }}</div>
                                        <span v-if="!k.jadwal.length">-</span>
                                    </td>
                                </tr>
                                <tr v-if="!kelas.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">KRS ini belum berisi kelas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="teks-bantu">
                    Admin juga bisa mengeluarkan mahasiswa dari kelas lewat menu Kelas Kuliah. Data lengkap mahasiswa ada di
                    <Link :href="route('admin.users.mahasiswa.show', mahasiswa.user_id)" class="text-[#0075de] hover:underline">detail mahasiswa</Link
                    >.
                </p>
            </div>
        </div>

        <AlertModal
            v-model:open="konfirmasiSetujui"
            title="Setujui KRS ini?"
            :description="`KRS ${mahasiswa.nama} (${totalSks} SKS) menjadi final dan tidak bisa diubah mahasiswa, kecuali dikembalikan untuk revisi.`"
            confirm-text="Setujui"
            cancel-text="Batal"
            @confirm="setujui"
        />
        <DialogBukaKunciKrs
            v-model:open="revisiOpen"
            judul="Kembalikan untuk Revisi"
            :nama="mahasiswa.nama ?? 'Mahasiswa'"
            :url="route('admin.verifikasi-krs.revisi', krs.id)"
            metode="post"
            :masa-revisi-berjalan="masaRevisiBerjalan"
            :batas-revisi="krs.batas_revisi"
            catatan-wajib
        />
    </AppLayout>
</template>
