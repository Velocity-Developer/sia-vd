<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import InputBerkas from '@/components/tugas-akhir/InputBerkas.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { STATUS_PENGAJUAN, labelPeristiwa, type StatusPengajuan } from '@/lib/tugasAkhir';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

type Jenis = 'kkm' | 'ppl' | 'kompre' | 'sidang';
type Keadaan = 'selesai' | 'menunggu' | 'perbaikan' | 'baru' | 'belum_memenuhi';

const props = defineProps<{
    jenis: Jenis;
    judul: string;
    rute: string;
    label: string;
    labelJudul: string;
    jenisKkmOptions: { id: string; name: string }[];
    gelombangOptions: { id: number; name: string; sisa_kuota: number | null }[];
    gelombangDipilih: { id: number; nama: string; tanggal_ujian: string } | null;
    judulAwal: string | null;
    keadaan: Keadaan;
    pengajuan: {
        id: number;
        status: StatusPengajuan;
        isian: Record<string, any>;
        lampiran: string[];
        catatan: string | null;
        diajukan_at: string | null;
        diproses_at: string | null;
    } | null;
    riwayat: {
        id: number;
        jenis: string;
        status: StatusPengajuan;
        riwayat: { status: string; catatan: string | null; oleh: string | null; waktu: string | null }[];
    }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const dokumen = 'application/pdf,.pdf,image/jpeg,image/png,.jpg,.jpeg,.png';
const label = props.label;
const terbuka = props.keadaan === 'baru' || props.keadaan === 'perbaikan';
// Isian lama ditampilkan saat perbaikan (untuk diubah), menunggu, dan sesudah disetujui (terkunci).
const lama = props.keadaan === 'baru' ? null : props.pengajuan;
const sudahAda = (kunci: string) => !!lama?.lampiran.includes(kunci);

const tindakLanjut: Record<Jenis, string> = {
    kkm: 'Setelah disetujui, Anda dimasukkan ke KRS mata kuliah KKM; kegiatannya dilaksanakan di luar sistem dan nilainya diisi admin.',
    ppl: 'Setelah disetujui, PPL dilaksanakan di luar sistem; nilainya diisi dosen pengampu mata kuliah PPL.',
    kompre: 'Setelah disetujui, ujian komprehensif dilaksanakan di luar sistem pada tanggal gelombang yang Anda pilih.',
    sidang: 'Setelah disetujui, jadwal sidang dan penguji diatur di luar sistem; nilai TA/Skripsi diisi lewat Nilai Semester.',
};

const deskripsi: Record<Jenis, string> = {
    kkm: 'Ajukan judul Kuliah Kerja Mahasiswa (KKM, PKL, atau KKN). Admin memeriksa judul dan berkas Anda.',
    ppl: 'Daftar Praktek Pengalaman Lapangan (PPL). Admin memeriksa tempat PPL dan berkas Anda.',
    kompre: 'Daftar Ujian Komprehensif pada gelombang yang sedang dibuka. Admin memeriksa berkas Anda.',
    sidang: 'Daftar sidang tugas akhir/skripsi setelah judul Anda disahkan. Admin memeriksa berkas Anda.',
};

const form = useForm({
    // Hanya dipakai pengajuan KKM; jenis lain mengabaikannya.
    jenis_kkm: (lama?.isian.jenis_kkm ?? '') as string,
    // Hanya dipakai pengajuan ujian komprehensif.
    gelombang_kompre_id: (lama?.isian.gelombang_kompre_id ?? (props.gelombangOptions.length === 1 ? props.gelombangOptions[0].id : '')) as
        | number
        | '',
    judul: lama?.isian.judul ?? props.judulAwal ?? '',
    keterangan: lama?.isian.keterangan ?? '',
    berkas_syarat: null as File | null,
    berkas_tambahan: null as File | null,
});
// Input berkas dipasang ulang (dikosongkan) setelah terkirim.
const versiBerkas = ref(0);
const kirim = () =>
    form.post(route('mahasiswa.pengajuan-kegiatan.ajukan', props.jenis), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.berkas_syarat = form.berkas_tambahan = null;
            versiBerkas.value++;
        },
    });
</script>

<template>
    <Head :title="props.judul" />
    <AppLayout :breadcrumbs="[{ title: props.judul, href: route(props.rute) }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.judul }}</h1>
                        <p class="deskripsi-halaman">{{ deskripsi[props.jenis] }}</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Formulir Pengajuan</h2>
                    <div v-if="props.keadaan === 'selesai'" class="alert-sukses mt-4" role="status">
                        Pengajuan {{ label }} Anda disetujui {{ formatTanggal(props.pengajuan?.diproses_at, false) }}. {{ tindakLanjut[props.jenis] }}
                    </div>
                    <div v-else-if="props.keadaan === 'menunggu'" class="alert-info mt-4" role="status">
                        Pengajuan dikirim {{ formatTanggal(props.pengajuan?.diajukan_at, false) }} dan sedang menunggu diproses admin.
                    </div>
                    <div v-else-if="props.keadaan === 'perbaikan'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Diminta perbaikan:</span> {{ props.pengajuan?.catatan }}
                    </div>
                    <div v-else-if="props.keadaan === 'belum_memenuhi'" class="alert-info mt-4" role="status">
                        Judul tugas akhir Anda belum disahkan. Ajukan judul di menu Pengajuan Judul &amp; Upload TA terlebih dahulu.
                    </div>
                    <div v-else-if="props.jenis === 'kompre' && terbuka && !props.gelombangOptions.length" class="alert-info mt-4" role="status">
                        Belum ada gelombang ujian komprehensif yang dibuka.
                    </div>
                    <div v-else-if="props.pengajuan?.status === 'ditolak'" class="alert-gagal mt-4" role="status">
                        <span class="font-medium">Pengajuan sebelumnya ditolak:</span> {{ props.pengajuan.catatan }} Anda bisa mengajukan lagi.
                    </div>

                    <form class="mt-5" @submit.prevent="kirim">
                        <fieldset
                            :disabled="!terbuka || form.processing || (props.jenis === 'kompre' && !props.gelombangOptions.length)"
                            class="grid gap-4 disabled:opacity-60"
                        >
                            <div v-if="props.jenis === 'kkm'" class="grid gap-2 sm:w-64">
                                <Label for="jenis_kkm" class="label-isian">Jenis kegiatan</Label>
                                <select id="jenis_kkm" v-model="form.jenis_kkm" class="isian isian-pilih" required>
                                    <option value="" disabled>Pilih KKM, PKL, atau KKN</option>
                                    <option v-for="o in props.jenisKkmOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
                                </select>
                                <InputError :message="form.errors.jenis_kkm" />
                            </div>
                            <div v-if="props.jenis === 'kompre'" class="grid gap-2">
                                <Label for="gelombang_kompre_id" class="label-isian">Gelombang ujian</Label>
                                <select v-if="terbuka" id="gelombang_kompre_id" v-model="form.gelombang_kompre_id" class="isian isian-pilih" required>
                                    <option value="" disabled>Pilih gelombang</option>
                                    <option v-for="g in props.gelombangOptions" :key="g.id" :value="g.id">
                                        {{ g.name }}{{ g.sisa_kuota !== null ? ` (sisa ${g.sisa_kuota})` : '' }}
                                    </option>
                                </select>
                                <Input
                                    v-else
                                    id="gelombang_kompre_id"
                                    :model-value="
                                        props.gelombangDipilih
                                            ? `${props.gelombangDipilih.nama} — ujian ${formatTanggal(props.gelombangDipilih.tanggal_ujian, false)}`
                                            : '-'
                                    "
                                    readonly
                                />
                                <InputError :message="form.errors.gelombang_kompre_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="judul" class="label-isian">{{ props.labelJudul }}</Label>
                                <Input id="judul" v-model="form.judul" maxlength="300" required />
                                <InputError :message="form.errors.judul" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="keterangan" class="label-isian"
                                    >Keterangan <span class="font-normal text-[#a39e98]">(opsional)</span></Label
                                >
                                <textarea id="keterangan" v-model="form.keterangan" rows="3" maxlength="2000" class="isian isian-area" />
                                <InputError :message="form.errors.keterangan" />
                            </div>
                            <div class="grid items-start gap-4 sm:grid-cols-2">
                                <InputBerkas
                                    id="berkas_syarat"
                                    :key="`berkas_syarat-${versiBerkas}`"
                                    label="Berkas syarat (PDF/JPG/PNG, maks. 5 MB)"
                                    :accept="dokumen"
                                    :pengajuan-id="lama?.id"
                                    :sudah-ada="sudahAda('berkas_syarat')"
                                    :wajib="props.keadaan === 'baru'"
                                    :terkunci="!terbuka"
                                    :error="form.errors.berkas_syarat"
                                    @pilih="form.berkas_syarat = $event"
                                />
                                <InputBerkas
                                    id="berkas_tambahan"
                                    :key="`berkas_tambahan-${versiBerkas}`"
                                    label="Berkas tambahan (opsional)"
                                    :accept="dokumen"
                                    :pengajuan-id="lama?.id"
                                    :sudah-ada="sudahAda('berkas_tambahan')"
                                    :wajib="false"
                                    :terkunci="!terbuka"
                                    :error="form.errors.berkas_tambahan"
                                    @pilih="form.berkas_tambahan = $event"
                                />
                            </div>
                            <p class="teks-bantu">{{ tindakLanjut[props.jenis] }}</p>
                            <div v-if="terbuka" class="flex justify-end">
                                <Button type="submit">{{ props.keadaan === 'perbaikan' ? 'Kirim Perbaikan' : 'Kirim Pengajuan' }}</Button>
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
                            <span class="text-sm font-medium text-black dark:text-foreground">{{ r.jenis }}</span>
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
