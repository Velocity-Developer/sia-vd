<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type MatkulRingkas = { id: number; kode_matkul: string; nama_matkul: string; semester: number };

type MataKuliahShowProps = {
    id: number;
    kode_matkul: string;
    nama_matkul: string;
    sks: number;
    semester: number;
    jenis: string;
    tugas_akhir: boolean;
    prasyarat: MatkulRingkas[];
    menjadi_prasyarat: MatkulRingkas[];
    prodi?: {
        id: number;
        kode_prodi: string;
        nama_prodi: string;
        jenjang: string;
        fakultas?: { kode_fakultas: string; nama_fakultas: string } | null;
    } | null;
};

const props = defineProps<{ mataKuliah: MataKuliahShowProps }>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    if (typeof val === 'string') {
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 10);
        return val;
    }
    return String(val);
};

const prodi = () => (props.mataKuliah as any).prodi ?? null;
</script>

<template>
    <Head :title="`Detail ${props.mataKuliah.nama_matkul}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Mata Kuliah', href: '#' }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Mata Kuliah</h1>
                        <p class="deskripsi-halaman">Ringkasan informasi mata kuliah, program studi, dan fakultas induk.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"><Link :href="route('admin.mata-kuliah.index')">Kembali</Link></Button>
                        <Button as-child><Link :href="route('admin.mata-kuliah.edit', props.mataKuliah.id)">Edit</Link></Button>
                    </div>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Mata Kuliah</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Mata Kuliah</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.mataKuliah.kode_matkul) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="teks-bantu">Nama Mata Kuliah</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.mataKuliah.nama_matkul) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">SKS</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.mataKuliah.sks) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Semester</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.mataKuliah.semester) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Jenis</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.mataKuliah.jenis) }}<span v-if="props.mataKuliah.tugas_akhir"> · TA/Skripsi</span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Program Studi & Fakultas</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Program Studi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(prodi()?.nama_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Prodi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(prodi()?.kode_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Jenjang</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(prodi()?.jenjang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Fakultas</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(prodi()?.fakultas?.nama_fakultas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Fakultas</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(prodi()?.fakultas?.kode_fakultas) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Prasyarat</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Harus Lulus Dulu</dt>
                            <dd v-if="props.mataKuliah.prasyarat.length" class="space-y-1 text-sm text-black dark:text-foreground">
                                <p v-for="item in props.mataKuliah.prasyarat" :key="item.id">
                                    {{ item.kode_matkul }} — {{ item.nama_matkul }} <span class="text-[#a39e98]">(smt {{ item.semester }})</span>
                                </p>
                            </dd>
                            <dd v-else class="text-sm text-black dark:text-foreground">Tidak ada</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Menjadi Prasyarat Untuk</dt>
                            <dd v-if="props.mataKuliah.menjadi_prasyarat.length" class="space-y-1 text-sm text-black dark:text-foreground">
                                <p v-for="item in props.mataKuliah.menjadi_prasyarat" :key="item.id">
                                    {{ item.kode_matkul }} — {{ item.nama_matkul }} <span class="text-[#a39e98]">(smt {{ item.semester }})</span>
                                </p>
                            </dd>
                            <dd v-else class="text-sm text-black dark:text-foreground">Tidak ada</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
