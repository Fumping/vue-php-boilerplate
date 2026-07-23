<script setup>
// AUTH HOOK-POINT.
// Deze view is een skeleton. Vul in met je auth-provider naar keuze:
//   - Supabase: importeer supabase client, roep signInWithOAuth aan
//   - Firebase: signInWithPopup / signInWithRedirect
//   - Session-based: POST username/password naar api/login endpoint
//
// Zie CLAUDE.md sectie "Auth toevoegen" voor concrete voorbeelden.

function loginWithProvider(provider) {
  // TODO: implementeer per provider
  console.log('TODO: login met', provider)
}
</script>

<template>
  <main class="login">
    <h1>Inloggen</h1>
    <p>Auth is nog niet geactiveerd. Zie CLAUDE.md voor instructies.</p>

    <div class="providers">
      <button type="button" @click="loginWithProvider('microsoft')">
        Inloggen met Microsoft
      </button>
      <button type="button" @click="loginWithProvider('google')">
        Inloggen met Google
      </button>
    </div>
  </main>
</template>
