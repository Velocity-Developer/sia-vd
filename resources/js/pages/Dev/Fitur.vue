<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string }; errors: { aktif?: string } }>();

type Fitur = {
    nama: string;
    label: string;
    keterangan: string | null;
    bawaan: boolean;
    terkunci: boolean;
    override: boolean | null;
    aktif: boolean;
    butuh: string[];
    dibutuhkan: string[];
};
type Log = { id: number; fitur: string; lama: boolean | null; baru: boolean | null; oleh: string | null; waktu: string | null };

const props = defineProps<{ fitur: Fitur[]; log: Log[] }>();

const menyimpan = ref<string | null>(null);

// aktif: true/false = override database, null = kembali mengikuti bawaan config.
const simpan = (f: Fitur, aktif: boolean | null) => {
    if (f.terkunci || menyimpan.value) return;
    menyimpan.value = f.nama;
    router.put(route('dev.fitur.update', f.nama), { aktif }, { preserveScroll: true, onFinish: () => (menyimpan.value = null) });
};

const teksNilai = (nilai: boolean | null): string => (nilai === null ? 'Bawaan config' : nilai ? 'Nyala' : 'Mati');
const teksWaktu = (waktu: string | null): string => (waktu ? waktu.slice(0, 16).replace('T', ' ') : '-');
</script>

<template>
    <Head title="Fitur Klien" />
    <AppLayout :breadcrumbs="[{ title: 'Fitur Klien', href: route('dev.fitur.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Fitur Klien</h1>
                        <p class="deskripsi-halaman">
                            Nyalakan atau matikan fitur untuk instalasi ini. Perubahan disimpan di database dan menimpa bawaan config (.env), kecuali
                            fitur yang terkunci.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>
                <div v-if="page.props.errors?.aktif" class="alert-gagal" role="alert">{{ page.props.errors.aktif }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[760px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Fitur</th>
                                    <th>Bawaan Config</th>
                                    <th>Override</th>
                                    <th class="kolom-aksi">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(f, index) in props.fitur" :key="f.nama">
                                    <td class="kolom-no align-top">{{ index + 1 }}</td>
                                    <td class="max-w-[420px] align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-black">{{ f.label }}</span>
                                            <code class="text-xs text-[#615d59]">{{ f.nama }}</code>
                                            <span
                                                v-if="f.terkunci"
                                                class="inline-flex items-center gap-1 rounded-full border border-[#e6e6e6] bg-[#f6f5f4] px-2 py-0.5 text-xs font-medium text-[#615d59]"
                                                ><Lock class="size-3" />Terkunci</span
                                            >
                                        </div>
                                        <p v-if="f.keterangan" class="mt-0.5 text-[#615d59]">{{ f.keterangan }}</p>
                                        <p v-if="f.butuh.length" class="mt-0.5 text-xs text-[#615d59]">Butuh: {{ f.butuh.join(', ') }}</p>
                                        <p v-if="f.dibutuhkan.length" class="mt-0.5 text-xs text-[#615d59]">
                                            Dibutuhkan oleh: {{ f.dibutuhkan.join(', ') }}
                                        </p>
                                    </td>
                                    <td class="align-top">{{ f.bawaan ? 'Nyala' : 'Mati' }}</td>
                                    <td class="align-top">
                                        <span v-if="f.terkunci" class="text-[#615d59]">Diabaikan</span>
                                        <template v-else>
                                            {{ teksNilai(f.override) }}
                                            <Button
                                                v-if="f.override !== null"
                                                variant="link"
                                                size="sm"
                                                class="h-auto px-1"
                                                :disabled="menyimpan !== null"
                                                @click="simpan(f, null)"
                                                >Ikuti bawaan</Button
                                            >
                                        </template>
                                    </td>
                                    <td class="kolom-aksi align-top">
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="f.aktif"
                                            :aria-label="`${f.aktif ? 'Matikan' : 'Nyalakan'} ${f.label}`"
                                            :title="f.terkunci ? 'Dikunci lewat LOCK_* di .env' : f.aktif ? 'Matikan' : 'Nyalakan'"
                                            :disabled="f.terkunci || menyimpan !== null"
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#0075de] disabled:cursor-not-allowed disabled:opacity-50"
                                            :class="f.aktif ? 'bg-[#0075de]' : 'bg-[#d6d3d1]'"
                                            @click="simpan(f, !f.aktif)"
                                        >
                                            <span
                                                class="inline-block size-5 rounded-full bg-white shadow transition-transform"
                                                :class="f.aktif ? 'translate-x-[22px]' : 'translate-x-0.5'"
                                            />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="props.fitur.length === 0">
                                    <td colspan="5" class="tabel-kosong">Belum ada fitur terdaftar di config/client.php.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <h2 class="judul-bagian mt-8">Riwayat Perubahan</h2>
                <div class="tabel-wadah mt-3">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[640px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Waktu</th>
                                    <th>Fitur</th>
                                    <th>Perubahan</th>
                                    <th>Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(l, index) in props.log" :key="l.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>{{ teksWaktu(l.waktu) }}</td>
                                    <td>{{ l.fitur }}</td>
                                    <td>{{ teksNilai(l.lama) }} → {{ teksNilai(l.baru) }}</td>
                                    <td>{{ l.oleh ?? '-' }}</td>
                                </tr>
                                <tr v-if="props.log.length === 0">
                                    <td colspan="5" class="tabel-kosong">Belum ada perubahan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
