<script setup>
// AUTH HOOK-POINT.
// Deze view is een skeleton. Implementeer `loginWithProvider(provider)`
// hieronder om echte auth aan te haken. Zie CLAUDE.md sectie
// "Auth toevoegen" voor voorbeelden (Supabase, Firebase, session-based).

function loginWithProvider(provider) {
  console.log('TODO: login met', provider)
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <div class="w-full max-w-sm space-y-6 bg-white rounded-lg shadow p-8">
      <h1 class="text-2xl font-bold text-slate-800">Inloggen</h1>
      <p class="text-sm text-slate-500">
        Auth is nog niet geactiveerd. Zie CLAUDE.md voor instructies.
      </p>

      <div class="space-y-2">
        <button
          type="button"
          class="w-full rounded border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-100"
          @click="loginWithProvider('microsoft')"
        >
          Inloggen met Microsoft
        </button>
        <button
          type="button"
          class="w-full rounded border border-slate-300 px-4 py-2 text-slate-700 hover:bg-slate-100"
          @click="loginWithProvider('google')"
        >
          Inloggen met Google
        </button>
      </div>
    </div>
  </main>
</template>
