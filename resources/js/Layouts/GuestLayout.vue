<script setup>
import Logo from '@/Components/Logo.vue'
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
    <!-- Panel kiri: identitas PMKCM V -->
    <section class="guest-hero relative flex flex-col justify-between overflow-hidden border-b-[6px] border-seal-500 bg-ink-950 p-7 text-ink-100 lg:border-b-0 lg:border-r-[6px] lg:p-12">
      <div class="relative flex items-center gap-4 text-white">
        <Logo :size="58" />
        <div>
          <b class="block font-serif text-3xl uppercase leading-none tracking-wider">PMKCM V</b>
          <small class="text-[11px] font-semibold tracking-[.32em] text-seal-400">INDARUNG 5</small>
        </div>
      </div>
      <div class="relative">
        <h1 class="my-7 font-serif text-5xl uppercase leading-[.95] text-white lg:text-7xl">
          Manajemen<br /><span class="text-seal-400">SDM</span>
        </h1>
        <p class="max-w-md text-ink-400">
          Data karyawan, perintah kerja, laporan harian, dan penilaian outsourcing dalam satu platform terpadu untuk operasional pabrik.
        </p>
      </div>
      <div class="relative mt-8 text-xs uppercase tracking-[.2em] text-ink-400">Akses terbatas · Akun dibuat oleh Admin SDM</div>
    </section>

    <!-- Panel kanan: form -->
    <section class="grid place-items-center bg-paper p-8 dark:bg-ink-950">
      <div class="w-full max-w-sm">
        <slot />
      </div>
    </section>
  </div>
</template>

<style>
.guest-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(135deg, transparent 0 38px, rgba(255, 255, 255, 0.035) 38px 40px);
}
.guest-hero::after {
  content: '';
  position: absolute;
  right: -140px;
  bottom: -140px;
  width: 460px;
  height: 460px;
  background: rgba(242, 107, 29, 0.07);
  clip-path: polygon(25% 5%, 75% 5%, 100% 50%, 75% 95%, 25% 95%, 0 50%);
}
</style>
