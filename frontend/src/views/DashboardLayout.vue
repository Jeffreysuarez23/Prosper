<script setup>
import { ref, onMounted, provide } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../services/api'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))
const refreshKey = ref(0) // Usado para recargar la vista hija sin recargar la página

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
}

const headerBalance = ref(0)
const unreadNotifs = ref(0)
const formatCurrency = (value) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(value)
}

const fetchHeaderBalance = async () => {
  try {
    const res = await api.get('/dashboard')
    headerBalance.value = res.data.balance_global || 0
    unreadNotifs.value = res.data.unread_notifications || 0
  } catch (error) {
    console.error('Error fetching global balance', error)
  }
}
provide('refreshHeaderBalance', fetchHeaderBalance)
provide('headerBalance', headerBalance)

onMounted(() => {
  fetchHeaderBalance()
})

</script>

<template>
  <div class="app">
    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M4 18 L9 10 L13 14 L20 4" stroke="currentColor" stroke-width="2.4" fill="none"
              stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span class="brand-name">Prosper</span>
      </div>
      <nav class="nav" aria-label="Navegación principal">
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
        <RouterLink class="nav-item" to="/estadisticas" active-class="is-active">
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
        <RouterLink class="nav-item" to="/gastos-fijos" active-class="is-active">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.8" />
            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="1.8" />
            <line x1="8" y1="15" x2="16" y2="15" stroke="currentColor" stroke-width="1.8" />
          </svg>
          <span>Gastos Fijos</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/tarjetas-credito" active-class="is-active">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <rect x="2" y="5" width="20" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="1.8" />
            <path d="M2 10h20" stroke="currentColor" stroke-width="1.8" />
            <circle cx="7" cy="15" r="1.5" fill="currentColor" />
            <circle cx="12" cy="15" r="1.5" fill="currentColor" opacity=".5" />
          </svg>
          <span>Tarjetas de credito</span>
        </RouterLink>
        <RouterLink class="nav-item" to="/notificaciones" active-class="is-active">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span>Notificaciones</span>
          <span v-if="unreadNotifs > 0" class="badge" style="margin-left: auto; background: var(--red); color: white;">{{ unreadNotifs > 9 ? '9+' : unreadNotifs }}</span>
        </RouterLink>
      </nav>
      <div class="sidebar-foot" style="display:flex; justify-content:center; gap:12px; margin-bottom: 20px;">
        <button class="theme-circle" :class="{ 'is-active': currentTheme === 'dark' }" @click="setTheme('dark')" style="background: linear-gradient(to right, #0c1114 50%, #4fd3a8 50%);" aria-label="Verde Oscuro"></button>
        <button class="theme-circle" :class="{ 'is-active': currentTheme === 'light' }" @click="setTheme('light')" style="background: linear-gradient(to right, #ffffff 50%, #000000 50%);" aria-label="Blanco y Negro"></button>
        <button class="theme-circle" :class="{ 'is-active': currentTheme === 'blue' }" @click="setTheme('blue')" style="background: linear-gradient(to right, #141b2d 50%, #3b82f6 50%);" aria-label="Azul Oscuro"></button>
      </div>
    </aside>

    <!-- ============ MAIN ============ -->
    <main class="main">
      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" id="menuToggle" aria-label="Menú">
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
          <div class="topbar-balance" id="topbarBalance">
            <span class="balance-label">Balance total</span>
            <strong class="balance-value" id="headerBalance">{{ formatCurrency(headerBalance) }}</strong>
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
                <option value="Freelance">💻 Freelance</option>
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
            <button type="submit" class="btn-accent">Guardar Movimiento</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
