<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Prodi = { id: number; nama: string; kode: string; jumlah: number };
type BobotNilai = {
    huruf: string;
    bobot: number | string;
    angka_minimal: number | string | null;
    lulus: boolean;
    boleh_diulang: boolean;
    dipakai?: number;
};

const props = defineProps<{ prodi: Prodi[]; prodiId: number | null; belumDiatur: boolean; bobotNilai: BobotNilai[] }>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const terpilih = computed(() => props.prodi.find((item) => item.id === props.prodiId) ?? null);

const pilihProdi = (event: Event) =>
    router.get(route('admin.bobot-nilai.index'), { prodi: (event.target as HTMLSelectElement).value }, { preserveScroll: true });

const form = useForm({
    bobot_nilai: props.bobotNilai.map(({ huruf, bobot, angka_minimal, lulus, boleh_diulang }) => ({
        huruf,
        bobot,
        angka_minimal: angka_minimal ?? '',
        lulus,
        boleh_diulang,
    })),
});
const dipakai = (huruf: string) => props.bobotNilai.find((row) => row.huruf === huruf.toUpperCase())?.dipakai ?? 0;
const errorOf = (key: string) => (form.errors as Record<string, string | undefined>)[key];

const simpan = () => {
    if (!props.prodiId) return;
    form.transform((data) => ({
        bobot_nilai: data.bobot_nilai.map((row) => ({ ...row, angka_minimal: row.angka_minimal === '' ? null : row.angka_minimal })),
    })).put(route('admin.bobot-nilai.update', props.prodiId), { preserveScroll: true });
};

const confirmOpen = ref(false);
const hapus = () => {
    if (!props.prodiId) return;
    router.delete(route('admin.bobot-nilai.destroy', props.prodiId), { preserveScroll: true, onFinish: () => (confirmOpen.value = false) });
};
</script>

<template>
    <Head title="Bobot Nilai" />
    <AppLayout :breadcrumbs="[{ title: 'Bobot Nilai', href: route('admin.bobot-nilai.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Bobot Nilai</h1>
                        <p class="deskripsi-halaman">Atur huruf nilai beserta bobotnya untuk tiap program studi.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div v-if="!props.prodi.length" class="kartu p-6">
                    <p class="teks-bantu">Belum ada program studi. Tambahkan dulu di Master → Master Tabel → Program Studi.</p>
                </div>

                <form v-else class="kartu p-6" @submit.prevent="simpan">
                    <div class="grid max-w-md gap-2">
                        <Label for="prodi" class="label-isian">Program studi</Label>
                        <select id="prodi" :value="props.prodiId ?? ''" class="isian isian-pilih" @change="pilihProdi">
                            <option v-for="item in props.prodi" :key="item.id" :value="item.id">
                                {{ item.nama }} ({{ item.kode }}){{ item.jumlah ? '' : ' · belum diatur' }}
                            </option>
                        </select>
                    </div>

                    <p class="teks-bantu mt-4">
                        Dipakai untuk pilihan nilai di kelas, IP/IPK, KHS, dan transkrip pada mata kuliah prodi ini, menggantikan Skala Nilai umum.
                        Mengubah bobot akan mengubah IP/IPK mahasiswa yang memiliki nilai tersebut. Angka minimal (0–100) dipakai mengubah nilai angka
                        pendadaran menjadi huruf. Wajib ada minimal satu huruf lulus dan satu huruf tidak lulus, dan huruf tidak lulus harus boleh
                        diulang.
                    </p>
                    <p v-if="props.belumDiatur" class="alert-info mt-3" role="status">
                        Bobot nilai {{ terpilih?.nama }} belum diatur, jadi prodi ini memakai Skala Nilai umum di Pengaturan Akademik (isian di
                        bawah). Simpan untuk menetapkan bobot sendiri bagi prodi ini.
                    </p>

                    <div class="tabel-wadah mt-4 shadow-none">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[620px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Huruf</th>
                                        <th>Bobot</th>
                                        <th>Angka min.</th>
                                        <th class="text-center">Lulus</th>
                                        <th class="text-center">Boleh diulang</th>
                                        <th class="kolom-aksi"><span class="sr-only">Hapus</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in form.bobot_nilai" :key="index">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <Input v-model="row.huruf" maxlength="2" class="w-20 uppercase" aria-label="Huruf" />
                                            <InputError :message="errorOf(`bobot_nilai.${index}.huruf`)" />
                                        </td>
                                        <td>
                                            <Input v-model="row.bobot" type="number" min="0" max="4" step="0.01" class="w-28" aria-label="Bobot" />
                                            <InputError :message="errorOf(`bobot_nilai.${index}.bobot`)" />
                                        </td>
                                        <td>
                                            <Input
                                                v-model="row.angka_minimal"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                class="w-28"
                                                aria-label="Angka minimal"
                                            />
                                            <InputError :message="errorOf(`bobot_nilai.${index}.angka_minimal`)" />
                                        </td>
                                        <td class="text-center">
                                            <input v-model="row.lulus" type="checkbox" class="size-4 accent-[#0075de]" aria-label="Lulus" />
                                        </td>
                                        <td class="text-center">
                                            <input
                                                v-model="row.boleh_diulang"
                                                type="checkbox"
                                                class="size-4 accent-[#0075de]"
                                                aria-label="Boleh diulang"
                                            />
                                        </td>
                                        <td class="kolom-aksi">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                :aria-label="
                                                    dipakai(row.huruf) ? `Nilai ${row.huruf} dipakai ${dipakai(row.huruf)} KRS` : 'Hapus baris'
                                                "
                                                :title="dipakai(row.huruf) ? `Dipakai ${dipakai(row.huruf)} KRS, tidak bisa dihapus` : 'Hapus baris'"
                                                :disabled="dipakai(row.huruf) > 0 || form.bobot_nilai.length === 1"
                                                @click="form.bobot_nilai.splice(index, 1)"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.bobot_nilai" />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="form.bobot_nilai.push({ huruf: '', bobot: '', angka_minimal: '', lulus: true, boleh_diulang: false })"
                    >
                        <Plus /> Tambah nilai
                    </Button>

                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <Button v-if="!props.belumDiatur" type="button" variant="outline" class="text-[#dd5b00]" @click="confirmOpen = true">
                            Hapus Bobot Prodi
                        </Button>
                        <Button type="submit" :disabled="form.processing">Simpan Bobot Nilai</Button>
                    </div>
                </form>

                <AlertModal
                    :open="confirmOpen"
                    :description="`Hapus seluruh bobot nilai ${terpilih?.nama ?? ''}?`"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="hapus"
                    @cancel="confirmOpen = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
