<script setup>
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ role: Object, groups: Array })
const isEdit = !!props.role

const form = useForm({
  name: props.role?.name ?? '',
  permissions: props.role?.permissions ?? [],
})

const has = (name) => form.permissions.includes(name)
const viewOf = (g) => g.permissions.find((p) => p.name.endsWith('.view'))?.name
const total = computed(() => form.permissions.length)

// Mencentang izin apa pun pada sebuah modul otomatis mencentang "Lihat"; mencabut "Lihat" mencabut semuanya.
function toggle(g, p) {
  const next = new Set(form.permissions)
  const view = viewOf(g)
  if (next.has(p.name)) {
    next.delete(p.name)
    if (p.name === view) g.permissions.forEach((x) => next.delete(x.name))
  } else {
    next.add(p.name)
    if (view) next.add(view)
  }
  form.permissions = [...next]
}

function setGroup(g, names) {
  const mine = new Set(g.permissions.map((p) => p.name))
  form.permissions = [...form.permissions.filter((n) => !mine.has(n)), ...names]
}
const viewOnly = (g) => setGroup(g, viewOf(g) ? [viewOf(g)] : [])
const full = (g) => setGroup(g, g.permissions.map((p) => p.name))
const clear = (g) => setGroup(g, [])

function submit() {
  if (isEdit) form.put(route('roles.update', props.role.id))
  else form.post(route('roles.store'))
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Ubah Role' : 'Role Baru'" />
    <template #title>{{ isEdit ? `Ubah Role: ${role.name}` : 'Role Baru' }}</template>

    <form class="space-y-4" @submit.prevent="submit">
      <div class="card card-body max-w-xl">
        <label class="label">Nama Role *</label>
        <input v-model="form.name" class="input" :disabled="role?.protected" placeholder="mis. KA Unit, KA Gudang" required />
        <p v-if="role?.protected" class="mt-1 text-xs text-ink-400">Nama role sistem ini tidak bisa diganti.</p>
        <p v-if="form.errors.name" class="error-text">{{ form.errors.name }}</p>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div v-for="g in groups" :key="g.key" class="card card-body space-y-3">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="font-serif font-semibold">{{ g.label }}</h3>
            <div class="flex gap-1 text-xs">
              <button v-if="viewOf(g) && g.permissions.length > 1" type="button" class="btn-ghost btn-sm" @click="viewOnly(g)">Hanya lihat</button>
              <button v-if="g.permissions.length > 1" type="button" class="btn-ghost btn-sm" @click="full(g)">Penuh</button>
              <button type="button" class="btn-ghost btn-sm" @click="clear(g)">Kosongkan</button>
            </div>
          </div>
          <p v-if="g.hint" class="text-xs text-ink-400">{{ g.hint }}</p>
          <label v-for="p in g.permissions" :key="p.name" class="flex cursor-pointer items-start gap-2 text-sm">
            <input type="checkbox" :checked="has(p.name)" class="mt-0.5 border-ink-300 text-seal-600 focus:ring-seal-500" @change="toggle(g, p)" />
            <span>{{ p.label }}</span>
          </label>
        </div>
      </div>

      <p v-if="form.errors.permissions" class="error-text">{{ form.errors.permissions }}</p>
      <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn-accent" :disabled="form.processing">{{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Buat Role' }}</button>
        <Link :href="route('roles.index')" class="btn-secondary">Batal</Link>
        <span class="text-sm text-ink-500">{{ total }} hak akses dipilih</span>
      </div>
    </form>
  </AppLayout>
</template>
