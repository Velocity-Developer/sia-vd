<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import TimePicker from '@/components/TimePicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    kelasKuliah: Record<string, any>;
    jadwal: Record<string, any> | null;
    ruangs: { id: number; name: string }[];
    /** Pertemuan belum berjalan (dan tidak dijadwal ulang manual) yang bisa ikut disusun ulang. */
    jumlahPertemuan: number;
}>();

const title = `${props.jadwal ? 'Edit' : 'Tambah'} Jadwal`;
const hariOptions = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const toHHMM = (time: string) => (time ?? '').slice(0, 5);

const form = useForm({
    hari: props.jadwal?.hari ?? '',
    jam_mulai: toHHMM(props.jadwal?.jam_mulai ?? ''),
    jam_akhir: toHHMM(props.jadwal?.jam_akhir ?? ''),
    ruang_id: props.jadwal?.ruang_id ?? '',
});

const submit = () =>
    props.jadwal
        ? form.put(route('admin.kelas-kuliah.jadwal.update', [props.kelasKuliah.id, props.jadwal.id]))
        : form.post(route('admin.kelas-kuliah.jadwal.store', props.kelasKuliah.id));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Kelas {{ props.kelasKuliah?.kode_kelas }} — lengkapi hari, jam mulai, jam akhir, dan ruang.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.kelas-kuliah.show', props.kelasKuliah.id)">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <p v-if="props.jumlahPertemuan > 0" class="alert-info">
                    Kelas ini sudah punya {{ props.jumlahPertemuan }} pertemuan. Mengubah jadwal mingguan tidak mengubah pertemuan yang sudah dibuat;
                    bila perlu, ubah pertemuan satu per satu di halaman Presensi kelas (alasan wajib diisi).
                </p>

                <form class="kartu p-6" @submit.prevent="submit">
                    <h2 class="judul-bagian">Data Jadwal</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="hari" class="label-isian">Hari</Label>
                            <select id="hari" v-model="form.hari" class="isian isian-pilih" required>
                                <option value="">Pilih hari</option>
                                <option v-for="hari in hariOptions" :key="hari" :value="hari">{{ hari }}</option>
                            </select>
                            <InputError :message="form.errors.hari" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="jam_mulai" class="label-isian">Jam Mulai</Label>
                            <TimePicker id="jam_mulai" v-model="form.jam_mulai" required />
                            <InputError :message="form.errors.jam_mulai" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="jam_akhir" class="label-isian">Jam Akhir</Label>
                            <TimePicker id="jam_akhir" v-model="form.jam_akhir" required />
                            <InputError :message="form.errors.jam_akhir" />
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2">
                        <Label for="ruang_id" class="label-isian">Ruang</Label>
                        <SearchSelect
                            id="ruang_id"
                            v-model="form.ruang_id"
                            :options="props.ruangs"
                            placeholder="Pilih ruang"
                            search-placeholder="Cari ruang (kode / nama)"
                            required
                        />
                        <InputError :message="form.errors.ruang_id" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
