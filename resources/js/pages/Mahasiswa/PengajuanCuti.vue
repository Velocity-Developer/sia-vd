<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputBerkas from '@/components/tugas-akhir/InputBerkas.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { JENIS_PENGAJUAN_CUTI, STATUS_PENGAJUAN, labelPeristiwa, type JenisPengajuanCuti, type StatusPengajuan } from '@/lib/tugasAkhir';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Keadaan = 'menunggu' | 'perbaikan' | 'baru' | 'belum_memenuhi';
type Pengajuan = {
    id: number;
    status: StatusPengajuan;
    isian: Record<string, any>;
    tahun_akademik: string | null;
    lampiran: string[];
    catatan: string | null;
    diajukan_at: string | null;
    diproses_at: string | null;
};
type Tahap = { keadaan: Keadaan; pengajuan: Pengajuan | null };

const props = defineProps<{
    mahasiswa: { status: string | null; jumlah_cuti: number; maks_cuti: number };
    cuti: Tahap & { alasan: string | null; tahunOptions: { id: number; nama: string; aktif: boolean; batas: string | null }[] };
    aktifKembali: Tahap;
    biaya: { nama: string; nominal: number; keterangan: string | null }[];
    riwayat: {
        id: number;
        jenis: JenisPengajuanCuti;
        status: StatusPengajuan;
        tahun_akademik: string | null;
        riwayat: { status: string; catatan: string | null; oleh: string | null; waktu: string | null }[];
    }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const rupiah = (n: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
const dokumen = 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png';

const terbuka = (t: Tahap) => t.keadaan === 'baru' || t.keadaan === 'perbaikan';
// Isian lama ditampilkan saat perbaikan (untuk diubah) dan saat menunggu (terkunci).
const isianLama = (t: Tahap) => (t.keadaan === 'perbaikan' || t.keadaan === 'menunggu' ? t.pengajuan : null);
const sudahAda = (t: Tahap, kunci: string) => !!isianLama(t)?.lampiran.includes(kunci);

const cuti = computed(() => props.cuti);
const lamaCuti = isianLama(props.cuti)?.isian;
const formCuti = useForm({
    tahun_akademik_id: (lamaCuti?.tahun_akademik_id ?? (props.cuti.tahunOptions.length === 1 ? props.cuti.tahunOptions[0].id : '')) as number | '',
    alasan: lamaCuti?.alasan ?? '',
    bukti_bayar: null as File | null,
    dokumen_pendukung: null as File | null,
});
// Input berkas dipasang ulang (dikosongkan) setelah terkirim.
const versiBerkas = ref(0);
const kirimCuti = () =>
    formCuti.post(route('mahasiswa.pengajuan-cuti.ajukan'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            formCuti.bukti_bayar = formCuti.dokumen_pendukung = null;
            versiBerkas.value++;
        },
    });

const aktif = computed(() => props.aktifKembali);
const formAktif = useForm({ keterangan: isianLama(props.aktifKembali)?.isian.keterangan ?? '' });
const kirimAktif = () => formAktif.post(route('mahasiswa.pengajuan-cuti.aktif-kembali'), { preserveScroll: true });
const infoBiaya = computed(() => props.biaya.map((b) => `${b.nama} ${rupiah(b.nominal)}`).join(', '));
</script>

<template>
    <Head title="Pengajuan Cuti" />
    <AppLayout :breadcrumbs="[{ title: 'Pengajuan Cuti', href: route('mahasiswa.pengajuan-cuti') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pengajuan Cuti</h1>
                        <p class="deskripsi-halaman">
                            Selama cuti Anda tetap bisa masuk untuk melihat KHS dan transkrip, tetapi tidak ditagih dan tidak bisa mengisi KRS.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <section class="kartu grid gap-4 p-6 sm:grid-cols-3">
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.04em]">Status Anda</p>
                        <p class="mt-1 text-lg font-semibold text-black dark:text-foreground">{{ props.mahasiswa.status ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.04em]">Cuti terpakai</p>
                        <p class="mt-1 text-lg font-semibold text-black dark:text-foreground">
                            {{ props.mahasiswa.jumlah_cuti }} dari {{ props.mahasiswa.maks_cuti }} semester
                        </p>
                    </div>
                    <div>
                        <p class="teks-bantu uppercase tracking-[0.04em]">Biaya cuti</p>
                        <p class="mt-1 text-sm text-black dark:text-foreground">{{ infoBiaya || 'Belum diatur' }}</p>
                    </div>
                </section>

                <!-- Aktif kembali: hanya untuk mahasiswa berstatus Cuti -->
                <section v-if="props.mahasiswa.status === 'Cuti' || aktif.keadaan === 'menunggu'" class="kartu p-6">
                    <h2 class="judul-bagian">Aktif Kembali</h2>
                    <div v-if="aktif.keadaan === 'menunggu'" class="alert-info mt-4" role="status">
                        Pengajuan dikirim {{ formatTanggal(aktif.pengajuan?.diajukan_at, false) }} dan sedang menunggu diproses admin.
                    </div>
                    <div v-else-if="aktif.keadaan === 'perbaikan'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Diminta perbaikan:</span> {{ aktif.pengajuan?.catatan }}
                    </div>
                    <div v-else-if="aktif.keadaan === 'baru' && aktif.pengajuan?.status === 'ditolak'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Pengajuan sebelumnya ditolak:</span> {{ aktif.pengajuan.catatan }} Anda bisa mengajukan lagi.
                    </div>

                    <form class="mt-4" @submit.prevent="kirimAktif">
                        <fieldset :disabled="!terbuka(aktif) || formAktif.processing" class="grid gap-4 disabled:opacity-60">
                            <div class="grid gap-2">
                                <Label for="keterangan" class="label-isian"
                                    >Keterangan <span class="font-normal text-[#a39e98]">(opsional)</span></Label
                                >
                                <textarea id="keterangan" v-model="formAktif.keterangan" rows="3" maxlength="1000" class="isian isian-area" />
                                <InputError :message="formAktif.errors.keterangan" />
                            </div>
                            <p class="teks-bantu">Setelah disetujui admin, status Anda kembali Aktif dan bisa mengisi KRS lagi.</p>
                            <div v-if="terbuka(aktif)" class="flex justify-end">
                                <Button type="submit">
                                    {{ aktif.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Ajukan Aktif Kembali' }}
                                </Button>
                            </div>
                        </fieldset>
                    </form>
                </section>

                <section v-if="props.mahasiswa.status !== 'Cuti'" class="kartu p-6">
                    <h2 class="judul-bagian">Ajukan Cuti</h2>
                    <div v-if="cuti.keadaan === 'menunggu'" class="alert-info mt-4" role="status">
                        Pengajuan cuti {{ cuti.pengajuan?.tahun_akademik }} dikirim {{ formatTanggal(cuti.pengajuan?.diajukan_at, false) }} dan sedang
                        menunggu diproses admin.
                    </div>
                    <div v-else-if="cuti.keadaan === 'belum_memenuhi'" class="alert-gagal mt-4" role="status">
                        {{ cuti.alasan }}
                    </div>
                    <div v-else-if="cuti.keadaan === 'perbaikan'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Diminta perbaikan:</span> {{ cuti.pengajuan?.catatan }}
                    </div>
                    <div v-else-if="cuti.pengajuan?.status === 'ditolak'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Pengajuan sebelumnya ditolak:</span> {{ cuti.pengajuan.catatan }} Anda bisa mengajukan lagi.
                    </div>

                    <form class="mt-5" @submit.prevent="kirimCuti">
                        <fieldset :disabled="!terbuka(cuti) || formCuti.processing" class="grid gap-4 disabled:opacity-60">
                            <div class="grid gap-2 sm:w-80">
                                <Label for="tahun_akademik_id" class="label-isian">Semester yang ingin dicutikan</Label>
                                <p v-if="cuti.keadaan === 'menunggu'" class="text-sm text-black dark:text-foreground">
                                    {{ cuti.pengajuan?.tahun_akademik }}
                                </p>
                                <select v-else id="tahun_akademik_id" v-model="formCuti.tahun_akademik_id" class="isian isian-pilih" required>
                                    <option value="" disabled>Pilih semester</option>
                                    <option v-for="t in cuti.tahunOptions" :key="t.id" :value="t.id">
                                        {{ t.nama }}{{ t.aktif ? ' (sedang berjalan)' : '' }}
                                    </option>
                                </select>
                                <p v-if="terbuka(cuti)" class="teks-bantu">Hanya semester yang periode pengajuan cutinya sedang dibuka.</p>
                                <InputError :message="formCuti.errors.tahun_akademik_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="alasan" class="label-isian">Alasan cuti</Label>
                                <textarea id="alasan" v-model="formCuti.alasan" rows="4" maxlength="2000" class="isian isian-area" required />
                                <InputError :message="formCuti.errors.alasan" />
                            </div>
                            <div class="grid items-start gap-4 sm:grid-cols-2">
                                <InputBerkas
                                    id="bukti_bayar"
                                    :key="`bukti_bayar-${versiBerkas}`"
                                    :label="`Bukti bayar cuti${infoBiaya ? ' — ' + infoBiaya : ''}`"
                                    :accept="dokumen"
                                    :pengajuan-id="cuti.pengajuan?.id"
                                    :sudah-ada="sudahAda(cuti, 'bukti_bayar')"
                                    :wajib="cuti.keadaan === 'baru'"
                                    :terkunci="!terbuka(cuti)"
                                    :error="formCuti.errors.bukti_bayar"
                                    @pilih="formCuti.bukti_bayar = $event"
                                />
                                <InputBerkas
                                    id="dokumen_pendukung"
                                    :key="`dokumen_pendukung-${versiBerkas}`"
                                    label="Dokumen pendukung (opsional), mis. surat keterangan"
                                    :accept="dokumen"
                                    :pengajuan-id="cuti.pengajuan?.id"
                                    :sudah-ada="sudahAda(cuti, 'dokumen_pendukung')"
                                    :wajib="false"
                                    :terkunci="!terbuka(cuti)"
                                    :error="formCuti.errors.dokumen_pendukung"
                                    @pilih="formCuti.dokumen_pendukung = $event"
                                />
                            </div>
                            <p class="teks-bantu">
                                Setelah disetujui admin, status Anda menjadi Cuti (langsung bila semesternya sedang berjalan, atau saat semester itu
                                dimulai). Untuk kembali kuliah, ajukan aktif kembali dari halaman ini.
                            </p>
                            <div v-if="terbuka(cuti)" class="flex justify-end">
                                <Button type="submit">
                                    {{ cuti.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Ajukan Cuti' }}
                                </Button>
                            </div>
                        </fieldset>
                    </form>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Riwayat Pengajuan</h2>
                    <p v-if="!props.riwayat.length" class="mt-3 text-sm text-[#615d59] dark:text-muted-foreground">Belum ada pengajuan.</p>
                    <div
                        v-for="r in props.riwayat"
                        :key="r.id"
                        class="mt-4 border-t border-[#e6e6e6] pt-4 first:border-0 first:pt-0 dark:border-border"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-black dark:text-foreground"
                                >{{ JENIS_PENGAJUAN_CUTI[r.jenis] }}<span v-if="r.tahun_akademik"> · {{ r.tahun_akademik }}</span></span
                            >
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_PENGAJUAN[r.status].kelas">{{
                                STATUS_PENGAJUAN[r.status].label
                            }}</span>
                        </div>
                        <ol class="mt-2 grid gap-1.5 border-l border-[#e6e6e6] pl-4 dark:border-border">
                            <li v-for="(h, i) in r.riwayat" :key="i" class="text-sm text-[#31302e] dark:text-foreground">
                                <span class="text-xs text-[#a39e98]">{{ formatTanggal(h.waktu, false) }} {{ h.waktu?.slice(11, 16) }}</span>
                                · {{ labelPeristiwa(h.status, i === 0) }}
                                <span v-if="h.status !== 'dikirim' && h.oleh" class="text-[#615d59]">oleh {{ h.oleh }}</span>
                                <span v-if="h.catatan" class="teks-bantu block">{{ h.catatan }}</span>
                            </li>
                        </ol>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
