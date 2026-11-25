<script setup>
import { computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import MemberPicker from '@/Components/MemberPicker.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({ order: Object, employees: Array, today: String })
const page = usePage()
const teams = computed(() => page.props.teams ?? [])
const isEdit = !!props.order

const form = useForm({
  order_date: props.order?.order_date ?? props.today,
  start_date: props.order?.start_date ?? props.today,
  leader_id: props.order?.leader_id ?? '',
  member_ids: props.order?.member_ids ?? [],
  description: props.order?.description ?? '',
  team: props.order?.team ?? '',
  area: props.order?.area ?? '',
  status: 'direncanakan',
})

// Kepala kerja tidak boleh sekaligus menjadi anggota.
watch(() => form.leader_id, (id) => {
  form.member_ids = form.member_ids.filter((m) => String(m) !== String(id))
})

function submit() {
  if (isEdit) form.put(route('work-orders.update', props.order.id))
  else form.post(route('work-orders.store'))
}
</script>

<template>
  <AppLayout>
    <Head :title="isEdit ? 'Ubah Perintah Kerja' : 'Buat Perintah Kerja'" />
    <template #title>{{ isEdit ? `Ubah ${order.number}` : 'Buat Perintah Kerja' }}</template>

    <form class="card card-body max-w-3xl space-y-4" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
          <label class="label">Tanggal Perintah *</label>
          <input v-model="form.order_date" type="date" class="input" required />
          <p v-if="form.errors.order_date" class="error-text">{{ form.errors.order_date }}</p>
        </div>
        <div>
          <label class="label">Tanggal Mulai</label>
          <input v-model="form.start_date" type="date" class="input" />
        </div>
        <div v-if="!isEdit">
          <label class="label">Status Awal</label>
          <select v-model="form.status" class="input"><option value="direncanakan">Direncanakan</option><option value="berjalan">Berjalan</option></select>
        </div>
      </div>

      <div>
        <label class="label">Kepala Kerja *</label>
        <select v-model="form.leader_id" class="input" required>
          <option value="" disabled>Pilih kepala kerja…</option>
          <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.full_name }} ({{ e.nip }})</option>
        </select>
        <p v-if="form.errors.leader_id" class="error-text">{{ form.errors.leader_id }}</p>
      </div>

      <div>
        <label class="label">Anggota Kerja *</label>
        <MemberPicker v-model="form.member_ids" :employees="employees" :exclude="form.leader_id" />
        <p v-if="form.errors.member_ids" class="error-text">{{ form.errors.member_ids }}</p>
      </div>

      <div>
        <label class="label">Uraian Pekerjaan *</label>
        <textarea v-model="form.description" rows="4" class="input" required></textarea>
        <p v-if="form.errors.description" class="error-text">{{ form.errors.description }}</p>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Tim</label>
          <select v-model="form.team" class="input"><option value="">—</option><option v-for="t in teams" :key="t" :value="t">{{ t }}</option></select>
        </div>
        <div>
          <label class="label">Area / Peralatan (opsional)</label>
          <input v-model="form.area" class="input" placeholder="mis. Kiln 5 - Burner" />
        </div>
      </div>

      <div class="flex gap-2">
        <button type="submit" :disabled="form.processing" class="btn-accent">{{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Buat Perintah Kerja' }}</button>
        <Link :href="isEdit ? route('work-orders.show', order.id) : route('work-orders.index')" class="btn-secondary">Batal</Link>
      </div>
    </form>
  </AppLayout>
</template>
