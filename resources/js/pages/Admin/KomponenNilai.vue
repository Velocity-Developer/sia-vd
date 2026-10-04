<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Sumber = 'manual' | 'kehadiran';
type Komponen = { id: number; nama: string; persen: number; sumber: Sumber; dipakai: number };

const props = defineProps<{ komponen: Komponen[] }>();

const page = usePage<{ flash: { success?: string; error?: string } }>();

const form = useForm({
    komponen: props.komponen.map(({ id, nama, persen, sumber }) => ({ id: id as number | null, nama, persen: persen as number | string, sumber })),
});
const total = computed(() => Math.round(form.komponen.reduce((n, row) => n + (Number(row.persen) || 0), 0) * 100) / 100);
const pas = computed(() => Math.abs(total.value - 100) < 0.005);
const dipakai = (id: number | null) => props.komponen.find((k) => k.id === id)?.dipakai ?? 0;
const errorOf = (key: string) => (form.errors as Record<string, string | undefined>)[key];

const simpan = () => form.put(route('admin.komponen-nilai.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Tambah Komponen Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Tambah Komponen Nilai', href: route('admin.komponen-nilai.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Tambah Komponen Nilai</h1>
                        <p class="deskripsi-halaman">Komponen nilai dan persen bobotnya, berlaku untuk semua mata kuliah.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form class="kartu p-6" @submit.prevent="simpan">
                    <p class="teks-bantu">
                        Nilai akhir mahasiswa = rata-rata berbobot angka (0–100) tiap komponen sesuai persennya, lalu diubah menjadi huruf memakai
                        angka minimal di Bobot Nilai prodi. Jumlah persen semua komponen harus 100%. Mengubah persen tidak menghitung ulang nilai yang
                        sudah tersimpan; nilai akhir suatu kelas ikut berubah saat nilainya disimpan lagi. Sumber “Otomatis dari kehadiran” memakai
                        persentase hadir/terlambat mahasiswa di pertemuan kuliah yang sudah selesai (paling banyak satu komponen).
                    </p>

                    <div class="tabel-wadah mt-4 shadow-none">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[720px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Komponen</th>
                                        <th>Persen</th>
                                        <th>Sumber nilai</th>
                                        <th class="kolom-aksi"><span class="sr-only">Hapus</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in form.komponen" :key="index">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <Input v-model="row.nama" maxlength="60" placeholder="mis. UTS" aria-label="Nama komponen" />
                                            <InputError :message="errorOf(`komponen.${index}.nama`)" />
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <Input
                                                    v-model="row.persen"
                                                    type="number"
                                                    min="0.01"
                                                    max="100"
                                                    step="0.01"
                                                    class="w-28"
                                                    aria-label="Persen"
                                                />
                                                <span class="text-sm text-[#615d59]">%</span>
                                            </div>
                                            <InputError :message="errorOf(`komponen.${index}.persen`)" />
                                        </td>
                                        <td>
                                            <select v-model="row.sumber" class="isian isian-pilih w-56" aria-label="Sumber nilai">
                                                <option value="manual">Diisi dosen/admin</option>
                                                <option value="kehadiran">Otomatis dari kehadiran</option>
                                            </select>
                                            <InputError :message="errorOf(`komponen.${index}.sumber`)" />
                                        </td>
                                        <td class="kolom-aksi">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                :aria-label="dipakai(row.id) ? `Dipakai ${dipakai(row.id)} nilai mahasiswa` : 'Hapus baris'"
                                                :title="
                                                    dipakai(row.id)
                                                        ? `Sudah berisi ${dipakai(row.id)} nilai mahasiswa, tidak bisa dihapus`
                                                        : 'Hapus baris'
                                                "
                                                :disabled="dipakai(row.id) > 0"
                                                @click="form.komponen.splice(index, 1)"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </td>
                                    </tr>
                                    <tr v-if="!form.komponen.length" class="baris-kosong">
                                        <td colspan="5" class="tabel-kosong">Belum ada komponen nilai.</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-right font-medium">Jumlah</td>
                                        <td class="font-medium tabular-nums" :class="pas ? 'text-[#1aae39]' : 'text-[#b42318]'">{{ total }}%</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.komponen" />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="form.komponen.push({ id: null, nama: '', persen: '', sumber: 'manual' })"
                    >
                        <Plus /> Tambah komponen
                    </Button>

                    <div class="mt-6 flex justify-end">
                        <Button type="submit" :disabled="form.processing || !pas">Simpan Komponen Nilai</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
