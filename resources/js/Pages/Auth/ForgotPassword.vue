<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({ status: String })

const form = useForm({ email: '', requester_name: '' })

function submit() {
  form.post(route('password.request.store'), { onSuccess: () => form.reset() })
}
</script>

<template>
  <GuestLayout>
    <Head title="Lupa Password" />
    <h1 class="text-3xl font-serif font-semibold text-ink-900 dark:text-ink-100">Lupa Password</h1>
    <p class="mt-1 text-sm text-ink-500 dark:text-ink-400">
      Kirim permintaan ke Owner. Setelah diproses, Owner akan memberikan password sementara secara langsung.
    </p>

    <div v-if="status" class="mt-4 border-l-4 border-emerald-600 bg-white px-4 py-3 text-sm font-medium text-emerald-800 dark:bg-ink-800 dark:text-emerald-300">{{ status }}</div>
    <div v-if="form.errors.email" class="mt-4 border-l-4 border-red-600 bg-white px-4 py-3 text-sm font-medium text-red-800 dark:bg-ink-800 dark:text-red-300">{{ form.errors.email }}</div>

    <form @submit.prevent="submit" class="mt-6 space-y-4">
      <div>
        <label class="label">Email Akun</label>
        <input v-model="form.email" type="email" required autofocus class="input" />
      </div>
      <div>
        <label class="label">Nama Lengkap (opsional)</label>
        <input v-model="form.requester_name" class="input" placeholder="Membantu Owner memastikan orangnya" />
      </div>
      <button type="submit" :disabled="form.processing" class="btn-accent w-full">{{ form.processing ? 'Mengirim…' : 'Kirim Permintaan' }}</button>
    </form>

    <p class="mt-6 text-center text-sm"><Link :href="route('login')" class="font-medium text-seal-600 hover:underline dark:text-seal-400">&larr; Kembali ke login</Link></p>
  </GuestLayout>
</template>
