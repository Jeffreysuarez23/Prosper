import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import DashboardLayout from '../views/DashboardLayout.vue'
import DashboardView from '../views/DashboardView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { requiresGuest: true }
    },
    {
      path: '/',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: DashboardView },
        { path: 'movimientos', name: 'movimientos', component: () => import('../views/MovimientosView.vue') },
        { path: 'estadisticas', name: 'estadisticas', component: () => import('../views/EstadisticasView.vue'), meta: { requiresPro: true } },
        { path: 'objetivos', name: 'objetivos', component: () => import('../views/ObjetivosView.vue') },
        { path: 'gastos-fijos', name: 'gastos-fijos', component: () => import('../views/GastosFijosView.vue'), meta: { requiresPro: true } },
        { path: 'tarjetas-credito', name: 'tarjetas-credito', component: () => import('../views/TarjetasCreditoView.vue'), meta: { requiresPro: true } },
        { path: 'notificaciones', name: 'notificaciones', component: () => import('../views/NotificacionesView.vue'), meta: { requiresPro: true } },
        { path: 'perfil', name: 'perfil', component: () => import('../views/PerfilView.vue') },
      ]
    }
  ]
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  
  if (to.meta.requiresAuth && !token) {
    next({ name: 'login' });
    return;
  } 
  
  if (to.meta.requiresGuest && token) {
    next({ name: 'dashboard' });
    return;
  }
  
  if (to.meta.requiresPro) {
    const userStr = localStorage.getItem('user');
    let plan = 'gratis';
    if (userStr) {
      try {
        const user = JSON.parse(userStr);
        plan = user.plan || user.membresia?.plan || 'gratis';
      } catch(e) {}
    }
    
    if (plan === 'gratis') {
      window.dispatchEvent(new CustomEvent('open-membership-modal'));
      if (from.name) {
        next(false); // Cancel navigation and stay on current page
      } else {
        next({ name: 'dashboard' }); // Redirect to dashboard if loading directly
      }
      return;
    }
  }
  
  if (to.meta.requiresAdmin) {
    const userStr = localStorage.getItem('user');
    let roleId = null;
    if (userStr) {
      try {
        const user = JSON.parse(userStr);
        roleId = user.role_id;
      } catch(e) {}
    }
    
    if (roleId !== 1) {
      next({ name: 'dashboard' }); // Redirect to dashboard if not admin
      return;
    }
  }
  
  next();
})

export default router
