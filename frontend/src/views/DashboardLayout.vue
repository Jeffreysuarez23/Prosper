<script setup>
import { ref, onMounted, provide, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../services/api'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))
const refreshKey = ref(0) // Usado para recargar la vista hija sin recargar la página

const showMembershipModal = ref(false)
const billingCycle = ref('monthly')
const isSidebarOpen = ref(false)

const userPlan = computed(() => user.value.membresia?.plan || 'gratis')
const userBillingCycle = computed(() => user.value.membresia?.billing_cycle || 'monthly')
const membershipEndsAt = computed(() => user.value.membresia?.ends_at ? new Date(user.value.membresia.ends_at).toLocaleDateString() : '')

const handleNavClick = (e, featureLevel) => {
  if (featureLevel === 'ultra' && userPlan.value === 'gratis') {
    e.preventDefault()
    showMembershipModal.value = true
  }
}

// Obtener inicial
const userInicial = user.value.name ? user.value.name.charAt(0).toUpperCase() : 'U'

// Tema
const currentTheme = ref(localStorage.getItem('prosper-theme') || 'dark')
const setTheme = (theme) => {
  currentTheme.value = theme
  localStorage.setItem('prosper-theme', theme)
  document.documentElement.setAttribute('data-theme', theme)
}

const handleLogout = async () => {
  try {
    await api.post('/logout')
  } catch (e) {
    console.error(e)
  }
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  router.push('/login')
}

// Compute title and subtitle depending on the current route
const getPageInfo = () => {
  const path = route.path
  if (path === '/movimientos') return { title: 'Movimientos', subtitle: 'Historial completo de tus ingresos y gastos.' }
  if (path === '/estadisticas') return { title: 'Estadísticas', subtitle: 'Analiza tu desempeño financiero con gráficos detallados.' }
  if (path === '/objetivos') return { title: 'Objetivos', subtitle: 'Supervisa el progreso de tus metas de ahorro.' }
  if (path === '/gastos-fijos') return { title: 'Gastos Fijos', subtitle: 'Administra tus compromisos y pagos recurrentes.' }
  if (path === '/tarjetas-credito') return { title: 'Tarjetas de Crédito', subtitle: 'Controla tus tarjetas, deudas y fechas de pago.' }
  if (path === '/notificaciones') return { title: 'Notificaciones', subtitle: 'Mantente al tanto de tus pagos pendientes y alertas.' }
  if (path === '/perfil') return { title: 'Mi Perfil', subtitle: 'Administra tu información personal y seguridad.' }
  return { title: 'Aquí está tu resumen financiero.', subtitle: 'Controla tus ingresos, gastos y metas de forma sencilla.' }
}

const showTxModal = ref(false)
const isSubmitting = ref(false)
const txData = ref({
  tipo: 'ingreso',
  monto: '',
  fecha: new Date().toISOString().split('T')[0],
  categoria: 'Salario',
  descripcion: '',
  metodo_pago: 'efectivo',
  metodo_pago_otro: ''
})

const txEditId = ref(null)

const openTxModal = (tx = null) => {
  if (tx) {
    txEditId.value = tx.id
    const formatValue = Math.round(tx.monto)
    txData.value = {
      tipo: tx.tipo,
      monto: formatValue,
      fecha: tx.fecha.split('T')[0],
      categoria: tx.categoria,
      descripcion: tx.descripcion || '',
      metodo_pago: ['efectivo', 'tarjeta', 'transferencia'].includes(tx.metodo_pago) ? tx.metodo_pago : 'otro',
      metodo_pago_otro: !['efectivo', 'tarjeta', 'transferencia'].includes(tx.metodo_pago) ? tx.metodo_pago : ''
    }
    displayMonto.value = new Intl.NumberFormat('es-CO').format(formatValue)
  } else {
    txEditId.value = null
    txData.value = {
      tipo: 'ingreso',
      monto: '',
      fecha: new Date().toISOString().split('T')[0],
      categoria: 'Salario',
      descripcion: '',
      metodo_pago: 'efectivo',
      metodo_pago_otro: ''
    }
    displayMonto.value = ''
  }
  showTxModal.value = true
}
provide('openTxModal', openTxModal)

const displayMonto = ref('')

const onMontoInput = (e) => {
  // Solo permitir números
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayMonto.value = ''
    txData.value.monto = ''
    return
  }
  
  let numericValue = parseInt(rawValue)

  // Validar que el gasto no supere el balance total
  if (!txEditId.value && txData.value.tipo === 'gasto' && numericValue > headerBalance.value) {
    numericValue = headerBalance.value
    Swal.fire({
      toast: true,
      position: 'bottom-end',
      icon: 'warning',
      title: 'Saldo insuficiente, monto ajustado al máximo',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title' }
    })
  }

  // Format with dots
  displayMonto.value = new Intl.NumberFormat('es-CO').format(numericValue)
  // Save raw numeric value for the API payload
  txData.value.monto = numericValue.toString()
  e.target.value = displayMonto.value
}

const handleSaveTx = async () => {
  isSubmitting.value = true
  try {
    const payload = { ...txData.value }
    
    if (!txEditId.value && payload.tipo === 'gasto' && parseFloat(payload.monto) > headerBalance.value) {
      Swal.fire({
        icon: 'warning',
        title: 'Balance insuficiente',
        text: 'No puedes ingresar un gasto mayor a tu balance total.',
        confirmButtonText: 'Entendido',
        confirmButtonColor: 'var(--accent)',
        customClass: {
          popup: 'swal-custom-popup',
          title: 'swal-custom-title',
          htmlContainer: 'swal-custom-content',
          confirmButton: 'swal-custom-confirm'
        }
      })
      return
    }

    if (payload.metodo_pago === 'otro' && payload.metodo_pago_otro) {
      payload.metodo_pago = payload.metodo_pago_otro
    }
    
    if (txEditId.value) {
      await api.put(`/movimientos/${txEditId.value}`, payload)
    } else {
      await api.post('/movimientos', payload)
    }
    
    // Reset form
    txData.value = {
      tipo: 'ingreso',
      monto: '',
      fecha: new Date().toISOString().split('T')[0],
      categoria: 'Salario',
      descripcion: '',
      metodo_pago: 'efectivo',
      metodo_pago_otro: ''
    }
    displayMonto.value = ''
    
    showTxModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Movimiento guardado exitosamente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
    
    // Refresh global header balance
    await fetchHeaderBalance()
    
    // Refresh current child view without reloading the page
    refreshKey.value++
  } catch (error) {
    if (error.response && error.response.status === 403 && error.response.data.message) {
      // The global api.js interceptor will handle this and show the alert
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No se pudo guardar el movimiento.',
        background: 'var(--surface)',
        color: 'var(--text)',
        confirmButtonColor: 'var(--accent)',
        customClass: { popup: 'swal-custom-popup' }
      })
    }
  } finally {
    isSubmitting.value = false
  }
}

const headerBalance = ref(0)
const headerBalanceMensual = ref(0)
const isMonthlyBalance = ref(false)
const unreadNotifs = ref(0)
const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(value)
}

const fetchHeaderBalance = async () => {
  try {
    const res = await api.get('/dashboard')
    headerBalance.value = res.data.balance_global || 0
    headerBalanceMensual.value = res.data.balance_mensual || 0
    unreadNotifs.value = res.data.unread_notifications || 0
  } catch (error) {
    console.error('Error fetching global balance', error)
  }
}
provide('refreshHeaderBalance', fetchHeaderBalance)
provide('headerBalance', headerBalance)

onMounted(async () => {
  fetchHeaderBalance()
  
  // Refresh user data from server to keep membership updated
  try {
    const userRes = await api.get('/user')
    user.value = userRes.data
    localStorage.setItem('user', JSON.stringify(userRes.data))
    window.dispatchEvent(new CustomEvent('user-updated'))
  } catch (error) {
    console.error('Error fetching user data:', error)
  }
  
  window.addEventListener('open-membership-modal', () => {
    showMembershipModal.value = true
  })
})

// ========================
// PAYPAL CHECKOUT LOGIC
// ========================
const showCheckoutModal = ref(false)
const checkoutPlan = ref('')
const checkoutPrice = ref('')

const openCheckout = (planName) => {
  checkoutPlan.value = planName
  
  if (planName === 'ultra') {
    checkoutPrice.value = billingCycle.value === 'annual' ? '$249.000 COP' : '$24.900 COP'
  }

  showMembershipModal.value = false
  showCheckoutModal.value = true
  
  // Render paypal buttons after DOM updates
  setTimeout(() => renderPayPalButtons(), 200)
}

const renderPayPalButtons = async () => {
  const container = document.getElementById('paypal-button-container')
  if (container) container.innerHTML = '' // Clear previous

  try {
    // 1. Fetch Client ID
    const res = await api.get('/paypal/client-id')
    const clientId = res.data.client_id
    
    if (clientId === 'test') {
      console.warn("Usando credenciales de prueba de PayPal")
    }

    // 2. Load SDK dynamically if not loaded
    if (!window.paypal) {
      await new Promise((resolve, reject) => {
        const script = document.createElement('script')
        script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=USD`
        script.onload = resolve
        script.onerror = () => reject(new Error('Failed to load PayPal SDK. Revisa tu Client ID en el archivo .env'))
        document.body.appendChild(script)
      })
    }

    // 3. Render Buttons
    window.paypal.Buttons({
      style: {
        layout: 'vertical',
        color: 'blue',
        shape: 'rect',
        label: 'pay'
      },
      createOrder: async (data, actions) => {
        try {
          const res = await api.post('/paypal/create-order', {
            plan: checkoutPlan.value,
            billing_cycle: billingCycle.value
          })
          if (res.data.id) {
            return res.data.id
          } else {
             throw new Error(res.data.error || 'Unknown error')
          }
        } catch (err) {
          console.error(err)
          Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo iniciar el pago. Verifica tus credenciales de PayPal.',
            background: 'var(--surface)',
            color: 'var(--text)'
          })
          throw err
        }
      },
      onApprove: async (data, actions) => {
        try {
          Swal.fire({
            title: 'Procesando pago...',
            text: 'Por favor espera un momento.',
            allowOutsideClick: false,
            background: 'var(--surface)',
            color: 'var(--text)',
            didOpen: () => {
              Swal.showLoading()
            }
          })

          const res = await api.post('/paypal/capture-order', {
            orderID: data.orderID,
            plan: checkoutPlan.value,
            billing_cycle: billingCycle.value
          })

          if (res.data.success) {
              Swal.fire({
                icon: 'success',
                title: '<span style="font-size: 2rem; font-weight: 800; background: -webkit-linear-gradient(45deg, #4fd3a8, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">¡Pago Exitoso!</span>',
                html: `
                  <p style="color: var(--text-muted); font-size: 1.05rem; margin-bottom: 1.5rem;">
                    Tu membresía se actualizó al instante. ¡Bienvenido al siguiente nivel!
                  </p>
                  <div style="padding: 1rem; background: rgba(79, 211, 168, 0.1); border: 1px solid rgba(79, 211, 168, 0.3); border-radius: 12px; display: inline-block;">
                    <h3 style="color: var(--text); font-weight: bold; font-size: 1.1rem; margin: 0;">
                      <i class="fas fa-crown" style="color: #a855f7; margin-right: 8px;"></i>Suscripción Activa
                    </h3>
                  </div>
                `,
                background: 'var(--surface-2)',
                color: 'var(--text)',
                confirmButtonText: 'Empezar a disfrutar',
                buttonsStyling: false,
                customClass: {
                  popup: 'swal-custom-popup',
                  confirmButton: 'btn-accent',
                },
                backdrop: `rgba(15, 23, 42, 0.85)`
              }).then(async () => {
                showCheckoutModal.value = false
                const userRes = await api.get('/user')
                user.value = userRes.data
                localStorage.setItem('user', JSON.stringify(userRes.data))
                window.dispatchEvent(new CustomEvent('user-updated'))
                refreshKey.value++
              })
            
          } else {
             Swal.fire({
              icon: 'error',
              title: 'Pago fallido',
              text: 'No se pudo completar la transacción.',
              background: 'var(--surface)',
              color: 'var(--text)'
            })
          }
        } catch (err) {
          console.error(err)
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error al procesar el pago.',
            background: 'var(--surface)',
            color: 'var(--text)'
          })
        }
      }
    }).render('#paypal-button-container')
  } catch (error) {
    console.error('Error setup PayPal', error)
    const container = document.getElementById('paypal-button-container')
    if (container) {
      container.innerHTML = `
        <div style="text-align: center; color: #ef4444; padding: 1rem; border: 1px dashed #ef4444; border-radius: 8px;">
          <p style="font-weight: bold; margin-bottom: 0.5rem;">⚠️ No se pudo cargar PayPal</p>
          <p style="font-size: 0.85rem;">Es probable que aún no hayas colocado tus credenciales en el archivo <code>backend/.env</code> o que sean inválidas.</p>
        </div>
      `
    }
  }
}


</script>

<template>
  <div class="app">
    <div v-if="isSidebarOpen" class="sidebar-overlay" @click="isSidebarOpen = false" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; display: block; backdrop-filter: blur(2px);"></div>
    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar" id="sidebar" :class="{ 'is-open': isSidebarOpen }">
      <div class="brand" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path d="M4 18 L9 10 L13 14 L20 4" stroke="currentColor" stroke-width="2.4" fill="none"
                stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span class="brand-name">Prosper</span>
        </div>
        <button @click="showMembershipModal = true"
          style="font-size: 0.65rem; text-transform: uppercase; font-weight: bold; padding: 4px 12px; border-radius: 12px; letter-spacing: 1px; cursor: pointer; transition: transform 0.2s, filter 0.2s; margin-top: 2px;"
          :style="userPlan === 'ultra' ? 'background: linear-gradient(45deg, #a855f7, #ec4899); color: white; border: none; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.4);' : 'background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);'"
          onmouseover="this.style.transform='scale(1.05)'; this.style.filter='brightness(1.1)'" onmouseout="this.style.transform='scale(1)'; this.style.filter='brightness(1)'">
          Plan {{ userPlan }}
        </button>
      </div>
      <nav class="nav" aria-label="Navegación principal" @click="isSidebarOpen = false">
        <RouterLink class="nav-item" to="/" exact-active-class="is-active">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M4 12 L12 4 L20 12 M6 10 V20 H18 V10" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Dashboard</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/movimientos" active-class="is-active">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M12 2v20M17 7l-5-5-5 5M7 17l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Movimientos</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/estadisticas" active-class="is-active" :class="{ 'is-disabled': userPlan === 'gratis' }" @click="handleNavClick($event, 'ultra')">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M5 20V10M12 20V4M19 20v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <span>Estadísticas</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/objetivos" active-class="is-active">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8" />
            <circle cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="1.8" />
            <circle cx="12" cy="12" r="1.5" fill="currentColor" />
          </svg>
          <span>Objetivos</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/gastos-fijos" active-class="is-active" :class="{ 'is-disabled': userPlan === 'gratis' }" @click="handleNavClick($event, 'ultra')">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.8" />
            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="1.8" />
            <line x1="8" y1="15" x2="16" y2="15" stroke="currentColor" stroke-width="1.8" />
          </svg>
          <span>Gastos Fijos</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/tarjetas-credito" active-class="is-active" :class="{ 'is-disabled': userPlan === 'gratis' }" @click="handleNavClick($event, 'ultra')">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <rect x="2" y="5" width="20" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="1.8" />
            <path d="M2 10h20" stroke="currentColor" stroke-width="1.8" />
            <circle cx="7" cy="15" r="1.5" fill="currentColor" />
            <circle cx="12" cy="15" r="1.5" fill="currentColor" opacity=".5" />
          </svg>
          <span>Tarjetas de credito</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/notificaciones" active-class="is-active" :class="{ 'is-disabled': userPlan === 'gratis' }" @click="handleNavClick($event, 'ultra')">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>Notificaciones</span>
          <span v-if="unreadNotifs > 0 && userPlan !== 'gratis'" class="badge" style="margin-left: auto; background: var(--red); color: white;">{{ unreadNotifs > 9 ? '9+' : unreadNotifs }}</span>
        </RouterLink>
      </nav>
      <div class="sidebar-foot" style="display:flex; justify-content:center; gap:12px; margin-bottom: 20px;">
        <button class="theme-circle" :class="{ 'is-active': currentTheme === 'dark' }" @click="setTheme('dark')" style="background: linear-gradient(to right, #0c1114 50%, #4fd3a8 50%);" aria-label="Verde Oscuro"></button>
        <button class="theme-circle" :class="{ 'is-active': currentTheme === 'light' }" @click="setTheme('light')" style="background: linear-gradient(to right, #ffffff 50%, #000000 50%);" aria-label="Blanco y Negro"></button>
        <button class="theme-circle" :class="{ 'is-active': currentTheme === 'blue' }" @click="setTheme('blue')" style="background: linear-gradient(to right, #141b2d 50%, #3b82f6 50%);" aria-label="Azul Oscuro"></button>
      </div>
    </aside>

    <!-- ============ MAIN ============ -->
    <main class="main" @click="isSidebarOpen = false">
      <header class="topbar" @click.stop>
        <div class="topbar-left">
          <button class="hamburger" id="menuToggle" aria-label="Menú" @click.stop="isSidebarOpen = !isSidebarOpen">
            <svg viewBox="0 0 24 24" width="22" height="22">
              <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
          <div class="topbar-title">
            <h1>{{ getPageInfo().title }}</h1>
            <p>{{ getPageInfo().subtitle }}</p>
          </div>
        </div>
        
        <div class="topbar-actions">
          <div class="topbar-balance" id="topbarBalance" style="display: flex; align-items: center; gap: 8px;">
            <div style="display: flex; flex-direction: column; align-items: flex-end;">
              <span class="balance-label" style="display: flex; align-items: center; gap: 6px; cursor: pointer; transition: opacity 0.2s;" @click="isMonthlyBalance = !isMonthlyBalance" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1" title="Clic para cambiar vista de balance">
                {{ isMonthlyBalance ? 'Balance mensual' : 'Balance total' }}
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M7 16V4M7 4L3 8M7 4L11 8M17 8V20M17 20L21 16M17 20L13 16" />
                </svg>
              </span>
              <strong class="balance-value" id="headerBalance">{{ formatCurrency(isMonthlyBalance ? headerBalanceMensual : headerBalance) }}</strong>
            </div>
          </div>
          <button class="btn-accent" id="btnNewTx" @click="openTxModal()">
            <svg viewBox="0 0 24 24" width="16" height="16">
              <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>Nuevo</span>
          </button>
          <div class="avatar-dropdown">
            <div class="avatar">{{ userInicial }}</div>
            <div class="dropdown-menu">
              <div class="dropdown-header">
                <div class="dh-avatar">{{ userInicial }}</div>
                <div class="dh-info">
                  <span class="dh-name">{{ user.name }}</span>
                  <span class="dh-email">{{ user.email }}</span>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <RouterLink to="/perfil" class="dropdown-item">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                Mi Perfil
              </RouterLink>
              <a href="#" @click.prevent="showMembershipModal = true" class="dropdown-item" style="color: var(--accent);">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
                Membresía
              </a>
              <a v-if="user.role_id === 1" href="https://prosper-dashboard.vercel.app/" class="dropdown-item" style="color: #f59e0b;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Panel Administrativo
              </a>
              <div class="dropdown-divider"></div>
              <a href="#" @click.prevent="handleLogout" class="dropdown-item text-red">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><polyline points="16 17 21 12 16 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                Cerrar Sesión
              </a>
            </div>
          </div>
        </div>
      </header>

      <!-- Aquí se renderiza cada vista hija -->
      <RouterView :key="refreshKey" />
    </main>

    <!-- ============ MODAL: MOVIMIENTOS ============ -->
    <div class="modal" :class="{ 'is-active': showTxModal }">
      <div class="modal-content">
        <div class="modal-head premium-head">
          <div class="head-icon">💸</div>
          <div class="head-text">
            <h2>{{ txEditId ? 'Editar Movimiento' : 'Nuevo Movimiento' }}</h2>
            <p>{{ txEditId ? 'Modifica los datos del registro' : 'Añade un ingreso, gasto o ahorro' }}</p>
          </div>
          <button class="modal-close" @click="showTxModal = false" aria-label="Cerrar">
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>
        </div>
        <form @submit.prevent="handleSaveTx" autocomplete="off">
          <div class="form-group">
            <label>Tipo de movimiento</label>
            <div class="type-selector">
              <button type="button" class="type-btn" :class="{ 'is-active': txData.tipo === 'ingreso' }" @click="txData.tipo = 'ingreso'; txData.categoria = 'Salario'">
                <span class="type-dot dot-green"></span>Ingreso
              </button>
              <button type="button" class="type-btn" :class="{ 'is-active': txData.tipo === 'gasto' }" @click="txData.tipo = 'gasto'; txData.categoria = 'Comida'">
                <span class="type-dot dot-red"></span>Gasto
              </button>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group form-half">
              <label>Monto</label>
              <input type="text" class="form-control" v-model="displayMonto" @input="onMontoInput" placeholder="0.00" required>
            </div>
            <div class="form-group form-half">
              <label>Fecha</label>
              <input type="date" class="form-control" v-model="txData.fecha" required>
            </div>
          </div>
          <div class="form-group">
            <label>Categoría</label>
            <select class="form-control" v-model="txData.categoria" required>
              <optgroup label="Ingreso" v-if="txData.tipo === 'ingreso'">
                <option value="Salario">💼 Salario</option>
                <option value="Inversiones">📈 Inversiones</option>
                <option value="Ventas">🛒 Ventas</option>
                <option value="Regalos">🎁 Regalos</option>
                <option value="Otros">📎 Otros</option>
              </optgroup>
              <optgroup label="Gasto" v-if="txData.tipo === 'gasto'">
                <option value="Comida">🍔 Comida</option>
                <option value="Transporte">🚗 Transporte</option>
                <option value="Vivienda">🏠 Vivienda</option>
                <option value="Servicios">⚡ Servicios</option>
                <option value="Salud">🏥 Salud</option>
                <option value="Ocio">🎮 Ocio</option>
                <option value="Educación">📚 Educación</option>
                <option value="Ropa">👕 Ropa</option>
                <option value="Otros">📎 Otros</option>
              </optgroup>
            </select>
          </div>
          <div class="form-group">
            <label>Descripción</label>
            <input type="text" class="form-control" v-model="txData.descripcion" placeholder="Ej: Compras del supermercado">
          </div>
          <div class="form-group">
            <label>Método de pago</label>
            <select class="form-control" v-model="txData.metodo_pago">
              <option value="efectivo">💵 Efectivo</option>
              <option value="tarjeta">💳 Tarjeta </option>
              <option value="transferencia">🏦 Transferencia</option>
              <option value="otro">📎 Otro</option>
            </select>
            <input type="text" class="form-control" v-model="txData.metodo_pago_otro" v-if="txData.metodo_pago === 'otro'" style="margin-top:8px;" placeholder="Especificar método de pago...">
          </div>
          <div class="form-actions" style="margin-top: 24px;">
            <button type="button" class="btn-ghost" @click="showTxModal = false">Cancelar</button>
            <button type="submit" class="btn-accent" :disabled="isSubmitting">Guardar Movimiento</button>
          </div>
        </form>
      </div>
    </div>
    <!-- ============ MODAL: MEMBRESÍAS ============ -->
    <div class="modal" :class="{ 'is-active': showMembershipModal }">
      <div class="modal-content" style="max-width: 900px; padding: 0; position: relative; max-height: 95vh; overflow: hidden;">
        
        <!-- Header -->
        <div style="padding: 1.5rem 1.5rem 0.5rem 1.5rem; text-align: center; position: relative;">
          <button @click="showMembershipModal = false" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.05); border: none; border-radius: 50%; width: 32px; height: 32px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" /></svg>
          </button>
          
          <div style="display: inline-block; margin-bottom: 0.5rem;">
            <h2 style="font-size: 1.8rem; margin-bottom: 0.25rem; background: -webkit-linear-gradient(45deg, var(--accent), #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Sube de nivel</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">Elige tu membresía.</p>
          </div>

          <!-- Billing Cycle Toggle -->
          <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 1rem;">
            <span :style="{ color: billingCycle === 'monthly' ? 'var(--text)' : 'var(--text-muted)', fontWeight: billingCycle === 'monthly' ? 'bold' : 'normal', transition: 'color 0.3s' }">Mensual</span>
            <label style="position: relative; display: inline-block; width: 50px; height: 26px;">
              <input type="checkbox" :checked="billingCycle === 'annual'" @change="billingCycle = $event.target.checked ? 'annual' : 'monthly'" style="opacity: 0; width: 0; height: 0;">
              <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--surface-2); border: 1px solid var(--border); transition: .4s; border-radius: 34px;">
                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: var(--accent); transition: .4s; border-radius: 50%;" :style="billingCycle === 'annual' ? 'transform: translateX(24px)' : ''"></span>
              </span>
            </label>
            <span :style="{ color: billingCycle === 'annual' ? 'var(--text)' : 'var(--text-muted)', fontWeight: billingCycle === 'annual' ? 'bold' : 'normal', display: 'flex', alignItems: 'center', gap: '6px', transition: 'color 0.3s' }">
              Anual 
              <span style="background: rgba(79, 211, 168, 0.2); color: var(--accent); border: 1px solid var(--accent); font-size: 0.65rem; font-weight: bold; padding: 2px 6px; border-radius: 10px;">2 meses gratis</span>
            </span>
          </div>
        </div>

        <!-- Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; padding: 2.5rem 1.5rem 1.5rem 1.5rem;">
          
          <!-- Plan Gratis -->
          <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column;">
            <h3 style="font-size: 1.2rem; margin-bottom: 1rem;">Gratis</h3>
            <div style="margin-bottom: 1.5rem;">
              <p style="font-size: 1.8rem; font-weight: bold;">$0<span style="font-size: 0.9rem; color: var(--text-muted); font-weight: normal;">/{{ billingCycle === 'annual' ? 'año' : 'mes' }}</span></p>
            </div>
            <ul style="list-style: none; padding: 0; margin: 0 0 1rem 0; flex-grow: 1; color: var(--text-muted); font-size: 0.85rem;">
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--accent); flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Dashboard:</b> Resumen básico.</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--accent); flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Movimientos:</b> Máximo 15 movimientos.</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--accent); flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Objetivos:</b> Máximo 1 objetivo.</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px; opacity: 0.5;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--text-muted); flex-shrink: 0; margin-top: 2px;"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span style="text-decoration: line-through;">Notificaciones</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px; opacity: 0.5;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--text-muted); flex-shrink: 0; margin-top: 2px;"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span style="text-decoration: line-through;">Estadísticas detalladas</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px; opacity: 0.5;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--text-muted); flex-shrink: 0; margin-top: 2px;"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span style="text-decoration: line-through;">Gastos Fijos</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px; opacity: 0.5;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: var(--text-muted); flex-shrink: 0; margin-top: 2px;"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span style="text-decoration: line-through;">Tarjetas de Crédito</span>
              </li>
            </ul>
            <div v-if="userPlan === 'gratis'" style="text-align: center;">
              <button class="btn-ghost" style="width: 100%; border: 1px solid var(--border); padding: 0.6rem;" disabled>Plan Actual</button>
            </div>
          </div>



          <!-- Plan Ultra -->
          <div class="membership-card" style="background: linear-gradient(145deg, rgba(30, 41, 59, 0.8) 0%, rgba(168, 85, 247, 0.25) 100%); border: 2px solid #a855f7; border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; transform: scale(1.05); box-shadow: 0 15px 35px rgba(168, 85, 247, 0.25); z-index: 1; position: relative; transition: transform 0.3s ease, box-shadow 0.3s ease;" onmouseover="this.style.transform='scale(1.08)'; this.style.boxShadow='0 20px 40px rgba(168, 85, 247, 0.35)'" onmouseout="this.style.transform='scale(1.05)'; this.style.boxShadow='0 15px 35px rgba(168, 85, 247, 0.25)'">
            <div style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: linear-gradient(45deg, #a855f7, #ec4899); color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; z-index: 3; box-shadow: 0 4px 10px rgba(168, 85, 247, 0.4);">Recomendado</div>
            <div style="position: absolute; inset: 0; overflow: hidden; border-radius: 14px; pointer-events: none; z-index: 0;">
              <div style="position: absolute; top: -50px; right: -50px; width: 100px; height: 100px; background: #ec4899; filter: blur(50px); opacity: 0.5; border-radius: 50%;"></div>
            </div>
            <div style="margin-bottom: 1rem; position: relative; z-index: 2;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.25rem;">
                <h3 style="font-size: 1.25rem; margin: 0; background: -webkit-linear-gradient(45deg, #a855f7, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 20px rgba(168, 85, 247, 0.2);">Ultra</h3>
                <span v-if="userPlan === 'ultra'" style="font-size: 0.75rem; color: #a855f7; background: rgba(168,85,247,0.1); border: 1px solid rgba(168,85,247,0.3); border-radius: 12px; padding: 0.2rem 0.6rem; font-weight: bold;">
                  Válido hasta: {{ membershipEndsAt }}
                </span>
              </div>
              <p style="font-size: 1.8rem; font-weight: bold; color: var(--text); transition: color 0.3s; margin-top: 0.5rem;">
                {{ billingCycle === 'annual' ? '$249.000' : '$24.900' }}
                <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: normal;">/{{ billingCycle === 'annual' ? 'año' : 'mes' }}</span>
              </p>
              <p v-if="billingCycle === 'annual'" style="color: #ec4899; font-size: 0.8rem; margin-top: -5px; font-weight: bold;">Ahorras $49.800</p>
            </div>
            <ul style="list-style: none; padding: 0; margin: 0 0 1rem 0; flex-grow: 1; color: var(--text-muted); font-size: 0.85rem;">
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: #a855f7; flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Movimientos:</b> Ilimitados.</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: #a855f7; flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Objetivos:</b> Ilimitados.</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: #a855f7; flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Tarjetas de Crédito:</b> Ilimitadas.</span>
              </li>
              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: #a855f7; flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span><b>Gastos Fijos:</b> Ilimitados.</span>
              </li>

              <li style="margin-bottom: 0.5rem; display: flex; align-items: start; gap: 6px;">
                <svg viewBox="0 0 24 24" width="14" height="14" style="color: #a855f7; flex-shrink: 0; margin-top: 2px;"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span style="color: var(--text);"><b>Acceso a futuras funciones en beta</b></span>
              </li>
            </ul>
            <div v-if="userPlan === 'ultra'" style="text-align: center;">
              <button v-if="userBillingCycle === 'monthly' && billingCycle === 'annual'" @click="openCheckout('ultra')" style="width: 100%; font-weight: bold; background: linear-gradient(45deg, #a855f7, #ec4899); color: white; border: none; padding: 0.6rem; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.4); transition: filter 0.3s ease, transform 0.2s ease; position: relative; z-index: 2;" onmouseover="this.style.filter='brightness(1.1)'; this.style.transform='scale(1.02)'" onmouseout="this.style.filter='brightness(1)'; this.style.transform='scale(1)'">
                Mejorar a Anual
              </button>
              <button v-else-if="userBillingCycle === 'annual' && billingCycle === 'monthly'" @click="openCheckout('ultra')" class="btn-ghost" style="width: 100%; border: 1px solid var(--border); padding: 0.6rem; color: var(--text); font-weight: 500; transition: all 0.2s ease; position: relative; z-index: 2; border-radius: 8px;" onmouseover="this.style.borderColor='#a855f7'; this.style.color='#a855f7'" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text)'">
                Cambiar a Mensual
              </button>
              <button v-else class="btn-ghost" style="width: 100%; border: 1px solid var(--border); padding: 0.6rem; position: relative; z-index: 2;" disabled>Plan Actual</button>
            </div>
            <button v-else @click="openCheckout('ultra')" style="width: 100%; font-weight: bold; background: linear-gradient(45deg, #a855f7, #ec4899); color: white; border: none; padding: 0.6rem; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.4); transition: filter 0.3s ease, transform 0.2s ease; position: relative; z-index: 2;" onmouseover="this.style.filter='brightness(1.1)'; this.style.transform='scale(1.02)'" onmouseout="this.style.filter='brightness(1)'; this.style.transform='scale(1)'">
              Elegir Ultra
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ============ MODAL: CHECKOUT PAYPAL ============ -->
    <div class="modal" :class="{ 'is-active': showCheckoutModal }">
      <div class="modal-content" style="max-width: 500px; padding: 2rem; position: relative;">
        <div class="modal-head" style="margin-bottom: 1.5rem; border-bottom: none; display: flex; flex-direction: column; align-items: center;">
          <h2 style="font-size: 1.6rem; color: var(--text); margin-bottom: 0.5rem;">Confirmar Pago</h2>
          <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center;">Estás a un paso de mejorar tu experiencia en Prosper.</p>
          <button class="modal-close" @click="showCheckoutModal = false" aria-label="Cerrar" style="position: absolute; top: 1.5rem; right: 1.5rem;">
            <svg viewBox="0 0 24 24" width="24" height="24">
              <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>
        </div>
        
        <div style="background: var(--surface-2); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="color: var(--text-muted); font-weight: bold;">Plan Seleccionado:</span>
            <span style="color: var(--text); font-weight: bold; text-transform: uppercase;">{{ checkoutPlan }} ({{ billingCycle === 'annual' ? 'Anual' : 'Mensual' }})</span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="color: var(--text-muted); font-weight: bold;">Total a pagar:</span>
            <span style="color: var(--accent); font-size: 1.4rem; font-weight: bold;">{{ checkoutPrice }}</span>
          </div>
        </div>

        <!-- Botones de PayPal se inyectarán aquí -->
        <div id="paypal-button-container" style="min-height: 150px; display: flex; justify-content: center; align-items: center;">
          <span style="color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Cargando pasarela de pago...</span>
        </div>

        <button @click="showCheckoutModal = false; showMembershipModal = true" class="btn-ghost" style="width: 100%; margin-top: 1rem; padding: 0.6rem; border: 1px solid var(--border);">Volver a membresías</button>
      </div>
    </div>

  </div>
</template>
