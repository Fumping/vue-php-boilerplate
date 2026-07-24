import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: () => import('../views/LoginView.vue'),
      meta: { title: 'Inloggen', guestOnly: true },
    },
    {
      path: '/change-password',
      component: () => import('../views/ChangePasswordView.vue'),
      meta: { title: 'Wachtwoord wijzigen', requiresAuth: true, allowPasswordChange: true },
    },
    {
      path: '/admin/users',
      component: () => import('../views/AdminUsersView.vue'),
      meta: { title: 'Gebruikers', requiresAuth: true, requiresAdmin: true },
    },
    {
      path: '/',
      component: () => import('../views/HomeView.vue'),
      meta: { title: 'Home', requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach(async (to) => {
  const { user, loadUser } = useAuth()
  await loadUser()

  if (to.meta.guestOnly && user.value) {
    return user.value.mustChangePassword ? '/change-password' : '/'
  }
  if (to.meta.requiresAuth && !user.value) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }
  if (
    user.value?.mustChangePassword
    && to.path !== '/change-password'
    && !to.meta.guestOnly
  ) {
    return '/change-password'
  }
  if (to.path === '/change-password' && user.value && !user.value.mustChangePassword) {
    return '/'
  }
  if (to.meta.requiresAdmin && user.value?.role !== 'admin') {
    return '/'
  }
})

router.afterEach((to) => {
  document.title = to.meta?.title ? `${to.meta.title} | Boilerplate` : 'Boilerplate'
})

export default router
