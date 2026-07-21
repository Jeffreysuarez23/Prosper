<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import api from '../services/api'
import Swal from 'sweetalert2'

const refreshHeaderBalance = inject('refreshHeaderBalance')
const headerBalance = inject('headerBalance')

const data = ref([])
const isSubmitting = ref(false)
const loading = ref(true)
const searchQuery = ref('')
const statusFilter = ref('')

const fetchData = async () => {
  loading.value = true
  try {
    const params = { q: searchQuery.value }
    const res = await api.get('/tarjetas-credito', { params })
    data.value = res.data
  } catch (error) {
    console.error("Error cargando tarjetas", error)
  } finally {
    loading.value = false
  }
}

onMounted(() => { fetchData() })

// ── Status helpers ──
const getCalculatedDebtInfo = (t) => {
  let baseDeuda = parseFloat(t.deuda_actual || 0)
  let penalty = 0
  let totalDeuda = baseDeuda
  
  const todayDay = new Date().getDate()
  const diffPago = parseInt(t.dia_pago) - todayDay
  
  if (baseDeuda > 0 && diffPago < 0 && t.tasa_interes > 0) {
    const tasaMensual = parseFloat(t.tasa_interes) / 12 / 100
    const d = new Date()
    const currentMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const hasInterestApplied = t.fecha_ultimo_interes && t.fecha_ultimo_interes.startsWith(currentMonth)
    
    if (hasInterestApplied) {
      penalty = baseDeuda - (baseDeuda / (1 + tasaMensual))
      totalDeuda = baseDeuda // already includes penalty in DB
    } else {
      penalty = baseDeuda * tasaMensual
      totalDeuda = baseDeuda + penalty
    }
  }
  
  return { baseDeuda, totalDeuda, penalty }
}

const getStatusInfo = (t) => {
  const { totalDeuda } = getCalculatedDebtInfo(t)
  const todayDay = new Date().getDate()
  const diffCorte = parseInt(t.dia_corte) - todayDay
  const diffPago = parseInt(t.dia_pago) - todayDay
  const limite = parseFloat(t.limite_credito || 1)
  
  const utilPct = Math.min(100, Math.round((totalDeuda / limite) * 100))
  const cupoDisponible = Math.max(0, limite - totalDeuda)

  let statusClass = 'status-ok'
  let statusText = '✅ Al día'
  let statusCode = 'ok'

  if (totalDeuda <= 0) {
    statusClass = 'status-ok'
    statusText = '✅ Sin deuda'
    statusCode = 'ok'
  } else if (diffPago < 0) {
    statusClass = 'status-urgent'
    statusText = '⚠️ Pago atrasado'
    statusCode = 'urgent'
  } else if (diffPago <= 3) {
    statusClass = 'status-warning'
    statusText = diffPago === 0 ? '⏰ Pago hoy' : diffPago === 1 ? '⏰ Pago mañana' : '⏰ Pago pronto'
    statusCode = 'warning'
  } else if (diffCorte <= 3 && diffCorte >= 0) {
    statusClass = 'status-info'
    statusText = diffCorte === 0 ? '📋 Corte hoy' : '📋 Corte pronto'
    statusCode = 'info'
  } else if (utilPct >= 80) {
    statusClass = 'status-warning'
    statusText = '⚠️ Uso alto'
    statusCode = 'warning'
  }

  return { utilPct, cupoDisponible, statusClass, statusText, statusCode, deuda: totalDeuda }
}

const filteredData = computed(() => {
  return data.value.filter(item => {
    if (!statusFilter.value) return true
    return getStatusInfo(item).statusCode === statusFilter.value
  })
})

// ── Computed stats ──
const totalTarjetas = computed(() => data.value.length)
const totalDeuda = computed(() => data.value.reduce((s, t) => s + getCalculatedDebtInfo(t).totalDeuda, 0))
const totalCupoDisponible = computed(() => data.value.reduce((s, t) => s + Math.max(0, parseFloat(t.limite_credito || 0) - getCalculatedDebtInfo(t).totalDeuda), 0))

const formatCurrency = (val) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 }).format(val || 0)

// ── Card Modal ──
const showCardModal = ref(false)
const cardEditId = ref(null)
const cardData = ref({
  nombre: '', banco: '', ultimos_digitos: '', limite_credito: '',
  deuda_actual: '', dia_corte: 1, dia_pago: 15,
  tasa_interes: '', icono: '💳', color: '#3b82f6'
})
const displayLimite = ref('')
const displayDeuda = ref('')
const displayTasa = ref('')

const cardColors = [
  '#3b82f6', '#8b5cf6', '#ef4444', '#f59e0b',
  '#10b981', '#ec4899', '#06b6d4', '#1e293b'
]

const emojis = ['💳', '🏦', '💰', '🛒', '✈️', '⛽', '🎓', '🏥', '🛡️', '🎯', '⭐', '🔥']

const formatLimiteInput = (e) => {
  let raw = e.target.value.replace(/\D/g, '')
  if (!raw) { displayLimite.value = ''; cardData.value.limite_credito = ''; return }
  displayLimite.value = new Intl.NumberFormat('es-CO').format(parseInt(raw))
  cardData.value.limite_credito = raw
  e.target.value = displayLimite.value
}

const formatDeudaInput = (e) => {
  let raw = e.target.value.replace(/\D/g, '')
  if (!raw) { displayDeuda.value = ''; cardData.value.deuda_actual = ''; return }
  let num = parseInt(raw)
  const limite = parseInt(cardData.value.limite_credito) || 0
  if (limite > 0 && num > limite) {
    num = limite
    Swal.fire({ toast: true, position: 'bottom-end', icon: 'warning', title: 'La deuda supera el límite', text: 'Se ha ajustado automáticamente al límite de crédito.', showConfirmButton: false, timer: 2500, background: 'var(--surface)', color: 'var(--text)', customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title' } })
  }
  displayDeuda.value = new Intl.NumberFormat('es-CO').format(num)
  cardData.value.deuda_actual = num
  e.target.value = displayDeuda.value
}

const formatDiaCorte = (e) => {
  let val = parseInt(e.target.value)
  if (isNaN(val)) {
    cardData.value.dia_corte = ''
    e.target.value = ''
    return
  }
  if (val > 31) val = 31
  if (val < 1) val = 1
  cardData.value.dia_corte = val
  e.target.value = val
}

const formatDiaPago = (e) => {
  let val = parseInt(e.target.value)
  if (isNaN(val)) {
    cardData.value.dia_pago = ''
    e.target.value = ''
    return
  }
  if (val > 31) val = 31
  if (val < 1) val = 1
  cardData.value.dia_pago = val
  e.target.value = val
}

const formatTasaInput = (e) => {
  let raw = e.target.value.replace(/[^0-9.]/g, '')
  const parts = raw.split('.')
  if (parts.length > 2) {
    raw = parts[0] + '.' + parts.slice(1).join('')
  }
  if (!raw) {
    displayTasa.value = ''
    cardData.value.tasa_interes = ''
    return
  }
  let num = parseFloat(raw)
  if (num > 100) {
    num = 100
    raw = '100'
  }
  displayTasa.value = raw
  cardData.value.tasa_interes = num
  e.target.value = raw
}

const formatTasaBlur = () => {
  if (displayTasa.value && !displayTasa.value.includes('%')) {
    displayTasa.value = displayTasa.value + '%'
  }
}

const openNewCard = () => {
  cardEditId.value = null
  cardData.value = { nombre: '', banco: '', ultimos_digitos: '', limite_credito: '', deuda_actual: '', dia_corte: 1, dia_pago: 15, tasa_interes: '', icono: '💳', color: '#3b82f6' }
  displayLimite.value = ''
  displayDeuda.value = ''
  displayTasa.value = ''
  showCardModal.value = true
}

const openEditCard = (t) => {
  cardEditId.value = t.id
  cardData.value = {
    nombre: t.nombre, banco: t.banco || '', ultimos_digitos: t.ultimos_digitos || '',
    limite_credito: t.limite_credito, deuda_actual: t.deuda_actual,
    dia_corte: t.dia_corte, dia_pago: t.dia_pago,
    tasa_interes: t.tasa_interes || '', icono: t.icono || '💳', color: t.color || '#3b82f6'
  }
  displayLimite.value = new Intl.NumberFormat('es-CO').format(t.limite_credito)
  displayDeuda.value = new Intl.NumberFormat('es-CO').format(t.deuda_actual)
  displayTasa.value = t.tasa_interes ? `${t.tasa_interes}%` : ''
  showCardModal.value = true
}

const saveCard = async () => {
  if (!cardData.value.nombre || !cardData.value.limite_credito) return
  isSubmitting.value = true
  try {
    if (cardEditId.value) {
      await api.put(`/tarjetas-credito/${cardEditId.value}`, cardData.value)
    } else {
      await api.post('/tarjetas-credito', cardData.value)
    }
    showCardModal.value = false
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tarjeta guardada exitosamente', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) { 
    console.error(error)
    if (error.response && error.response.status === 403 && error.response.data.message) {
      // The global api.js interceptor will handle this and show the alert
    } else {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: error.response?.data?.message || 'Error al guardar la tarjeta',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
    }
  } finally {
    isSubmitting.value = false
  }
}

const deleteCard = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar Tarjeta?',
    text: "Esta acción es permanente y no se puede deshacer.",
    icon: 'warning',
    showCancelButton: true,
    background: 'var(--surface)',
    color: 'var(--text)',
    customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', actions: 'swal-custom-actions', confirmButton: 'swal-custom-confirm', cancelButton: 'swal-custom-cancel' },
    buttonsStyling: false,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  })
  if (result.isConfirmed) {
    try {
      await api.delete(`/tarjetas-credito/${id}`)
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tarjeta eliminada', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
      fetchData()
      if (refreshHeaderBalance) refreshHeaderBalance()
    } catch (error) { console.error(error) }
  }
}

// ── Pay Modal ──
const showPayModal = ref(false)
const payData = ref({ id: null, nombre: '', deuda_actual: 0, monto: '' })
const displayPayMonto = ref('')

const openPayModal = (t) => {
  const debtInfo = getCalculatedDebtInfo(t)
  payData.value = {
    id: t.id,
    nombre: t.nombre,
    deuda_actual: debtInfo.totalDeuda
  }
  displayPayMonto.value = ''
  showPayModal.value = true
}

const formatPayInput = (e) => {
  let raw = e.target.value.replace(/\D/g, '')
  if (!raw) { displayPayMonto.value = ''; payData.value.monto = ''; return }
  let num = parseInt(raw)
  const maxAllowed = Math.min(payData.value.deuda_actual, headerBalance.value)
  if (num > maxAllowed) {
    num = maxAllowed
    Swal.fire({ toast: true, position: 'bottom-end', icon: 'error', title: num === payData.value.deuda_actual ? 'Supera la deuda actual' : 'Saldo insuficiente', showConfirmButton: false, timer: 2500, background: 'var(--surface)', color: 'var(--text)', customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title' } })
  }
  displayPayMonto.value = new Intl.NumberFormat('es-CO').format(num)
  payData.value.monto = num
  e.target.value = displayPayMonto.value
}

const savePay = async () => {
  const monto = parseFloat(payData.value.monto) || 0
  if (monto <= 0) return
  if (monto > headerBalance.value) {
    Swal.fire({ icon: 'warning', title: 'Balance insuficiente', text: 'No tienes suficiente balance para este pago.', confirmButtonText: 'Entendido', confirmButtonColor: 'var(--accent)', customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' } })
    return
  }
  isSubmitting.value = true
  try {
    await api.post(`/tarjetas-credito/${payData.value.id}/pago`, { monto })
    showPayModal.value = false

    const newDeuda = payData.value.deuda_actual - monto
    if (newDeuda <= 0) {
      setTimeout(() => {
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '¡Deuda liquidada! 🎉', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
      }, 500)
    } else {
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pago registrado', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
    }
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Error al procesar', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
  } finally {
    isSubmitting.value = false
  }
}

// ── Debt Modal ──
const showDebtModal = ref(false)
const debtData = ref({ id: null, nombre: '', deuda_actual: 0, limite_credito: 0, monto: '' })
const displayDebtMonto = ref('')

const openDebtModal = (t) => {
  debtData.value = { id: t.id, nombre: t.nombre, deuda_actual: parseFloat(t.deuda_actual), limite_credito: parseFloat(t.limite_credito) }
  displayDebtMonto.value = ''
  showDebtModal.value = true
}

const formatDebtInput = (e) => {
  let raw = e.target.value.replace(/\D/g, '')
  if (!raw) { displayDebtMonto.value = ''; debtData.value.monto = ''; return }
  let num = parseInt(raw)
  const maxAllowed = debtData.value.limite_credito - debtData.value.deuda_actual
  if (num > maxAllowed) {
    num = maxAllowed
    Swal.fire({ toast: true, position: 'bottom-end', icon: 'error', title: 'Supera el límite de la tarjeta', showConfirmButton: false, timer: 2500, background: 'var(--surface)', color: 'var(--text)', customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title' } })
  }
  displayDebtMonto.value = new Intl.NumberFormat('es-CO').format(num)
  debtData.value.monto = num
  e.target.value = displayDebtMonto.value
}

const saveDebt = async () => {
  const monto = parseFloat(debtData.value.monto) || 0
  if (monto <= 0) return
  const maxAllowed = debtData.value.limite_credito - debtData.value.deuda_actual
  if (monto > maxAllowed) {
    Swal.fire({ icon: 'warning', title: 'Límite excedido', text: 'El monto ingresado supera el límite de crédito disponible.', confirmButtonText: 'Entendido', confirmButtonColor: 'var(--accent)', customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' } })
    return
  }
  isSubmitting.value = true
  try {
    await api.post(`/tarjetas-credito/${debtData.value.id}/deuda`, { monto })
    showDebtModal.value = false
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deuda agregada exitosamente', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Error al procesar', showConfirmButton: false, timer: 3000, background: 'var(--surface)', color: 'var(--text)' })
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div v-if="loading" style="padding: 20px;">Cargando tarjetas de crédito...</div>

  <section v-else class="page-section">
    <!-- Summary Stats -->
    <div class="mini-stats">
      <div class="mini-stat ms-income">
        <span class="ms-label">Tarjetas</span>
        <strong class="ms-value" style="color: var(--accent);">{{ totalTarjetas }}</strong>
      </div>
      <div class="mini-stat ms-balance">
        <span class="ms-label">Deuda Total</span>
        <strong class="ms-value" style="color: var(--red);">{{ formatCurrency(totalDeuda).replace('COP', '').trim()
        }}</strong>
      </div>
      <div class="mini-stat ms-expense">
        <span class="ms-label">Cupo Disponible</span>
        <strong class="ms-value" style="color: var(--green);">{{ formatCurrency(totalCupoDisponible).replace('COP',
          '').trim() }}</strong>
      </div>
    </div>

    <!-- Search & Filters -->
    <div class="goals-header" style="flex-wrap: wrap; gap: 16px;">
      <form @submit.prevent="fetchData" style="display:flex; gap:12px; align-items:center; flex:1; min-width: 0;">
        <div class="search-wrap" style="position:relative; flex:1;">
          <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16"
            style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
            <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <input type="search" v-model="searchQuery" @input="fetchData" placeholder="Buscar tarjetas..."
            style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
        </div>
        <select v-model="statusFilter"
          style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface); color: white;">
          <option value="">Todos los estados</option>
          <option value="ok">Al día / Sin deuda</option>
          <option value="warning">Pago pronto / Uso alto</option>
          <option value="urgent">Pago atrasado</option>
        </select>
      </form>

      <button class="btn-accent" @click="openNewCard">
        <svg viewBox="0 0 24 24" width="16" height="16">
          <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <span>Nueva Tarjeta</span>
      </button>
    </div>

    <!-- Cards Grid -->
    <div v-if="filteredData.length > 0" class="goals-grid" id="creditCardsGrid">
      <div v-for="t in filteredData" :key="t.id" class="cc-card">
        <!-- Visual Credit Card -->
        <div class="cc-visual"
          :style="{ background: `linear-gradient(135deg, ${t.color || '#3b82f6'}, ${t.color || '#3b82f6'}dd, ${t.color || '#3b82f6'}88)` }">
          <div class="cc-visual-top">
            <span class="cc-chip">
              <svg viewBox="0 0 24 24" width="28" height="28" fill="none">
                <rect x="2" y="5" width="20" height="14" rx="3" stroke="rgba(255,255,255,.6)" stroke-width="1.5" />
                <path d="M2 10h20" stroke="rgba(255,255,255,.4)" stroke-width="1.5" />
                <rect x="5" y="13" width="6" height="3" rx="1" stroke="rgba(255,255,255,.4)" stroke-width="1" />
              </svg>
            </span>
            <span :class="['cc-status-pill', getStatusInfo(t).statusClass]">{{ getStatusInfo(t).statusText }}</span>
          </div>
          <div class="cc-digits">
            <span>••••</span> <span>••••</span> <span>••••</span> <span>{{ t.ultimos_digitos || '••••' }}</span>
          </div>
          <div class="cc-visual-bottom">
            <div>
              <span class="cc-label-small">Titular</span>
              <span class="cc-name">{{ t.nombre }}</span>
            </div>
            <div style="text-align:right;">
              <span class="cc-label-small">Banco</span>
              <span class="cc-name">{{ t.banco || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Card Info -->
        <div class="cc-info">
          <div class="cc-info-row">
            <div class="cc-info-item">
              <span class="cc-info-label">Deuda Total</span>
              <span class="cc-info-value" style="color: var(--red);">{{ formatCurrency(getCalculatedDebtInfo(t).totalDeuda).replace('COP', '').trim() }}</span>
            </div>
            
            <div v-if="getCalculatedDebtInfo(t).penalty > 0" class="cc-info-item" style="text-align:center;">
              <span class="cc-info-label" style="color: var(--amber-500);">⚠️ Interés incluido</span>
              <span class="cc-info-value-sm" style="color: var(--amber-500);">{{ formatCurrency(getCalculatedDebtInfo(t).penalty).replace('COP', '').trim() }}</span>
            </div>

            <div class="cc-info-item" style="text-align:right;">
              <span class="cc-info-label">Límite</span>
              <span class="cc-info-value">{{ formatCurrency(t.limite_credito).replace('COP', '').trim() }}</span>
            </div>
          </div>

          <!-- Utilization bar -->
          <div class="cc-util-bar">
            <div class="cc-util-fill"
              :style="{ width: getStatusInfo(t).utilPct + '%', background: getStatusInfo(t).utilPct > 80 ? 'var(--red)' : getStatusInfo(t).utilPct > 50 ? 'var(--amber-500)' : t.color || 'var(--accent)' }">
            </div>
          </div>
          <div
            style="display:flex; justify-content:space-between; font-size:.75rem; color:var(--text-muted); margin-top:4px;">
            <span>{{ getStatusInfo(t).utilPct }}% utilizado</span>
            <span>Disponible: {{ formatCurrency(getStatusInfo(t).cupoDisponible).replace('COP', '').trim() }}</span>
          </div>

          <div class="cc-info-row" style="margin-top:16px;">
            <div class="cc-info-item">
              <span class="cc-info-label">📋Día de corte</span>
              <span class="cc-info-value-sm">{{ t.dia_corte }}</span>
            </div>
            <div class="cc-info-item" style="text-align:center;">
              <span class="cc-info-label">💵 Día de pago</span>
              <span class="cc-info-value-sm">{{ t.dia_pago }}</span>
            </div>
            <div class="cc-info-item" style="text-align:right;">
              <span class="cc-info-label">📊 Tasa</span>
              <span class="cc-info-value-sm">{{ t.tasa_interes || 0 }}%</span>
            </div>
          </div>

          <div class="goal-actions">
            <button v-if="getStatusInfo(t).deuda > 0" class="goal-btn primary" @click="openPayModal(t)"
              style="background:var(--accent); color:white; border:none;">Pagar</button>
            <button v-else class="goal-btn"
              style="color:var(--green); border-color:var(--green); font-weight:600; pointer-events:none;">Sin
              deuda</button>
            <button v-if="getStatusInfo(t).cupoDisponible > 0" class="goal-btn primary" @click="openDebtModal(t)"
              style="background:var(--surface-2); color:var(--text); border:1px solid var(--border);">Comprar</button>
            <button class="goal-btn" @click="openEditCard(t)">Editar</button>
            <button class="goal-btn danger" @click="deleteCard(t.id)">Eliminar</button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="empty-state">
      <div class="empty-icon">💳</div>
      <p>No se encontraron tarjetas de crédito.</p>
      <small>Usa el botón "Nueva Tarjeta" para agregar una.</small>
    </div>
  </section>

  <!-- ═══ Modal Nueva/Editar Tarjeta ═══ -->
  <div v-if="showCardModal" class="modal" style="display: flex;">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon">💳</div>
        <div class="head-text">
          <h2>{{ cardEditId ? 'Editar Tarjeta' : 'Nueva Tarjeta de Crédito' }}</h2>
          <p>{{ cardEditId ? 'Actualiza los datos de tu tarjeta' : 'Registra una nueva tarjeta' }}</p>
        </div>
        <button class="modal-close" @click="showCardModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveCard" autocomplete="off">
        <div class="form-row">
          <div class="form-group form-half">
            <label>Nombre de la tarjeta</label>
            <input type="text" v-model="cardData.nombre" required placeholder="Ej: Visa Gold">
          </div>
          <div class="form-group form-half">
            <label>Banco emisor</label>
            <input type="text" v-model="cardData.banco" required placeholder="Ej: Bancolombia">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label>Límite de crédito</label>
            <input type="text" :value="displayLimite" @input="formatLimiteInput" required placeholder="0">
          </div>
          <div class="form-group form-half">
            <label>Deuda actual</label>
            <input type="text" :value="displayDeuda" @input="formatDeudaInput" placeholder="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-third">
            <label>Últimos 4 dígitos</label>
            <input type="text" v-model="cardData.ultimos_digitos" maxlength="4" required placeholder="1234">
          </div>
          <div class="form-group form-third">
            <label>Día de corte</label>
            <input type="number" :value="cardData.dia_corte" @input="formatDiaCorte" min="1" max="31" required>
          </div>
          <div class="form-group form-third">
            <label>Día de pago</label>
            <input type="number" :value="cardData.dia_pago" @input="formatDiaPago" min="1" max="31" required>
          </div>
        </div>
        <div class="form-group">
          <label>Tasa de interés anual (%)</label>
          <input type="text" :value="displayTasa" @input="formatTasaInput" @blur="formatTasaBlur" required placeholder="Ej: 28.5%">
        </div>
        <div class="form-group">
          <label>Color de la tarjeta</label>
          <div class="color-grid">
            <button type="button" v-for="c in cardColors" :key="c" class="color-btn"
              :class="{ 'is-active': cardData.color === c }" :style="{ background: c }"
              @click="cardData.color = c"></button>
          </div>
        </div>
        <div class="form-group">
          <label>Icono</label>
          <div class="emoji-grid">
            <button type="button" v-for="emoji in emojis" :key="emoji" class="emoji-btn"
              :class="{ 'is-active': cardData.icono === emoji }" @click="cardData.icono = emoji">{{ emoji }}</button>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" @click="showCardModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" :disabled="isSubmitting">Guardar Tarjeta</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Modal Pagar ═══ -->
  <div v-if="showPayModal" class="modal" style="display: flex;">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon" style="color:var(--accent);">💵</div>
        <div class="head-text" style="text-align:left;">
          <h2>Pagar Tarjeta</h2>
          <p>
            {{ payData.nombre }}
          </p>
        </div>
        <button class="modal-close" @click="showPayModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="savePay" autocomplete="off">
        <div class="form-group" style="text-align:left;">
          <label>Monto a pagar</label>
          <div style="position: relative;">
            <input type="text" class="form-control" v-model="displayPayMonto" @input="formatPayInput" required placeholder="0.00">
          </div>
          <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
            <span>Deuda Total (incluye intereses):</span>
            <span style="font-weight: 600; color: var(--text);">{{ formatCurrency(payData.deuda_actual).replace('COP', '').trim() }}</span>
          </div>
        </div>
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" @click="showPayModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" style="background:var(--accent);" :disabled="isSubmitting">Pagar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Modal Agregar Deuda ═══ -->
  <div v-if="showDebtModal" class="modal" style="display: flex;">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon" style="color:var(--amber-500);">🛒</div>
        <div class="head-text" style="text-align:left;">
          <h2>Registrar Compra</h2>
          <p>
            {{ debtData.nombre }}
          </p>
        </div>
        <button class="modal-close" @click="showDebtModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveDebt" autocomplete="off">
        <div class="form-group" style="text-align:left;">
          <label>Monto de la compra</label>
          <div style="position: relative;">
            <input type="text" class="form-control" v-model="displayDebtMonto" @input="formatDebtInput" required placeholder="0.00">
          </div>
          <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
            <span>Cupo:</span>
            <span style="font-weight: 600; color: var(--text);">{{ formatCurrency(debtData.limite_credito - debtData.deuda_actual).replace('COP', '').trim() }}</span>
          </div>
        </div>
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" @click="showDebtModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" style="background:var(--amber-500);" :disabled="isSubmitting">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* ── Visual Credit Card ── */
.cc-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow-card);
  transition: transform .2s, box-shadow .2s;
}

.cc-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, .15);
}

.cc-visual {
  padding: 24px;
  color: white;
  position: relative;
  min-height: 180px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.cc-visual::before {
  content: '';
  position: absolute;
  top: -30%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: rgba(255, 255, 255, .08);
  border-radius: 50%;
}

.cc-visual::after {
  content: '';
  position: absolute;
  bottom: -40%;
  left: -15%;
  width: 250px;
  height: 250px;
  background: rgba(255, 255, 255, .05);
  border-radius: 50%;
}

.cc-visual-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 1;
}

.cc-chip {
  opacity: .8;
}

.cc-status-pill {
  font-size: .7rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  backdrop-filter: blur(8px);
}

.cc-status-pill.status-ok {
  background: rgba(34, 197, 94, .25);
  color: #86efac;
}

.cc-status-pill.status-warning {
  background: rgba(245, 158, 11, .25);
  color: #fcd34d;
}

.cc-status-pill.status-urgent {
  background: rgba(239, 68, 68, .25);
  color: #fca5a5;
}

.cc-status-pill.status-info {
  background: rgba(59, 130, 246, .25);
  color: #93c5fd;
}

.cc-digits {
  font-family: var(--font-mono), 'Courier New', monospace;
  font-size: 1.25rem;
  letter-spacing: 4px;
  display: flex;
  gap: 16px;
  position: relative;
  z-index: 1;
  margin: 20px 0 16px;
}

.cc-label-small {
  display: block;
  font-size: .65rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  opacity: .6;
  margin-bottom: 2px;
}

.cc-name {
  font-size: .85rem;
  font-weight: 600;
}

.cc-visual-bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  position: relative;
  z-index: 1;
}

/* ── Card Info ── */
.cc-info {
  padding: 20px 24px 22px;
}

.cc-info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.cc-info-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cc-info-label {
  font-size: .72rem;
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: .5px;
}

.cc-info-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text);
}

.cc-info-value-sm {
  font-size: .95rem;
  font-weight: 600;
  color: var(--text);
}

/* ── Utilization Bar ── */
.cc-util-bar {
  width: 100%;
  height: 6px;
  background: var(--surface-2);
  border-radius: 99px;
  margin-top: 14px;
  overflow: hidden;
}

.cc-util-fill {
  height: 100%;
  border-radius: 99px;
  transition: width .5s ease;
}

/* ── Color Picker ── */
.color-grid {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.color-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: 3px solid transparent;
  cursor: pointer;
  transition: all .2s;
}

.color-btn:hover {
  transform: scale(1.15);
}

.color-btn.is-active {
  border-color: var(--text);
  box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--text);
}

/* ── Form thirds ── */
.form-third {
  flex: 1;
  min-width: 0;
}

/* ── Actions reuse ── */
.goal-actions {
  margin-top: 16px;
  display: flex;
  gap: 8px;
}

.goal-btn {
  flex: 1;
  padding: 8px 0;
  border-radius: var(--radius-md);
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  background: transparent;
  border: 1px solid var(--border);
  color: var(--text);
  transition: all .2s;
}

.goal-btn:hover {
  background: var(--surface-2);
}

.goal-btn.danger {
  color: var(--red);
  border-color: var(--red);
}

.goal-btn.danger:hover {
  background: var(--red);
  color: white;
}

/* ── Responsive Credit Cards ── */
@media (max-width: 920px) {
  .cc-visual {
    padding: 18px;
    min-height: 150px;
  }
  .cc-digits {
    font-size: 1rem;
    gap: 10px;
    margin: 14px 0 12px;
  }
  .cc-info {
    padding: 16px 18px 18px;
  }
  .cc-info-value {
    font-size: .95rem;
  }
  .cc-info-value-sm {
    font-size: .85rem;
  }
  .goal-actions {
    flex-wrap: wrap;
  }
  .goal-btn {
    min-width: calc(50% - 4px);
  }
}

@media (max-width: 560px) {
  .cc-visual {
    padding: 16px;
    min-height: 130px;
  }
  .cc-digits {
    font-size: .85rem;
    gap: 8px;
    letter-spacing: 2px;
  }
  .cc-name {
    font-size: .78rem;
  }
  .cc-label-small {
    font-size: .6rem;
  }
  .cc-info {
    padding: 14px 16px 16px;
  }
  .cc-info-label {
    font-size: .65rem;
  }
  .cc-info-value {
    font-size: .88rem;
  }
  .cc-status-pill {
    font-size: .6rem;
    padding: 3px 8px;
  }
}
</style>
