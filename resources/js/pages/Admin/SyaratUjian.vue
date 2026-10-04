<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Prodi = { id: number; nama: string; kode: string; diatur: boolean };
type Pengaturan = {
    syarat_ujian_aktif: boolean;
    min_kehadiran_ujian: number;
    izin_sakit_dihitung_hadir: boolean;
    huruf_maks_remidi: string | null;
};

const props = defineProps<{
    bolehUmum: boolean;
    prodi: Prodi[];
    prodiId: number | null;
    belumDiatur: boolean;
    pengaturan: Pengaturan;
    hurufOptions: string[];
}>();

const page = usePage<{ flash: { success?: string; error?: string } }>();
const terpilih = computed(() => props.prodi.find((item) => item.id === props.prodiId) ?? null);

const pilihProdi = (event: Event) =>
    router.get(route('admin.syarat-ujian.index'), { prodi: (event.target as HTMLSelectElement).value }, { preserveScroll: true });

const form = useForm({ ...props.pengaturan, huruf_maks_remidi: props.pengaturan.huruf_maks_remidi ?? '' });

const simpan = () =>
    form
        .transform((data) => ({ ...data, huruf_maks_remidi: data.huruf_maks_remidi || null }))
        .put(props.prodiId ? route('admin.syarat-ujian.update', props.prodiId) : route('admin.syarat-ujian.update-umum'), { preserveScroll: true });

const confirmOpen = ref(false);
const hapus = () => {
    if (!props.prodiId) return;
    router.delete(route('admin.syarat-ujian.destroy', props.prodiId), { preserveScroll: true, onFinish: () => (confirmOpen.value = false) });
};
</script>

<template>
    <Head title="Syarat Ujian & Remedial" />
    <AppLayout :breadcrumbs="[{ title: 'Syarat Ujian & Remedial', href: route('admin.syarat-ujian.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Syarat Ujian &amp; Remedial</h1>
                        <p class="deskripsi-halaman">
                            Atur syarat kehadiran untuk mengikuti UTS/UAS dan batas huruf setelah remedial, untuk semua prodi atau per program studi.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div v-if="!props.prodi.length && !props.bolehUmum" class="kartu p-6">
                    <p class="teks-bantu">Belum ada program studi.</p>
                </div>

                <form v-else class="kartu p-6" @submit.prevent="simpan">
                    <div class="grid max-w-md gap-2">
                        <Label for="prodi" class="label-isian">Berlaku untuk</Label>
                        <select id="prodi" :value="props.prodiId ?? 'umum'" class="isian isian-pilih" @change="pilihProdi">
                            <option v-if="props.bolehUmum" value="umum">Umum (semua prodi yang belum diatur)</option>
                            <option v-for="item in props.prodi" :key="item.id" :value="item.id">
                                {{ item.nama }} ({{ item.kode }}){{ item.diatur ? '' : ' · memakai pengaturan umum' }}
                            </option>
                        </select>
                    </div>

                    <p v-if="props.belumDiatur" class="alert-info mt-4" role="status">
                        {{ terpilih?.nama }} belum punya pengaturan sendiri, jadi memakai pengaturan umum (isian di bawah). Simpan untuk menetapkan
                        pengaturan khusus prodi ini.
                    </p>

                    <h2 class="judul-bagian mt-6">Syarat Kehadiran Ujian</h2>
                    <p class="teks-bantu mt-1">
                        Kehadiran dihitung dari pertemuan kuliah yang sudah selesai: UTS memakai pertemuan sebelum UTS, UAS memakai semua pertemuan.
                    </p>

                    <Label for="syarat_ujian_aktif" class="label-isian mt-4 flex w-fit items-start gap-2.5 font-normal">
                        <Checkbox id="syarat_ujian_aktif" v-model="form.syarat_ujian_aktif" class="mt-0.5" />
                        <span>
                            Terapkan syarat kehadiran ujian: mahasiswa di bawah batas minimal ditandai <strong>tidak memenuhi syarat</strong> UTS/UAS
                            dan <strong>tidak bisa mencetak kartu ujian</strong> jenis itu, kecuali mendapat dispensasi.
                        </span>
                    </Label>
                    <InputError :message="form.errors.syarat_ujian_aktif" />

                    <div class="mt-4 grid max-w-xs gap-2">
                        <Label for="min_kehadiran_ujian" class="label-isian">Minimal kehadiran (%)</Label>
                        <Input id="min_kehadiran_ujian" v-model="form.min_kehadiran_ujian" type="number" min="0" max="100" />
                        <InputError :message="form.errors.min_kehadiran_ujian" />
                    </div>

                    <Label for="izin_sakit_dihitung_hadir" class="label-isian mt-4 flex w-fit items-start gap-2.5 font-normal">
                        <Checkbox id="izin_sakit_dihitung_hadir" v-model="form.izin_sakit_dihitung_hadir" class="mt-0.5" />
                        <span>Izin dan sakit dihitung hadir untuk syarat ujian. Bila tidak dicentang, izin dan sakit dihitung tidak hadir.</span>
                    </Label>
                    <InputError :message="form.errors.izin_sakit_dihitung_hadir" />

                    <h2 class="judul-bagian mt-8">Remedial</h2>
                    <p class="teks-bantu mt-1">
                        Huruf tertinggi yang boleh diberikan kepada peserta remidi setelah ujian remidinya selesai. Pilih Bebas bila tidak dibatasi.
                    </p>
                    <div class="mt-4 grid max-w-xs gap-2">
                        <Label for="huruf_maks_remidi" class="label-isian">Huruf maksimal setelah remidi</Label>
                        <select id="huruf_maks_remidi" v-model="form.huruf_maks_remidi" class="isian isian-pilih">
                            <option value="">Bebas</option>
                            <option v-for="huruf in props.hurufOptions" :key="huruf" :value="huruf">{{ huruf }}</option>
                        </select>
                        <InputError :message="form.errors.huruf_maks_remidi" />
                    </div>

                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <Button
                            v-if="props.prodiId && !props.belumDiatur"
                            type="button"
                            variant="outline"
                            class="text-[#dd5b00]"
                            @click="confirmOpen = true"
                        >
                            Hapus Pengaturan Prodi
                        </Button>
                        <Button type="submit" :disabled="form.processing">Simpan</Button>
                    </div>
                </form>

                <AlertModal
                    :open="confirmOpen"
                    :description="`Hapus pengaturan ${terpilih?.nama ?? ''}? Prodi ini akan kembali memakai pengaturan umum.`"
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
