<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatTanggal } from '@/lib/presensi';
import { HASIL_PENDADARAN, type HasilPendadaran, type JadwalPendadaran, type KodeHasil } from '@/lib/tugasAkhir';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

export type JadwalDosen = JadwalPendadaran &
    HasilPendadaran & {
        nama: string | null;
        peran: string;
        boleh_dinilai: boolean;
        nilai_saya: { nilai: number; catatan: string | null } | null;
        jumlah_nilai: number;
        ketua: boolean;
        nilai_penguji: { peran: string; nilai: number; catatan: string | null }[];
        usulan: { rata_rata: number; huruf: string | null; lulus: boolean } | null;
    };

const props = defineProps<{ jadwal: JadwalDosen }>();
const j = () => props.jadwal;

// Nilai penguji (0–100), bisa diubah sampai ketua menetapkan hasil.
const bukaNilai = ref(false);
const nilaiForm = useForm({ nilai: '' as number | string, catatan: '' });
const isiNilai = () => {
    nilaiForm.clearErrors();
    nilaiForm.nilai = j().nilai_saya?.nilai ?? '';
    nilaiForm.catatan = j().nilai_saya?.catatan ?? '';
    bukaNilai.value = true;
};
const simpanNilai = () =>
    nilaiForm.post(route('dosen.pendadaran.nilai', j().id), { preserveScroll: true, onSuccess: () => (bukaNilai.value = false) });

// Hasil oleh ketua: usulan otomatis dari huruf rata-rata.
const bukaHasil = ref(false);
const hasilForm = useForm({ hasil: '' as KodeHasil | '', catatan_hasil: '' });
const tetapkan = () => {
    hasilForm.clearErrors();
    hasilForm.hasil = j().usulan?.lulus ? 'lulus' : 'tidak_lulus';
    hasilForm.catatan_hasil = '';
    bukaHasil.value = true;
};
const simpanHasil = () =>
    hasilForm.post(route('dosen.pendadaran.hasil', j().id), { preserveScroll: true, onSuccess: () => (bukaHasil.value = false) });

const sahkan = ref(false);
const sahkanRevisi = () =>
    router.post(route('dosen.pendadaran.revisi.sahkan', j().id), {}, { preserveScroll: true, onFinish: () => (sahkan.value = false) });
const bukaTolakRevisi = ref(false);
const revisiForm = useForm({ catatan: '' });
const tolakRevisi = () =>
    revisiForm.post(route('dosen.pendadaran.revisi.tolak', j().id), { preserveScroll: true, onSuccess: () => (bukaTolakRevisi.value = false) });
</script>

<template>
    <div class="grid gap-1.5 text-sm">
        <span class="w-fit rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-medium text-[#0075de]">{{ jadwal.peran }}</span>

        <template v-if="jadwal.hasil">
            <span class="w-fit rounded-full px-2 py-0.5 text-xs font-medium" :class="HASIL_PENDADARAN[jadwal.hasil].kelas">{{
                HASIL_PENDADARAN[jadwal.hasil].label
            }}</span>
            <span class="teks-bantu">Nilai {{ jadwal.nilai_akhir }} ({{ jadwal.huruf }})</span>
            <template v-if="jadwal.status === 'revisi'">
                <span v-if="jadwal.revisi_diunggah_at" class="text-xs text-[#31302e] dark:text-foreground">
                    Revisi dikirim {{ formatTanggal(jadwal.revisi_diunggah_at, false) }} ·
                    <a :href="route('berkas.naskah-revisi', jadwal.id)" target="_blank" rel="noopener" class="text-[#0075de] hover:underline"
                        >naskah</a
                    >
                </span>
                <span v-else class="text-xs text-[#a39e98]">Menunggu naskah revisi</span>
                <div v-if="jadwal.ketua && jadwal.revisi_diunggah_at" class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" @click="bukaTolakRevisi = true">Kembalikan</Button>
                    <Button size="sm" @click="sahkan = true">Sahkan Revisi</Button>
                </div>
            </template>
        </template>

        <template v-else-if="jadwal.status === 'dijadwalkan'">
            <span v-if="jadwal.nilai_saya" class="text-xs text-[#31302e] dark:text-foreground">Nilai Anda: {{ jadwal.nilai_saya.nilai }}</span>
            <span class="text-xs text-[#a39e98]">{{ jadwal.jumlah_nilai }}/3 penguji sudah menilai</span>
            <div class="flex flex-wrap gap-2">
                <Button v-if="jadwal.boleh_dinilai" variant="outline" size="sm" @click="isiNilai">
                    {{ jadwal.nilai_saya ? 'Ubah Nilai' : 'Isi Nilai' }}
                </Button>
                <Button v-if="jadwal.ketua && jadwal.usulan" size="sm" @click="tetapkan">Tetapkan Hasil</Button>
            </div>
            <span v-if="!jadwal.boleh_dinilai && jadwal.peran !== 'Pembimbing'" class="text-xs text-[#a39e98]"
                >Nilai diisi sejak pendadaran dimulai.</span
            >
        </template>
    </div>

    <div v-if="bukaNilai" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="bukaNilai = false">
        <form class="kartu w-full max-w-md p-6" @submit.prevent="simpanNilai">
            <h3 class="judul-bagian">Nilai pendadaran</h3>
            <p class="teks-bantu mt-1">{{ jadwal.nama }} · sebagai {{ jadwal.peran }}</p>
            <div class="mt-4 grid gap-2">
                <Label for="nilai_pendadaran" class="label-isian">Nilai (0–100)</Label>
                <Input id="nilai_pendadaran" v-model="nilaiForm.nilai" type="number" min="0" max="100" step="0.01" class="w-32" required />
                <InputError :message="nilaiForm.errors.nilai" />
            </div>
            <div class="mt-4 grid gap-2">
                <Label for="catatan_nilai" class="label-isian">Catatan <span class="font-normal text-[#a39e98]">(opsional)</span></Label>
                <textarea id="catatan_nilai" v-model="nilaiForm.catatan" rows="3" maxlength="1000" class="isian isian-area" />
                <InputError :message="nilaiForm.errors.catatan" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <Button type="button" variant="outline" @click="bukaNilai = false">Batal</Button>
                <Button type="submit" :disabled="nilaiForm.processing">Simpan</Button>
            </div>
        </form>
    </div>

    <div
        v-if="bukaHasil && jadwal.usulan"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
        @click.self="bukaHasil = false"
    >
        <form class="kartu w-full max-w-lg p-6" @submit.prevent="simpanHasil">
            <h3 class="judul-bagian">Tetapkan hasil pendadaran</h3>
            <p class="teks-bantu mt-1">{{ jadwal.nama }}</p>
            <table class="mt-4 w-full text-sm">
                <tr v-for="n in jadwal.nilai_penguji" :key="n.peran" class="border-b border-[#e6e6e6] dark:border-border">
                    <td class="py-1.5 text-[#615d59] dark:text-muted-foreground">{{ n.peran }}</td>
                    <td class="py-1.5 text-right font-medium">{{ n.nilai }}</td>
                </tr>
                <tr>
                    <td class="py-1.5 font-medium">Rata-rata</td>
                    <td class="py-1.5 text-right font-semibold">{{ jadwal.usulan.rata_rata }} ({{ jadwal.usulan.huruf ?? '?' }})</td>
                </tr>
            </table>
            <p class="mt-2 text-xs" :class="jadwal.usulan.lulus ? 'text-[#1aae39]' : 'text-[#b42318]'">
                Usulan: {{ jadwal.usulan.lulus ? 'lulus' : 'tidak lulus' }} berdasarkan skala nilai.
            </p>
            <fieldset class="mt-4 grid gap-2">
                <legend class="label-isian mb-1">Hasil</legend>
                <label v-for="(h, kode) in HASIL_PENDADARAN" :key="kode" class="flex items-center gap-2 text-sm">
                    <input
                        v-model="hasilForm.hasil"
                        type="radio"
                        :value="kode"
                        :disabled="!jadwal.usulan.lulus && kode !== 'tidak_lulus'"
                        class="accent-[#0075de]"
                    />
                    {{ h.label }}
                </label>
                <InputError :message="hasilForm.errors.hasil" />
            </fieldset>
            <div class="mt-4 grid gap-2">
                <Label for="catatan_hasil" class="label-isian">
                    {{ hasilForm.hasil === 'lulus_revisi' ? 'Bagian yang harus direvisi' : 'Catatan' }}
                    <span v-if="hasilForm.hasil !== 'lulus_revisi'" class="font-normal text-[#a39e98]">(opsional)</span>
                </Label>
                <textarea
                    id="catatan_hasil"
                    v-model="hasilForm.catatan_hasil"
                    rows="4"
                    maxlength="3000"
                    class="isian isian-area"
                    :required="hasilForm.hasil === 'lulus_revisi'"
                />
                <InputError :message="hasilForm.errors.catatan_hasil" />
            </div>
            <p class="teks-bantu mt-3">
                Lulus: nilai huruf langsung masuk ke mata kuliah TA/Skripsi. Lulus dengan revisi: nilai masuk setelah Anda mengesahkan revisi.
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <Button type="button" variant="outline" @click="bukaHasil = false">Batal</Button>
                <Button type="submit" :disabled="hasilForm.processing">Tetapkan</Button>
            </div>
        </form>
    </div>

    <AlertModal
        :open="sahkan"
        title="Sahkan revisi?"
        :description="`Tugas akhir ${jadwal.nama} selesai dan nilai ${jadwal.huruf} masuk ke mata kuliah TA/Skripsi.`"
        confirm-text="Sahkan"
        cancel-text="Batal"
        @update:open="sahkan = $event"
        @confirm="sahkanRevisi"
        @cancel="sahkan = false"
    />
    <div v-if="bukaTolakRevisi" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="bukaTolakRevisi = false">
        <form class="kartu w-full max-w-md p-6" @submit.prevent="tolakRevisi">
            <h3 class="judul-bagian">Kembalikan revisi</h3>
            <p class="teks-bantu mt-1">{{ jadwal.nama }} mengunggah ulang naskah sesuai catatan Anda.</p>
            <textarea v-model="revisiForm.catatan" rows="3" maxlength="1000" class="isian isian-area mt-4" aria-label="Catatan revisi" required />
            <InputError :message="revisiForm.errors.catatan" />
            <div class="mt-6 flex justify-end gap-2">
                <Button type="button" variant="outline" @click="bukaTolakRevisi = false">Batal</Button>
                <Button type="submit" variant="destructive" :disabled="revisiForm.processing">Kembalikan</Button>
            </div>
        </form>
    </div>
</template>
