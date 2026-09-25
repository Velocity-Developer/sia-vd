<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, MODE_UJIAN, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type Opsi = { id: number; name: string };
type Ujian = {
    id: number;
    kelas_id: number;
    jenis: JenisUjian;
    mode: ModeUjian;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang_id: number | null;
    pengawas: string | null;
    petunjuk: string | null;
    status: 'draf' | 'terbit';
    kelas_kuliah?: { kode_kelas: string; mata_kuliah?: { nama_matkul: string } | null } | null;
};

const props = defineProps<{
    ujian: Ujian | null;
    tahunAkademikId: number | null;
    kelasOptions: Opsi[];
    kelasRemidiOptions: Opsi[];
    batasRemidi: { bayar: string | null; nilai: string | null };
    menungguVerifikasi: Record<number, number>;
    ruangOptions: Opsi[];
    jenisAwal: JenisUjian | null;
}>();

const form = useForm({
    kelas_id: '' as number | string,
    jenis: (props.jenisAwal ?? 'uts') as JenisUjian,
    mode: props.ujian?.mode ?? ('tatap_muka' as ModeUjian),
    tanggal: props.ujian?.tanggal.slice(0, 10) ?? '',
    jam_mulai: jam(props.ujian?.jam_mulai),
    jam_akhir: jam(props.ujian?.jam_akhir),
    ruang_id: (props.ujian?.ruang_id ?? '') as number | string,
    pengawas: props.ujian?.pengawas ?? '',
    petunjuk: props.ujian?.petunjuk ?? '',
    status: props.ujian?.status ?? ('draf' as 'draf' | 'terbit'),
    abaikan_bentrok_mahasiswa: false,
});

// Kolom kelas & jenis hanya dikirim saat membuat jadwal baru.
const simpan = () => {
    form.transform((data) => {
        const { kelas_id, jenis, ...sisa } = data;
        return { ...(props.ujian ? {} : { kelas_id, jenis }), ...sisa, ruang_id: data.ruang_id || null };
    });
    if (props.ujian) form.put(route('admin.ujian.update', props.ujian.id));
    else form.post(route('admin.ujian.store'));
};
const bentrokMahasiswa = computed(() => (form.errors.tanggal ?? '').includes('punya ujian lain'));
const remidi = computed(() => (props.ujian?.jenis ?? form.jenis) === 'remidi');
// Remidi hanya untuk kelas yang daftar remidinya dikunci dan punya peserta lunas.
const kelasTerpilih = computed(() => Number(props.ujian?.kelas_id ?? form.kelas_id) || 0);
const jumlahMenunggu = computed(() => (remidi.value ? (props.menungguVerifikasi[kelasTerpilih.value] ?? 0) : 0));
const opsiKelas = computed(() => (remidi.value ? props.kelasRemidiOptions : props.kelasOptions));
watch(
    () => form.jenis,
    () => {
        if (!opsiKelas.value.some((k) => k.id === form.kelas_id)) form.kelas_id = '';
    },
);
const judul = computed(() => (props.ujian ? 'Ubah Jadwal Ujian' : 'Tambah Jadwal Ujian'));

const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
const sel = 'h-10 w-full rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px]';
</script>

<template>
    <Head :title="judul" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Jadwal Ujian', href: route('admin.ujian.index') },
            { title: judul, href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[860px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">{{ judul }}</h1>
                        <p v-if="props.ujian" class="text-sm text-[#615d59]">
                            {{ JENIS_UJIAN[props.ujian.jenis] }} · {{ props.ujian.kelas_kuliah?.kode_kelas }} —
                            {{ props.ujian.kelas_kuliah?.mata_kuliah?.nama_matkul }}
                        </p>
                    </div>
                    <Link :href="route('admin.ujian.index', { tahun_akademik_id: props.tahunAkademikId })">
                        <Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black">Kembali</Button>
                    </Link>
                </div>

                <form class="flex flex-col gap-4" @submit.prevent="simpan">
                    <section v-if="!props.ujian" class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                        <div class="grid content-start items-start gap-4 sm:grid-cols-[1fr,260px]">
                            <div class="grid content-start gap-2">
                                <Label for="kelas_id">Kelas</Label>
                                <SearchSelect
                                    id="kelas_id"
                                    v-model="form.kelas_id"
                                    :options="opsiKelas"
                                    placeholder="Pilih kelas"
                                    search-placeholder="Cari kode kelas atau mata kuliah"
                                    required
                                />
                                <p v-if="remidi && !props.kelasRemidiOptions.length" class="text-xs text-[#a39e98]">
                                    Belum ada kelas yang daftar remidinya dikunci dan punya peserta lunas.
                                </p>
                                <p v-if="jumlahMenunggu" class="text-xs text-[#dd5b00]">
                                    {{ jumlahMenunggu }} bukti bayar kelas ini masih menunggu verifikasi. Verifikasi dulu di Tagihan Remidi agar
                                    pesertanya tidak tertinggal ujian.
                                </p>
                                <InputError :message="form.errors.kelas_id" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label>Jenis ujian</Label>
                                <div class="flex h-10 items-center gap-4 text-sm">
                                    <label v-for="(label, j) in JENIS_UJIAN" :key="j" class="flex items-center gap-2">
                                        <input v-model="form.jenis" type="radio" :value="j" class="size-4 accent-[#0075de]" /> {{ label }}
                                    </label>
                                </div>
                                <InputError :message="form.errors.jenis" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-black">Mode ujian</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Mode ujian">
                            <label
                                v-for="m in MODE_UJIAN"
                                :key="m.value"
                                class="flex cursor-pointer gap-3 rounded-lg border p-3"
                                :class="form.mode === m.value ? 'border-[#0075de] bg-[#f6f9fd]' : 'border-[#e6e6e6]'"
                            >
                                <input v-model="form.mode" type="radio" :value="m.value" class="mt-1 size-4 shrink-0 accent-[#0075de]" />
                                <span>
                                    <span class="block text-sm font-medium text-black">{{ m.label }}</span>
                                    <span class="block text-xs text-[#615d59]">{{ m.teks }}</span>
                                </span>
                            </label>
                        </div>
                        <InputError :message="form.errors.mode" />
                    </section>

                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-black">Waktu &amp; tempat</h2>
                        <div class="mt-4 grid content-start items-start gap-4 sm:grid-cols-3">
                            <div class="grid content-start gap-2">
                                <Label for="tanggal">Tanggal</Label>
                                <Input id="tanggal" v-model="form.tanggal" type="date" :class="inp" required />
                                <p v-if="remidi && props.batasRemidi.bayar" class="text-xs text-[#a39e98]">
                                    Sesudah {{ formatTanggal(props.batasRemidi.bayar, false) }} s.d.
                                    {{ formatTanggal(props.batasRemidi.nilai, false) }}
                                </p>
                                <InputError :message="form.errors.tanggal" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label for="jam_mulai">Jam mulai</Label>
                                <Input id="jam_mulai" v-model="form.jam_mulai" type="time" :class="inp" required />
                                <InputError :message="form.errors.jam_mulai" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label for="jam_akhir">Jam selesai</Label>
                                <Input id="jam_akhir" v-model="form.jam_akhir" type="time" :class="inp" required />
                                <InputError :message="form.errors.jam_akhir" />
                            </div>
                            <div v-if="form.mode === 'tatap_muka'" class="grid content-start gap-2 sm:col-span-2">
                                <Label for="ruang_id">Ruang</Label>
                                <select id="ruang_id" v-model="form.ruang_id" :class="sel" required>
                                    <option value="">Pilih ruang</option>
                                    <option v-for="r in props.ruangOptions" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                                <InputError :message="form.errors.ruang_id" />
                            </div>
                            <p v-else class="text-sm text-[#615d59] sm:col-span-2">Ujian online dikerjakan di SIA, tanpa ruang.</p>
                            <div class="grid content-start gap-2">
                                <Label for="pengawas">Pengawas (opsional)</Label>
                                <Input id="pengawas" v-model="form.pengawas" maxlength="255" :class="inp" />
                                <InputError :message="form.errors.pengawas" />
                            </div>
                            <div class="grid content-start gap-2 sm:col-span-3">
                                <Label for="petunjuk">Petunjuk untuk mahasiswa (opsional)</Label>
                                <textarea
                                    id="petunjuk"
                                    v-model="form.petunjuk"
                                    rows="3"
                                    maxlength="5000"
                                    placeholder="mis. Buku tertutup, bawa kalkulator, datang 15 menit lebih awal"
                                    class="rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                                />
                                <InputError :message="form.errors.petunjuk" />
                            </div>
                        </div>
                        <label v-if="bentrokMahasiswa || form.abaikan_bentrok_mahasiswa" class="mt-4 flex items-center gap-2 text-sm text-[#8a5a00]">
                            <input v-model="form.abaikan_bentrok_mahasiswa" type="checkbox" class="size-4 accent-[#0075de]" />
                            Tetap simpan walau ada mahasiswa dengan dua ujian di jam yang sama
                        </label>
                    </section>

                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-semibold text-black">Status</h2>
                        <div class="mt-3 flex flex-wrap gap-6 text-sm">
                            <label class="flex items-center gap-2"
                                ><input v-model="form.status" type="radio" value="draf" class="size-4 accent-[#0075de]" /> Draf (belum tampil ke
                                mahasiswa)</label
                            >
                            <label class="flex items-center gap-2"
                                ><input v-model="form.status" type="radio" value="terbit" class="size-4 accent-[#0075de]" /> Terbit</label
                            >
                        </div>
                        <InputError :message="form.errors.status" />
                    </section>

                    <div class="flex justify-end">
                        <Button type="submit" class="h-10 rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]" :disabled="form.processing"
                            >Simpan</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
