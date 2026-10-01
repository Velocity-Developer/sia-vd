<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type ProgramStudiShowProps = {
    id: number;
    kode_prodi: string;
    nama_prodi: string;
    jenjang: string;
    status_akreditasi: string;
    no_sk_akreditasi?: string | null;
    tanggal_akreditasi_mulai: string;
    tanggal_akreditasi_akhir: string;
    tahun_berdiri: number;
    gelar_akademik?: string | null;
    singkatan_gelar?: string | null;
    sks_lulus?: number | null;
    status_prodi?: string | null;
    nomor_kaprodi?: string | null;
    operator?: string | null;
    nomor_operator?: string | null;
    no_sk_dikti?: string | null;
    tanggal_sk_dikti?: string | null;
    tanggal_berakhir_sk_dikti?: string | null;
    alamat?: string | null;
    provinsi?: { nama: string } | null;
    kota?: { nama: string } | null;
    kode_pos?: string | null;
    telepon?: string | null;
    faximili?: string | null;
    email?: string | null;
    website?: string | null;
    fakultas?: { id: number; kode_fakultas: string; nama_fakultas: string; dekan?: { user?: { name?: string } } | null } | null;
    ketuaProgramStudi?: { user?: { name?: string } } | null;
    ketua_program_studi?: { user?: { name?: string } } | null;
};

const props = defineProps<{ programStudi: ProgramStudiShowProps }>();

const v = (val: unknown): string => {
    if (val === null || val === undefined || val === '') return '-';
    if (typeof val === 'string') {
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 10);
        return val;
    }
    return String(val);
};

const kaprodi = () => props.programStudi.ketuaProgramStudi?.user?.name ?? props.programStudi.ketua_program_studi?.user?.name ?? '-';
const fakultas = () => (props.programStudi as any).fakultas ?? null;
</script>

<template>
    <Head :title="`Detail ${props.programStudi.nama_prodi}`" />
    <AppLayout :breadcrumbs="[{ title: 'Detail Program Studi', href: '#' }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Detail Program Studi</h1>
                        <p class="deskripsi-halaman">Ringkasan informasi program studi, fakultas induk, dan pimpinan prodi.</p>
                    </div>
                    <div class="flex gap-2">
                        <Button as-child variant="outline"><Link :href="route('admin.program-studi.index')">Kembali</Link></Button>
                        <Button as-child><Link :href="route('admin.program-studi.edit', props.programStudi.id)">Edit</Link></Button>
                    </div>
                </div>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Informasi Program Studi</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Prodi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.kode_prodi) }}</dd>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <dt class="teks-bantu">Nama Prodi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.nama_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Jenjang</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.jenjang) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Status Akreditasi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.status_akreditasi) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">No. SK Akreditasi</dt>
                            <dd class="break-words break-all text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.no_sk_akreditasi) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Tanggal Akreditasi Mulai</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.tanggal_akreditasi_mulai) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Tanggal Akreditasi Akhir</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.tanggal_akreditasi_akhir) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Tahun Berdiri</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.tahun_berdiri) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Gelar Akademik</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.gelar_akademik) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Singkatan Gelar</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.singkatan_gelar) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Status Prodi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.status_prodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">SKS Lulus</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.sks_lulus) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Fakultas & Pimpinan</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Fakultas</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(fakultas()?.nama_fakultas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Fakultas</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(fakultas()?.kode_fakultas) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Dekan Fakultas</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(fakultas()?.dekan?.user?.name) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kaprodi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(kaprodi()) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Nomor Kaprodi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.nomor_kaprodi) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Operator</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.operator) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Nomor Operator</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.nomor_operator) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">SK Dikti</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1">
                            <dt class="teks-bantu">Nomor SK Dikti</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.no_sk_dikti) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Tanggal SK Dikti</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.tanggal_sk_dikti) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Tanggal Berakhir SK Dikti</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.tanggal_berakhir_sk_dikti) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="kartu p-6">
                    <h2 class="judul-bagian">Alamat &amp; Kontak</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                            <dt class="teks-bantu">Alamat</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.alamat) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kota/Kabupaten</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.kota?.nama) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Provinsi</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">
                                {{ v(props.programStudi.provinsi?.nama) }}
                            </dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Kode Pos</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.kode_pos) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Telepon</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.telepon) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Faximili</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.faximili) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Email</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.email) }}</dd>
                        </div>
                        <div class="space-y-1">
                            <dt class="teks-bantu">Website</dt>
                            <dd class="break-words text-sm font-medium text-black dark:text-foreground">{{ v(props.programStudi.website) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
