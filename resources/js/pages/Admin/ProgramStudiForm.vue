<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    programStudi: Record<string, any> | null;
    fakultas: { id: number; nama_fakultas: string }[];
    dosen: { id: number; name: string }[];
    provinsis: { id: number; nama: string }[];
    kotas: { id: number; provinsi_id: number; nama: string }[];
    statusProdi: string[];
}>();

const title = `${props.programStudi ? 'Edit' : 'Tambah'} Program Studi`;

const jenjang = ['D3', 'D4', 'S1', 'S2', 'S3', 'Sp-1', 'Sp-2', 'Profesi'];

const form = useForm({
    fakultas_id: props.programStudi?.fakultas_id ?? '',
    kode_prodi: props.programStudi?.kode_prodi ?? '',
    nama_prodi: props.programStudi?.nama_prodi ?? '',
    jenjang: props.programStudi?.jenjang ?? '',
    status_akreditasi: props.programStudi?.status_akreditasi ?? '',
    no_sk_akreditasi: props.programStudi?.no_sk_akreditasi ?? '',
    tanggal_akreditasi_mulai: props.programStudi?.tanggal_akreditasi_mulai?.slice(0, 10) ?? '',
    tanggal_akreditasi_akhir: props.programStudi?.tanggal_akreditasi_akhir?.slice(0, 10) ?? '',
    kaprodi: props.programStudi?.kaprodi ?? '',
    tahun_berdiri: props.programStudi?.tahun_berdiri ?? '',
    gelar_akademik: props.programStudi?.gelar_akademik ?? '',
    singkatan_gelar: props.programStudi?.singkatan_gelar ?? '',
    sks_lulus: props.programStudi?.sks_lulus ?? '',
    status_prodi: props.programStudi?.status_prodi ?? '',
    nomor_kaprodi: props.programStudi?.nomor_kaprodi ?? '',
    operator: props.programStudi?.operator ?? '',
    nomor_operator: props.programStudi?.nomor_operator ?? '',
    no_sk_dikti: props.programStudi?.no_sk_dikti ?? '',
    tanggal_sk_dikti: props.programStudi?.tanggal_sk_dikti?.slice(0, 10) ?? '',
    tanggal_berakhir_sk_dikti: props.programStudi?.tanggal_berakhir_sk_dikti?.slice(0, 10) ?? '',
    alamat: props.programStudi?.alamat ?? '',
    provinsi_id: (props.programStudi?.provinsi_id ?? '') as number | '',
    kota_id: (props.programStudi?.kota_id ?? '') as number | '',
    kode_pos: props.programStudi?.kode_pos ?? '',
    telepon: props.programStudi?.telepon ?? '',
    faximili: props.programStudi?.faximili ?? '',
    email: props.programStudi?.email ?? '',
    website: props.programStudi?.website ?? '',
});

// Pilihan kota mengikuti provinsi; kota yang tidak termasuk provinsi baru dikosongkan.
const kotaPilihan = computed(() => props.kotas.filter((kota) => kota.provinsi_id === form.provinsi_id));
watch(
    () => form.provinsi_id,
    () => {
        if (!kotaPilihan.value.some((kota) => kota.id === form.kota_id)) form.kota_id = '';
    },
);

const submit = () =>
    props.programStudi ? form.put(route('admin.program-studi.update', props.programStudi.id)) : form.post(route('admin.program-studi.store'));
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Program Studi', href: route('admin.program-studi.index') }]">
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ title }}</h1>
                        <p class="deskripsi-halaman">Lengkapi data program studi, kaprodi, SK Dikti, akreditasi, dan alamat.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.program-studi.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Program Studi</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="fakultas_id" class="label-isian">Fakultas</Label>
                            <SearchSelect
                                id="fakultas_id"
                                v-model="form.fakultas_id"
                                :options="props.fakultas.map((item) => ({ id: item.id, name: item.nama_fakultas }))"
                                placeholder="Pilih fakultas"
                                search-placeholder="Cari fakultas"
                                required
                            />
                            <InputError :message="form.errors.fakultas_id" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_prodi" class="label-isian">Kode Program Studi</Label>
                                <Input id="kode_prodi" v-model="form.kode_prodi" type="text" required />
                                <InputError :message="form.errors.kode_prodi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_prodi" class="label-isian">Nama Program Studi</Label>
                                <Input id="nama_prodi" v-model="form.nama_prodi" type="text" required />
                                <InputError :message="form.errors.nama_prodi" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="jenjang" class="label-isian">Jenjang</Label>
                                <select id="jenjang" v-model="form.jenjang" class="isian isian-pilih" required>
                                    <option value="">Pilih jenjang</option>
                                    <option v-for="item in jenjang" :key="item" :value="item">{{ item }}</option>
                                </select>
                                <InputError :message="form.errors.jenjang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tahun_berdiri" class="label-isian">Tahun Berdiri</Label>
                                <Input id="tahun_berdiri" v-model="form.tahun_berdiri" type="number" required />
                                <InputError :message="form.errors.tahun_berdiri" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="gelar_akademik" class="label-isian">Gelar Akademik</Label>
                                <Input id="gelar_akademik" v-model="form.gelar_akademik" type="text" placeholder="Sarjana Keperawatan" />
                                <InputError :message="form.errors.gelar_akademik" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="singkatan_gelar" class="label-isian">Singkatan Gelar</Label>
                                <Input id="singkatan_gelar" v-model="form.singkatan_gelar" type="text" placeholder="S.Kep." />
                                <InputError :message="form.errors.singkatan_gelar" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="status_prodi" class="label-isian">Status Prodi</Label>
                                <select id="status_prodi" v-model="form.status_prodi" class="isian isian-pilih">
                                    <option value="">Pilih status</option>
                                    <option v-for="item in props.statusProdi" :key="item" :value="item">{{ item }}</option>
                                </select>
                                <InputError :message="form.errors.status_prodi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="sks_lulus" class="label-isian">SKS Lulus</Label>
                                <Input id="sks_lulus" v-model="form.sks_lulus" type="number" min="1" max="1000" />
                                <p class="teks-bantu">
                                    Syarat SKS bernilai (di luar TA/Skripsi) untuk mendaftar pendadaran mahasiswa prodi ini. Kosongkan untuk memakai
                                    Pengaturan Akademik.
                                </p>
                                <InputError :message="form.errors.sks_lulus" />
                            </div>
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Kaprodi &amp; Operator</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="kaprodi" class="label-isian">Kaprodi</Label>
                            <SearchSelect
                                id="kaprodi"
                                v-model="form.kaprodi"
                                :options="props.dosen"
                                placeholder="Pilih kaprodi"
                                search-placeholder="Cari kaprodi"
                                required
                            />
                            <InputError :message="form.errors.kaprodi" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="nomor_kaprodi" class="label-isian">Nomor Kaprodi</Label>
                                <Input id="nomor_kaprodi" v-model="form.nomor_kaprodi" type="text" inputmode="tel" placeholder="08xxxxxxxxxx" />
                                <InputError :message="form.errors.nomor_kaprodi" />
                            </div>
                            <div class="grid gap-2 sm:col-start-1">
                                <Label for="operator" class="label-isian">Operator</Label>
                                <Input id="operator" v-model="form.operator" type="text" placeholder="Nama operator prodi" />
                                <InputError :message="form.errors.operator" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nomor_operator" class="label-isian">Nomor Operator</Label>
                                <Input id="nomor_operator" v-model="form.nomor_operator" type="text" inputmode="tel" placeholder="08xxxxxxxxxx" />
                                <InputError :message="form.errors.nomor_operator" />
                            </div>
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">SK Dikti</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="no_sk_dikti" class="label-isian">Nomor SK Dikti</Label>
                            <Input id="no_sk_dikti" v-model="form.no_sk_dikti" type="text" />
                            <InputError :message="form.errors.no_sk_dikti" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_sk_dikti" class="label-isian">Tanggal SK Dikti</Label>
                                <DatePicker id="tanggal_sk_dikti" v-model="form.tanggal_sk_dikti" placeholder="Pilih tanggal SK" :required="false" />
                                <InputError :message="form.errors.tanggal_sk_dikti" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_berakhir_sk_dikti" class="label-isian">Tanggal Berakhir SK Dikti</Label>
                                <DatePicker
                                    id="tanggal_berakhir_sk_dikti"
                                    v-model="form.tanggal_berakhir_sk_dikti"
                                    placeholder="Pilih tanggal berakhir"
                                    :required="false"
                                />
                                <InputError :message="form.errors.tanggal_berakhir_sk_dikti" />
                            </div>
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Akreditasi</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="status_akreditasi" class="label-isian">Status Akreditasi</Label>
                                <Input id="status_akreditasi" v-model="form.status_akreditasi" type="text" required />
                                <InputError :message="form.errors.status_akreditasi" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="no_sk_akreditasi" class="label-isian">Nomor SK Akreditasi</Label>
                                <Input id="no_sk_akreditasi" v-model="form.no_sk_akreditasi" type="text" />
                                <InputError :message="form.errors.no_sk_akreditasi" />
                            </div>
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="tanggal_akreditasi_mulai" class="label-isian">Tanggal Akreditasi Mulai</Label>
                                <DatePicker
                                    id="tanggal_akreditasi_mulai"
                                    v-model="form.tanggal_akreditasi_mulai"
                                    placeholder="Pilih tanggal mulai"
                                    :required="false"
                                />
                                <InputError :message="form.errors.tanggal_akreditasi_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="tanggal_akreditasi_akhir" class="label-isian">Tanggal Akreditasi Akhir</Label>
                                <DatePicker
                                    id="tanggal_akreditasi_akhir"
                                    v-model="form.tanggal_akreditasi_akhir"
                                    placeholder="Pilih tanggal akhir"
                                    :required="false"
                                />
                                <InputError :message="form.errors.tanggal_akreditasi_akhir" />
                            </div>
                        </div>
                    </section>

                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Alamat &amp; Kontak</h2>
                        <div class="mt-4 grid gap-2">
                            <Label for="alamat" class="label-isian">Alamat</Label>
                            <textarea id="alamat" v-model="form.alamat" rows="2" placeholder="Nama jalan dan nomor" class="isian isian-area" />
                            <InputError :message="form.errors.alamat" />
                        </div>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="provinsi_id" class="label-isian">Provinsi</Label>
                                <select id="provinsi_id" v-model="form.provinsi_id" class="isian isian-pilih">
                                    <option value="">Pilih provinsi</option>
                                    <option v-for="provinsi in props.provinsis" :key="provinsi.id" :value="provinsi.id">{{ provinsi.nama }}</option>
                                </select>
                                <InputError :message="form.errors.provinsi_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="kota_id" class="label-isian">Kota/Kabupaten</Label>
                                <select id="kota_id" v-model="form.kota_id" class="isian isian-pilih" :disabled="!form.provinsi_id">
                                    <option value="">{{ form.provinsi_id ? 'Pilih kota/kabupaten' : 'Pilih provinsi dulu' }}</option>
                                    <option v-for="kota in kotaPilihan" :key="kota.id" :value="kota.id">{{ kota.nama }}</option>
                                </select>
                                <InputError :message="form.errors.kota_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="kode_pos" class="label-isian">Kode Pos</Label>
                                <Input id="kode_pos" v-model="form.kode_pos" inputmode="numeric" maxlength="10" />
                                <InputError :message="form.errors.kode_pos" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="telepon" class="label-isian">Telepon</Label>
                                <Input id="telepon" v-model="form.telepon" placeholder="0411-123456" />
                                <InputError :message="form.errors.telepon" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="faximili" class="label-isian">Faximili</Label>
                                <Input id="faximili" v-model="form.faximili" />
                                <InputError :message="form.errors.faximili" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email" class="label-isian">Email</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="prodi@example.ac.id" />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="website" class="label-isian">Website</Label>
                                <Input id="website" v-model="form.website" placeholder="https://prodi.example.ac.id" />
                                <InputError :message="form.errors.website" />
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
