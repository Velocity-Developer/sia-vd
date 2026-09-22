<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type MataKuliah = {
    nama_matkul: string;
};

type Kelas = {
    id: number;
    kode_kelas: string;
    mataKuliah?: MataKuliah | null;
    mata_kuliah?: MataKuliah | null;
};

type Materi = {
    id: number;
    judul_materi: string;
    jenis: string;
    pertemuan_ke: number;
    catatan?: string | null;
    file?: string[] | string | null;
    uploader?: { name: string } | null;
    kelas_kuliah?: Kelas | null;
    kelasKuliah?: Kelas | null;
};

const props = defineProps<{ materi: Materi }>();

const files = (): string[] => {
    const value = props.materi.file;

    if (Array.isArray(value)) {
        return value.filter((file): file is string => typeof file === 'string' && file !== '');
    }

    if (typeof value === 'string' && value !== '') {
        try {
            const decoded = JSON.parse(value);

            if (Array.isArray(decoded)) {
                return decoded.filter((file): file is string => typeof file === 'string' && file !== '');
            }
        } catch {
            return [value];
        }

        return [value];
    }

    return [];
};

const fileName = (path: string) => path.split('/').pop() ?? path;
const kelas = () => props.materi.kelasKuliah ?? props.materi.kelas_kuliah;
</script>

<template>
    <Head :title="props.materi.judul_materi" />
    <AppLayout
        :breadcrumbs="[
            {
                title: 'Detail Kelas',
                href: kelas()
                    ? route('mahasiswa.jadwal-kuliah.show', kelas()!.id)
                    : route('mahasiswa.jadwal-kuliah'),
            },
            { title: props.materi.judul_materi, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="text-[26px] font-bold text-black">
                            {{ props.materi.judul_materi }}
                        </h1>
                        <p class="text-sm text-[#615d59]">Detail materi perkuliahan.</p>
                    </div>
                    <Link
                        :href="
                            kelas()
                                ? route('mahasiswa.jadwal-kuliah.show', kelas()!.id)
                                : route('mahasiswa.jadwal-kuliah')
                        "
                        class="rounded-lg border border-[#e6e6e6] bg-white px-4 py-2 text-sm font-medium text-black"
                    >
                        Kembali
                    </Link>
                </div>
                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-[#a39e98]">Jenis</dt>
                            <dd class="font-medium">{{ props.materi.jenis }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Pertemuan</dt>
                            <dd class="font-medium">{{ props.materi.pertemuan_ke }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Kelas</dt>
                            <dd class="font-medium">
                                {{ kelas()?.kode_kelas ?? '-' }} —
                                {{ kelas()?.mataKuliah?.nama_matkul ?? kelas()?.mata_kuliah?.nama_matkul ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-[#a39e98]">Diunggah oleh</dt>
                            <dd class="font-medium">{{ props.materi.uploader?.name ?? '-' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Catatan</h2>
                        <p class="mt-2 whitespace-pre-line text-[15px] text-[#31302e]">
                            {{ props.materi.catatan || '-' }}
                        </p>
                    </div>
                    <div class="mt-6 border-t border-[#e6e6e6] pt-5">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">File</h2>
                        <div v-if="files().length" class="mt-3 space-y-2">
                            <a
                                v-for="(path, fileIndex) in files()"
                                :key="path"
                                :href="route('berkas.materi', [props.materi.id, fileIndex])"
                                target="_blank"
                                class="flex items-center justify-between rounded-lg border border-[#e6e6e6] px-3 py-2 text-sm text-[#0075de] transition hover:border-[#0075de] hover:bg-[#f8fbff]"
                            >
                                {{ fileName(path) }}
                            </a>
                        </div>
                        <p v-else class="mt-2 text-sm text-[#615d59]">Tidak ada file.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
