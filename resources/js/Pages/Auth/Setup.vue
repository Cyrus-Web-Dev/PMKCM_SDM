<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
  form.post(route('setup.store'), { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <GuestLayout>
    <Head title="Setup Awal" />
    <h1 class="text-3xl font-serif font-semibold text-ink-900 dark:text-ink-100">Setup Awal</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">Belum ada akun. Buat akun <b>Owner</b> (super admin) untuk memulai. Halaman ini otomatis tertutup setelahnya.</p>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Nama Lengkap</label>
        <input v-model="form.name" required autofocus class="input" />
        <p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p>
      </div>
      <div>
        <label class="label">Email (untuk login)</label>
        <input v-model="form.email" type="email" required class="input" />
        <p v-if="form.errors.email" class="error-text">{{ form.errors.email }}</p>
      </div>
      <div>
        <label class="label">Password</label>
        <input v-model="form.password" type="password" required autocomplete="new-password" class="input" />
        <p class="mt-1 text-xs text-ink-400">Minimal 8 karakter, huruf besar &amp; kecil, dan angka.</p>
        <p v-if="form.errors.password" class="error-text">{{ form.errors.password }}</p>
      </div>
      <div>
        <label class="label">Ulangi Password</label>
        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="input" />
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent w-full">{{ form.processing ? 'Menyiapkan…' : 'Buat Akun Owner' }}</button>
    </form>
  </GuestLayout>
</template>
