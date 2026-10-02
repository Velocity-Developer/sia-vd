<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PilihKecamatan from '@/components/PilihKecamatan.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rupiah } from '@/lib/tagihanRemidi';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Opsi = { value: string | number; label: string };

// Isian biodata PDDIKTI mahasiswa (kode sama dengan formulir PMB); semuanya opsional.
const props = defineProps<{ form: InertiaForm<Record<string, string>>; opsi: Record<string, Opsi[]>; kecamatanLabel?: string | null }>();

const form = props.form;
const labelKecamatan = ref<string | null>(props.kecamatanLabel ?? null);
const kecamatan = computed({
    get: () => (form.wilayah_kecamatan_id ? Number(form.wilayah_kecamatan_id) : null),
    set: (id: number | null) => (form.wilayah_kecamatan_id = id === null ? '' : String(id)),
});
const pindahan = computed(() => form.status === 'Pindahan' || !!form.asal_perguruan_tinggi || !!form.nim_asal);

const pilihan: { field: string; label: string; opsi: string }[] = [
    { field: 'status_sipil', label: 'Status Perkawinan', opsi: 'status_sipil' },
    { field: 'jalur_kelas', label: 'Jalur Kelas', opsi: 'kelas' },
    { field: 'jenis_masuk', label: 'Jenis Masuk', opsi: 'jenis_masuk' },
    { field: 'alat_transportasi', label: 'Alat Transportasi', opsi: 'alat_transportasi' },
    { field: 'jenis_tinggal', label: 'Jenis Tinggal', opsi: 'jenis_tinggal' },
    { field: 'jenis_pembiayaan', label: 'Jenis Pembiayaan', opsi: 'jenis_pembiayaan' },
];
</script>

<template>
    <section class="kartu p-6">
        <h2 class="judul-bagian">Biodata PDDIKTI</h2>
        <p class="deskripsi-halaman">Terisi otomatis dari formulir PMB bila mahasiswa disalin dari Calon Maba. Boleh dikosongkan.</p>
        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="nik" class="label-isian">NIK</Label>
                <Input id="nik" v-model="form.nik" inputmode="numeric" maxlength="16" />
                <InputError :message="form.errors.nik" />
            </div>
            <div class="grid gap-2">
                <Label for="npwp" class="label-isian">NPWP</Label>
                <Input id="npwp" v-model="form.npwp" inputmode="numeric" maxlength="16" />
                <InputError :message="form.errors.npwp" />
            </div>
            <div v-for="p in pilihan" :key="p.field" class="grid gap-2">
                <Label :for="p.field" class="label-isian">{{ p.label }}</Label>
                <select :id="p.field" v-model="form[p.field]" class="isian isian-pilih">
                    <option value="">—</option>
                    <option v-for="o in props.opsi[p.opsi]" :key="o.value" :value="String(o.value)">{{ o.label }}</option>
                </select>
                <InputError :message="form.errors[p.field]" />
            </div>
            <div class="grid gap-2">
                <Label for="jumlah_pembiayaan" class="label-isian">Jumlah Pembiayaan</Label>
                <Input id="jumlah_pembiayaan" v-model="form.jumlah_pembiayaan" type="number" min="0" />
                <span v-if="form.jumlah_pembiayaan" class="teks-bantu">{{ rupiah(Number(form.jumlah_pembiayaan)) }}</span>
                <InputError :message="form.errors.jumlah_pembiayaan" />
            </div>
            <div class="grid gap-2">
                <Label for="telepon_wali" class="label-isian">No. HP Wali/Ortu</Label>
                <Input id="telepon_wali" v-model="form.telepon_wali" type="tel" inputmode="numeric" />
                <InputError :message="form.errors.telepon_wali" />
            </div>
            <div class="grid gap-2">
                <Label for="penerima_kps" class="label-isian">Penerima KPS</Label>
                <select id="penerima_kps" v-model="form.penerima_kps" class="isian isian-pilih">
                    <option value="0">Tidak</option>
                    <option value="1">Ya</option>
                </select>
            </div>
            <div v-if="form.penerima_kps === '1'" class="grid gap-2">
                <Label for="nomor_kps" class="label-isian">Nomor KPS</Label>
                <Input id="nomor_kps" v-model="form.nomor_kps" />
                <InputError :message="form.errors.nomor_kps" />
            </div>
        </div>

        <h3 class="mt-6 text-sm font-semibold text-black dark:text-foreground">Alamat Rinci</h3>
        <div class="mt-3 grid items-start gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="dusun" class="label-isian">Dusun</Label>
                <Input id="dusun" v-model="form.dusun" />
                <InputError :message="form.errors.dusun" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="grid gap-2">
                    <Label for="rt" class="label-isian">RT</Label>
                    <Input id="rt" v-model="form.rt" inputmode="numeric" maxlength="3" />
                    <InputError :message="form.errors.rt" />
                </div>
                <div class="grid gap-2">
                    <Label for="rw" class="label-isian">RW</Label>
                    <Input id="rw" v-model="form.rw" inputmode="numeric" maxlength="3" />
                    <InputError :message="form.errors.rw" />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="kelurahan" class="label-isian">Kelurahan</Label>
                <Input id="kelurahan" v-model="form.kelurahan" />
                <InputError :message="form.errors.kelurahan" />
            </div>
            <div class="grid gap-2">
                <Label for="wilayah_kecamatan_id" class="label-isian">Kecamatan</Label>
                <PilihKecamatan id="wilayah_kecamatan_id" v-model="kecamatan" v-model:label="labelKecamatan" />
                <InputError :message="form.errors.wilayah_kecamatan_id" />
            </div>
            <div class="grid gap-2">
                <Label for="kode_pos" class="label-isian">Kode Pos</Label>
                <Input id="kode_pos" v-model="form.kode_pos" inputmode="numeric" maxlength="5" />
                <InputError :message="form.errors.kode_pos" />
            </div>
            <div class="grid gap-2">
                <Label for="nilai_un" class="label-isian">Nilai UN</Label>
                <Input id="nilai_un" v-model="form.nilai_un" maxlength="10" />
                <InputError :message="form.errors.nilai_un" />
            </div>
        </div>

        <template v-if="pindahan">
            <h3 class="mt-6 text-sm font-semibold text-black dark:text-foreground">Asal Pindahan</h3>
            <div class="mt-3 grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="asal_perguruan_tinggi" class="label-isian">Asal Perguruan Tinggi</Label>
                    <Input id="asal_perguruan_tinggi" v-model="form.asal_perguruan_tinggi" />
                    <InputError :message="form.errors.asal_perguruan_tinggi" />
                </div>
                <div class="grid gap-2">
                    <Label for="jenjang_asal" class="label-isian">Jenjang Asal</Label>
                    <select id="jenjang_asal" v-model="form.jenjang_asal" class="isian isian-pilih">
                        <option value="">—</option>
                        <option v-for="o in props.opsi.jenjang" :key="o.value" :value="String(o.value)">{{ o.label }}</option>
                    </select>
                    <InputError :message="form.errors.jenjang_asal" />
                </div>
                <div class="grid gap-2">
                    <Label for="prodi_asal" class="label-isian">Program Studi Asal</Label>
                    <Input id="prodi_asal" v-model="form.prodi_asal" />
                    <InputError :message="form.errors.prodi_asal" />
                </div>
                <div class="grid gap-2">
                    <Label for="nim_asal" class="label-isian">NIM Asal</Label>
                    <Input id="nim_asal" v-model="form.nim_asal" maxlength="30" />
                    <InputError :message="form.errors.nim_asal" />
                </div>
                <div class="grid gap-2">
                    <Label for="sks_diakui" class="label-isian">SKS Diakui</Label>
                    <Input id="sks_diakui" v-model="form.sks_diakui" type="number" min="0" max="200" />
                    <InputError :message="form.errors.sks_diakui" />
                </div>
            </div>
        </template>
    </section>
</template>
