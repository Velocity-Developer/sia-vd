<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { UserRoundPlus } from 'lucide-vue-next';
import { ref } from 'vue';

// Tombol + dialog "Salin ke Master Mahasiswa" untuk calon maba yang diterima. NIM diisi admin dan menjadi username.
const props = defineProps<{ id: number; nama: string; ringkas?: boolean }>();

const buka = ref(false);
const form = useForm({ nim: '' });

const bukaDialog = () => {
    form.reset();
    form.clearErrors();
    buka.value = true;
};
const salin = () =>
    form.post(route('admin.pendaftar-pmb.salin', props.id), {
        preserveScroll: true,
        onSuccess: () => (buka.value = false),
    });
</script>

<template>
    <Button
        v-if="props.ringkas"
        variant="outline"
        size="icon-sm"
        class="text-[#0075de]"
        title="Salin ke Master Mahasiswa"
        aria-label="Salin ke Master Mahasiswa"
        @click="bukaDialog"
        ><UserRoundPlus
    /></Button>
    <Button v-else @click="bukaDialog"><UserRoundPlus /> Salin ke Master Mahasiswa</Button>
    <Teleport to="body">
        <div v-if="buka" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="buka = false">
            <form class="kartu w-full max-w-md p-6 text-left shadow-xl" @submit.prevent="salin">
                <h3 class="judul-bagian">Salin ke Master Mahasiswa</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    Akun mahasiswa untuk {{ props.nama }} dibuat dengan NIM di bawah sebagai username, lalu tautan atur sandi dan verifikasi email
                    dikirim. Dosen wali diatur lewat Set Penasehat Akademik.
                </p>
                <label class="mt-4 grid gap-2">
                    <span class="label-isian">NIM</span>
                    <Input v-model="form.nim" maxlength="50" required autofocus />
                    <InputError :message="form.errors.nim" />
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="buka = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing || !form.nim.trim()">Salin</Button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
