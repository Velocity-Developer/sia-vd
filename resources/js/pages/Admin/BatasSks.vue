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
type BatasSks = { ips_minimal: number | string; maks_sks: number | string };

const props = defineProps<{
    prodi: Prodi[];
    prodiId: number | null;
    belumDiatur: boolean;
    maksSksTanpaIps: number | null;
    batasSks: BatasSks[];
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const terpilih = computed(() => props.prodi.find((item) => item.id === props.prodiId) ?? null);

const pilihProdi = (event: Event) =>
    router.get(route('admin.batas-sks.index'), { prodi: (event.target as HTMLSelectElement).value }, { preserveScroll: true });

const form = useForm({
    maks_sks_tanpa_ips: (props.maksSksTanpaIps ?? '') as number | string,
    batas_sks: props.batasSks.map((row) => ({ ...row })),
});
const errorOf = (key: string) => (form.errors as Record<string, string | undefined>)[key];

const simpan = () => {
    if (props.prodiId) form.put(route('admin.batas-sks.update', props.prodiId), { preserveScroll: true });
};

const confirmOpen = ref(false);
const hapus = () => {
    if (!props.prodiId) return;
    router.delete(route('admin.batas-sks.destroy', props.prodiId), { preserveScroll: true, onFinish: () => (confirmOpen.value = false) });
};
</script>

<template>
    <Head title="Batas SKS per Semester" />
    <AppLayout :breadcrumbs="[{ title: 'Batas SKS per Semester', href: route('admin.batas-sks.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Batas SKS per Semester</h1>
                        <p class="deskripsi-halaman">
                            Atur batas SKS yang boleh diambil mahasiswa tiap program studi menurut IPS semester sebelumnya.
                        </p>
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
                        Ditentukan dari IPS semester terakhir mahasiswa prodi ini yang sudah bernilai. Baris dengan IPS minimal tertinggi yang
                        terpenuhi yang dipakai. Berlaku di KRS, verifikasi KRS, dan kuota SKS tagihan, menggantikan Batas SKS global.
                    </p>
                    <p v-if="props.belumDiatur" class="alert-info mt-3" role="status">
                        Batas SKS {{ terpilih?.nama }} belum diatur, jadi prodi ini memakai Batas SKS global di Pengaturan Akademik (isian di bawah).
                        Simpan untuk menetapkan batas sendiri bagi prodi ini.
                    </p>

                    <div class="tabel-wadah mt-4 shadow-none">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[460px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>IPS minimal</th>
                                        <th>Maks SKS</th>
                                        <th class="kolom-aksi"><span class="sr-only">Hapus</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in form.batas_sks" :key="index">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <Input v-model="row.ips_minimal" type="number" min="0" max="4" step="0.01" aria-label="IPS minimal" />
                                            <InputError :message="errorOf(`batas_sks.${index}.ips_minimal`)" />
                                        </td>
                                        <td>
                                            <Input v-model="row.maks_sks" type="number" min="1" max="40" aria-label="Maks SKS" />
                                            <InputError :message="errorOf(`batas_sks.${index}.maks_sks`)" />
                                        </td>
                                        <td class="kolom-aksi">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                aria-label="Hapus baris"
                                                :disabled="form.batas_sks.length === 1"
                                                @click="form.batas_sks.splice(index, 1)"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.batas_sks" />
                    <Button type="button" variant="outline" size="sm" class="mt-3" @click="form.batas_sks.push({ ips_minimal: '', maks_sks: '' })">
                        <Plus /> Tambah baris
                    </Button>

                    <div class="mt-6 grid max-w-xs gap-2">
                        <Label for="maks_sks_tanpa_ips" class="label-isian">Maks SKS bila belum ada IPS</Label>
                        <Input id="maks_sks_tanpa_ips" v-model="form.maks_sks_tanpa_ips" type="number" min="1" max="40" />
                        <p class="teks-bantu">Untuk mahasiswa baru atau yang nilai semester lalunya belum keluar.</p>
                        <InputError :message="form.errors.maks_sks_tanpa_ips" />
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <Button v-if="!props.belumDiatur" type="button" variant="outline" class="text-[#dd5b00]" @click="confirmOpen = true">
                            Hapus Batas Prodi
                        </Button>
                        <Button type="submit" :disabled="form.processing">Simpan Batas SKS</Button>
                    </div>
                </form>

                <AlertModal
                    :open="confirmOpen"
                    :description="`Hapus Batas SKS ${terpilih?.nama ?? ''}? Prodi ini akan kembali memakai Batas SKS global.`"
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
