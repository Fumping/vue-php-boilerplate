import { createRouter, createWebHistory } from 'vue-router'

// AUTH HOOK-POINT (commented-out door bewuste keuze).
// Om auth aan te zetten:
//   1. Implementeer de `requireAuth` guard hieronder (voorbeeld: check
//      localStorage token, Supabase session, cookie, wat je maar wilt).
//   2. Voeg `beforeEnter: requireAuth` toe aan de routes die je wilt
//      beschermen.
//   3. Overweeg ook een globale `router.beforeEach` als je 't voor alle
//      routes wilt behalve /login.
//
// export async function requireAuth(to, from, next) {
//   const authenticated = false // TODO: echte auth-check
//   if (!authenticated) {
//     return next({ path: '/login', query: { redirect: to.fullPath } })
//   }
//   next()
// }

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: () => import('../views/LoginView.vue'),
      meta: { title: 'Inloggen' },
    },
    {
      path: '/',
      component: () => import('../views/HomeView.vue'),
      meta: { title: 'Home' },
    },
  ],
})

router.afterEach((to) => {
  document.title = to.meta?.title ? `${to.meta.title} | Boilerplate` : 'Boilerplate'
})

export default router
