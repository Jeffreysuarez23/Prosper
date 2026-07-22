<script setup>
import { ref, computed, onMounted, watch, inject } from 'vue'
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
    const params = {
      q: searchQuery.value,
      status: statusFilter.value
    }
    const res = await api.get('/objetivos', { params })
    data.value = res.data
  } catch (error) {
    console.error("Error cargando objetivos", error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

let searchTimeout
watch([searchQuery, statusFilter], () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchData()
  }, 300)
})

// Stats
const totalMetas = computed(() => data.value.length)
const totalAhorrado = computed(() => data.value.reduce((sum, item) => sum + parseFloat(item.monto_actual), 0))
const totalRestante = computed(() => data.value.reduce((sum, item) => {
  const restante = parseFloat(item.monto_objetivo) - parseFloat(item.monto_actual)
  return sum + (restante > 0 ? restante : 0)
}, 0))

// Utils
const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(val || 0)
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const dateObj = new Date(dateStr)
  const monthNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
  return `${dateObj.getDate().toString().padStart(2, '0')} ${monthNames[dateObj.getMonth()]} ${dateObj.getFullYear()}`
}

const getPct = (actual, objetivo) => {
  if (parseFloat(objetivo) <= 0) return 0
  let pct = Math.floor((parseFloat(actual) / parseFloat(objetivo)) * 100)
  return pct > 100 ? 100 : pct
}

// -----------------------------
// GOAL MODAL LOGIC (Create/Edit)
// -----------------------------
const showGoalModal = ref(false)
const goalEditId = ref(null)
const goalData = ref({
  nombre: '',
  monto_objetivo: '',
  monto_actual: '',
  fecha_limite: new Date().toISOString().split('T')[0],
  icono: '🎯'
})
const displayGoalTarget = ref('')
const displayGoalSaved = ref('')

const openNewGoal = () => {
  goalEditId.value = null
  goalData.value = {
    nombre: '',
    monto_objetivo: '',
    monto_actual: '',
    fecha_limite: new Date().toISOString().split('T')[0],
    icono: '🎯',
    dia_recordatorio: ''
  }
  displayGoalTarget.value = ''
  displayGoalSaved.value = ''
  showGoalModal.value = true
}

const openEditGoal = (g) => {
  goalEditId.value = g.id
  goalData.value = {
    nombre: g.nombre,
    monto_objetivo: Math.round(g.monto_objetivo),
    monto_actual: Math.round(g.monto_actual),
    fecha_limite: g.fecha_limite.split('T')[0],
    icono: g.icono || '🎯',
    dia_recordatorio: g.dia_recordatorio || ''
  }
  displayGoalTarget.value = new Intl.NumberFormat('es-CO').format(goalData.value.monto_objetivo)
  displayGoalSaved.value = new Intl.NumberFormat('es-CO').format(goalData.value.monto_actual)
  showGoalModal.value = true
}

const formatGoalTarget = (e) => {
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayGoalTarget.value = ''
    goalData.value.monto_objetivo = ''
    return
  }
  displayGoalTarget.value = new Intl.NumberFormat('es-CO').format(parseInt(rawValue))
  goalData.value.monto_objetivo = rawValue
  e.target.value = displayGoalTarget.value
}

const formatGoalSaved = (e) => {
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayGoalSaved.value = ''
    goalData.value.monto_actual = ''
    return
  }
  
  let numericValue = parseInt(rawValue)
  const target = parseFloat(goalData.value.monto_objetivo) || 0
  
  // Calculate max allowed for initial savings (cap at target and available balance)
  // For new goals, it's just the balance. For edits, it's balance + old savings.
  const oldSaved = goalEditId.value ? Math.round(data.value.find(g => g.id === goalEditId.value)?.monto_actual || 0) : 0
  const maxAvailable = headerBalance.value + oldSaved
  const maxAllowed = Math.min(target > 0 ? target : Infinity, maxAvailable)
  
  if (numericValue > maxAllowed) {
    numericValue = maxAllowed
    Swal.fire({
      toast: true,
      position: 'bottom-end',
      icon: 'error',
      title: numericValue === target ? 'No puede superar la meta' : 'Saldo insuficiente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
  }
  
  displayGoalSaved.value = new Intl.NumberFormat('es-CO').format(numericValue)
  goalData.value.monto_actual = numericValue
  e.target.value = displayGoalSaved.value
}

const formatInputDeposit = (e) => {
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayDepositAbono.value = ''
    depositData.value.abono = ''
    return
  }
  
  let numericValue = parseInt(rawValue)
  const remaining = depositData.value.monto_objetivo - depositData.value.monto_actual
  const maxAllowed = Math.min(remaining, headerBalance.value)
  
  if (numericValue > maxAllowed) {
    numericValue = maxAllowed
    Swal.fire({
      toast: true,
      position: 'bottom-end',
      icon: 'error',
      title: numericValue === remaining ? 'Supera la meta' : 'Saldo insuficiente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
  }

  displayDepositAbono.value = new Intl.NumberFormat('es-CO').format(numericValue)
  depositData.value.abono = numericValue
  e.target.value = displayDepositAbono.value
}

const saveGoal = async () => {
  const target = parseFloat(goalData.value.monto_objetivo) || 0
  const saved = parseFloat(goalData.value.monto_actual) || 0
  
  if (saved > target) return

  // Check if we have enough balance for the increment
  const oldSaved = goalEditId.value ? Math.round(data.value.find(g => g.id === goalEditId.value)?.monto_actual || 0) : 0
  const diff = saved - oldSaved
  if (diff > 0 && diff > headerBalance.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Balance insuficiente',
      text: 'No tienes suficiente balance para cubrir este aumento.',
      confirmButtonText: 'Entendido',
      confirmButtonColor: 'var(--accent)',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' }
    })
    return
  }

  const payload = {
    ...goalData.value,
    monto_actual: saved // Default to 0 if empty
  }

  isSubmitting.value = true
  try {
    if (goalEditId.value) {
      await api.put(`/objetivos/${goalEditId.value}`, payload)
    } else {
      await api.post('/objetivos', payload)
    }
    showGoalModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Objetivo guardado exitosamente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
  } finally {
    isSubmitting.value = false
  }
}

const deleteGoal = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar Objetivo?',
    text: "Esta acción es permanente y no se puede deshacer.",
    icon: 'warning',
    showCancelButton: true,
    background: 'var(--surface)',
    color: 'var(--text)',
    customClass: { 
      popup: 'swal-custom-popup',
      title: 'swal-custom-title',
      htmlContainer: 'swal-custom-content',
      actions: 'swal-custom-actions',
      confirmButton: 'swal-custom-confirm',
      cancelButton: 'swal-custom-cancel'
    },
    buttonsStyling: false,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/objetivos/${id}`)
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Objetivo eliminado',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
      fetchData()
      if (refreshHeaderBalance) refreshHeaderBalance()
    } catch (error) {
      console.error(error)
    }
  }
}

// -----------------------------
// DEPOSIT MODAL LOGIC
// -----------------------------
const showDepositModal = ref(false)
const depositData = ref({
  id: null,
  nombre: '',
  monto_actual: 0,
  monto_objetivo: 0,
  abono: ''
})
const displayDepositAbono = ref('')

const openDeposit = (g) => {
  depositData.value = {
    id: g.id,
    nombre: g.nombre,
    monto_actual: parseFloat(g.monto_actual),
    monto_objetivo: parseFloat(g.monto_objetivo),
    abono: ''
  }
  displayDepositAbono.value = ''
  showDepositModal.value = true
}

const saveDeposit = async () => {
  const abono = parseFloat(depositData.value.abono) || 0
  if (abono <= 0) return

  if (abono > headerBalance.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Balance insuficiente',
      text: 'No tienes suficiente balance para este abono.',
      confirmButtonText: 'Entendido',
      confirmButtonColor: 'var(--accent)',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' }
    })
    return
  }

  const restante = depositData.value.monto_objetivo - depositData.value.monto_actual
  if (abono > restante) return

  const newMontoActual = depositData.value.monto_actual + abono

  isSubmitting.value = true
  try {
    await api.put(`/objetivos/${depositData.value.id}`, {
      monto_actual: newMontoActual
    })
    showDepositModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Fondos añadidos exitosamente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
    
    // Si acaba de completar la meta, mostramos una alerta especial
    if (newMontoActual >= depositData.value.monto_objetivo) {
      setTimeout(() => {
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: `¡Felicidades! Meta completada 🎉`,
          showConfirmButton: false,
          timer: 3000,
          background: 'var(--surface)',
          color: 'var(--text)'
        })
      }, 500)
    }
    
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
  } finally {
    isSubmitting.value = false
  }
}

// -----------------------------
// WITHDRAW MODAL LOGIC
// -----------------------------
const showWithdrawModal = ref(false)
const withdrawData = ref({
  id: null,
  nombre: '',
  monto_actual: 0,
  monto_objetivo: 0,
  retiro: ''
})
const displayWithdrawAmount = ref('')

const formatInputWithdraw = (e) => {
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayWithdrawAmount.value = ''
    withdrawData.value.retiro = ''
    return
  }
  
  let numericValue = parseInt(rawValue)
  const maxAllowed = withdrawData.value.monto_actual
  
  if (numericValue > maxAllowed) {
    numericValue = maxAllowed
    Swal.fire({
      toast: true,
      position: 'bottom-end',
      icon: 'error',
      title: 'No puedes retirar más de lo ahorrado',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
  }

  displayWithdrawAmount.value = new Intl.NumberFormat('es-CO').format(numericValue)
  withdrawData.value.retiro = numericValue
  e.target.value = displayWithdrawAmount.value
}

const openWithdraw = (g) => {
  withdrawData.value = {
    id: g.id,
    nombre: g.nombre,
    monto_actual: parseFloat(g.monto_actual),
    monto_objetivo: parseFloat(g.monto_objetivo),
    retiro: ''
  }
  displayWithdrawAmount.value = ''
  showWithdrawModal.value = true
}

const saveWithdraw = async () => {
  const retiro = parseFloat(withdrawData.value.retiro) || 0
  if (retiro <= 0) return

  if (retiro > withdrawData.value.monto_actual) return

  const newMontoActual = withdrawData.value.monto_actual - retiro

  isSubmitting.value = true
  try {
    await api.put(`/objetivos/${withdrawData.value.id}`, {
      monto_actual: newMontoActual
    })
    showWithdrawModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Retiro realizado exitosamente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
    
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
  } finally {
    isSubmitting.value = false
  }
}

// -----------------------------
// HISTORIAL MODAL LOGIC
// -----------------------------
const showHistorialModal = ref(false)
const historialData = ref([])
const historialLoading = ref(false)
const historialGoalName = ref('')
const historialCurrentPage = ref(1)
const historialPerPage = 5

const historialTotalPages = computed(() => {
  return Math.ceil(historialData.value.length / historialPerPage) || 1
})

const historialPaginatedData = computed(() => {
  const start = (historialCurrentPage.value - 1) * historialPerPage
  return historialData.value.slice(start, start + historialPerPage)
})

const setHistorialPage = (p) => {
  if (p < 1 || p > historialTotalPages.value) return
  historialCurrentPage.value = p
}

const openHistorial = async (g) => {
  historialGoalName.value = g.nombre
  historialData.value = []
  historialCurrentPage.value = 1
  historialLoading.value = true
  showHistorialModal.value = true
  try {
    const res = await api.get(`/objetivos/${g.id}/historial`)
    historialData.value = res.data
  } catch (error) {
    console.error('Error cargando historial', error)
  } finally {
    historialLoading.value = false
  }
}

const formatDateTime = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const monthNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
  const hours = d.getHours().toString().padStart(2, '0')
  const mins = d.getMinutes().toString().padStart(2, '0')
  return `${d.getDate().toString().padStart(2, '0')} ${monthNames[d.getMonth()]} ${d.getFullYear()} — ${hours}:${mins}`
}
</script>

<template>
  <div v-if="loading && data.length === 0" style="padding: 20px;">Cargando metas...</div>
  
  <section v-else class="page-section">
    <!-- Goals summary -->
    <div class="mini-stats">
      <div class="mini-stat ms-income">
        <span class="ms-label">Total metas</span>
        <strong class="ms-value" style="color: #3b82f6;">{{ totalMetas }}</strong>
      </div>
      <div class="mini-stat ms-savings">
        <span class="ms-label">Total Ahorrado</span>
        <strong class="ms-value" style="color: #22c55e;">{{ formatCurrency(totalAhorrado).replace('COP', '').trim() }}</strong>
      </div>
      <div class="mini-stat ms-expense">
        <span class="ms-label">Restante Global</span>
        <strong class="ms-value" style="color: var(--red);">{{ formatCurrency(totalRestante).replace('COP', '').trim() }}</strong>
      </div>
    </div>

    <!-- Filters & Add Goal -->
    <div class="goals-header" style="flex-wrap: wrap; gap: 16px;">
      <div style="display:flex; gap:12px; align-items:center; flex:1; min-width: 300px;">
        <div class="search-wrap" style="position:relative; flex:1;">
          <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
            <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <input type="search" v-model="searchQuery" placeholder="Buscar metas..." style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
        </div>
        <select v-model="statusFilter" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface); color: white;">
          <option value="">Todas las metas</option>
          <option value="progress">En progreso</option>
          <option value="completed">Completadas</option>
        </select>
      </div>

      <button @click="openNewGoal" class="btn-accent">
        <svg viewBox="0 0 24 24" width="16" height="16">
          <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <span>Nuevo Objetivo</span>
      </button>
    </div>
    
    <!-- Goals Grid -->
    <div v-if="data.length > 0" class="goals-grid" id="goalsGrid">
      <div v-for="g in data" :key="g.id" :class="['goal-card', getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'goal-completed' : '']" :style="getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'border-color: var(--mint-400); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);' : ''">
        <div class="goal-pct" :style="getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'background: var(--mint-400); color: white;' : ''">{{ getPct(g.monto_actual, g.monto_objetivo) }}%</div>
        
        <!-- Botón de historial (tres punticos) -->
        <button class="goal-history-btn" @click="openHistorial(g)" title="Ver historial">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
            <circle cx="12" cy="5" r="2" />
            <circle cx="12" cy="12" r="2" />
            <circle cx="12" cy="19" r="2" />
          </svg>
        </button>
        
        <div class="goal-card-top">
          <div class="goal-emoji" :style="getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'background: var(--mint-400); color: white; border-color: var(--mint-500);' : ''">{{ g.icono || '🎯' }}</div>
          <div>
            <div class="goal-name" :style="getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'color: var(--mint-400);' : ''">
              {{ g.nombre }} <span v-if="getPct(g.monto_actual, g.monto_objetivo) >= 100">🎉</span>
            </div>
            <div class="goal-deadline">Cumple antes del {{ formatDate(g.fecha_limite) }}</div>
            <div v-if="g.dia_recordatorio" class="goal-deadline" style="margin-top: 2px;">Paga antes del {{ g.dia_recordatorio }}</div>
          </div>
        </div>
        
        <div class="progress"><span :style="{ width: getPct(g.monto_actual, g.monto_objetivo) + '%' }"></span></div>
        
        <div class="goal-numbers" style="align-items: flex-end;">
          <div style="display: flex; flex-direction: column; gap: 4px;">
            <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Dinero abonado</span>
            <span class="goal-saved">{{ formatCurrency(g.monto_actual).replace('COP', '').trim() }}</span>
          </div>
          <div style="display: flex; flex-direction: column; gap: 4px; text-align: right;">
            <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Objetivo a lograr</span>
            <span class="goal-target" style="font-size: .95rem; color: var(--text); font-weight: 600;">{{ formatCurrency(g.monto_objetivo).replace('COP', '').trim() }}</span>
          </div>
        </div>
        
        <div class="goal-actions">
          <button v-if="getPct(g.monto_actual, g.monto_objetivo) < 100" @click="openDeposit(g)" class="goal-btn primary">Abonar</button>
          <button v-else class="goal-btn" style="color: var(--mint-500); border-color: var(--mint-400); font-weight: 600; flex: 2; pointer-events: none;">¡Meta Completada!</button>
          
          <button v-if="g.monto_actual > 0" @click="openWithdraw(g)" class="goal-btn danger">Retirar</button>
          <button @click="openEditGoal(g)" class="goal-btn">Editar</button>
          <button @click="deleteGoal(g.id)" class="goal-btn danger">Eliminar</button>
        </div>
      </div>
    </div>
    
    <div v-else class="empty-state" id="goalsEmpty">
      <div class="empty-icon">🎯</div>
      <p>No se encontraron metas.</p>
      <small>Crea tu primera meta o cambia los filtros de búsqueda.</small>
    </div>
  </section>

  <!-- ============ MODAL: NUEVO / EDITAR META ============ -->
  <div v-if="showGoalModal" class="modal" style="display: flex;">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon">🎯</div>
        <div class="head-text">
          <h2>{{ goalEditId ? 'Editar Objetivo de Ahorro' : 'Nuevo Objetivo de Ahorro' }}</h2>
          <p>{{ goalEditId ? 'Actualiza los detalles de tu meta' : 'Planifica tu próximo gran logro' }}</p>
        </div>
        <button class="modal-close" @click="showGoalModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveGoal" autocomplete="off">
        <div class="form-group">
          <label>Nombre del Objetivo</label>
          <input type="text" v-model="goalData.nombre" placeholder="Ej: Fondo de emergencia, Viaje, etc." required>
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label>Meta a lograr</label>
            <input type="text" class="form-control" v-model="displayGoalTarget" @input="formatGoalTarget" placeholder="0.00" required>
          </div>
          <div class="form-group form-half">
            <label>Total ahorrado inicial</label>
            <input type="text" class="form-control" v-model="displayGoalSaved" @input="formatGoalSaved" placeholder="0.00">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label>Fecha Límite</label>
            <input type="date" v-model="goalData.fecha_limite" required>
          </div>
          <div class="form-group form-half">
            <label>Día de pago (Recordatorio)</label>
            <input type="number" min="1" max="31" v-model="goalData.dia_recordatorio" placeholder="Ej: 15 (Opcional)">
          </div>
        </div>
        <div class="form-group">
          <label>Icono del Objetivo</label>
          <div class="emoji-grid">
            <button type="button" v-for="emoji in ['🎯','✈️','🚗','💻','🏠','🎓','🛡️','💍','🏥','🎮']" :key="emoji"
                    :class="['emoji-btn', goalData.icono === emoji ? 'is-active' : '']"
                    @click="goalData.icono = emoji">
              {{ emoji }}
            </button>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" @click="showGoalModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" :disabled="isSubmitting">Guardar Meta</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ MODAL: ABONAR FONDOS ============ -->
  <div v-if="showDepositModal" class="modal" style="display: flex;">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon">💰</div>
        <div class="head-text" style="text-align:left;">
          <h2>Añadir Fondos</h2>
          <p>Meta: {{ depositData.nombre }}</p>
        </div>
        <button class="modal-close" @click="showDepositModal = false" type="button" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveDeposit" autocomplete="off">
        <div class="form-group" style="text-align:left;">
          <label>Monto a abonar</label>
          <div style="position: relative;">
            <input type="text" 
                   class="form-control" 
                   v-model="displayDepositAbono" 
                   @input="formatInputDeposit" 
                   placeholder="0.00" 
                   required>
          </div>
          <div style="display: flex; justify-content: space-between; margin-top: 4px; font-size: 0.85rem; color: var(--text-muted);">
            <span>Falta para completar:</span>
            <span style="font-weight: 600; color: var(--text);">{{ formatCurrency(depositData.monto_objetivo - depositData.monto_actual).replace('COP', '').trim() }}</span>
          </div>
        </div>
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" @click="showDepositModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" style="background:var(--accent);" :disabled="isSubmitting">Añadir Fondos</button>
        </div>
      </form>
    </div>
  </div>
  <!-- ============ MODAL: RETIRAR DINERO ============ -->
  <div v-if="showWithdrawModal" class="modal" style="display: flex;">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon">💸</div>
        <div class="head-text" style="text-align:left;">
          <h2>Retirar Dinero</h2>
          <p>Meta: {{ withdrawData.nombre }}</p>
        </div>
        <button class="modal-close" @click="showWithdrawModal = false" type="button" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveWithdraw" autocomplete="off">
        <div class="form-group" style="text-align:left;">
          <label>¿Cuánto deseas retirar?</label>
          <div style="position: relative;">
            <input type="text" 
                   class="form-control" 
                   v-model="displayWithdrawAmount" 
                   @input="formatInputWithdraw" 
                   placeholder="0.00" 
                   required>
          </div>
          <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
            <span>Disponible para retirar:</span>
            <span style="font-weight: 600; color: var(--text);">{{ formatCurrency(withdrawData.monto_actual).replace('COP', '').trim() }}</span>
          </div>
        </div>
        
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" @click="showWithdrawModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" style="background:var(--red);" :disabled="isSubmitting">
            {{ isSubmitting ? 'Procesando...' : 'Retirar Dinero' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ MODAL: HISTORIAL ============ -->
  <div v-if="showHistorialModal" class="modal" style="display: flex;">
    <div class="modal-content" style="max-width:480px; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon">📋</div>
        <div class="head-text" style="text-align:left;">
          <h2>Historial del objetivo</h2>
          <p>{{ historialGoalName }}</p>
        </div>
        <button class="modal-close" @click="showHistorialModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>

      <div v-if="historialLoading" style="text-align:center; padding: 32px 0; color: var(--text-muted);">
        Cargando historial...
      </div>

      <div v-else-if="historialData.length === 0" style="text-align:center; padding: 32px 0;">
        <div style="font-size: 2.5rem; margin-bottom: 12px;">📭</div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">No hay movimientos registrados aún.</p>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 4px;">Los abonos y retiros que realices aparecerán aquí.</p>
      </div>

      <div v-else class="historial-list">
        <div v-for="h in historialPaginatedData" :key="h.id" class="historial-item">
          <div class="historial-icon" :class="h.tipo === 'abono' ? 'hi-abono' : 'hi-retiro'">
            <svg v-if="h.tipo === 'abono'" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M12 5v14M5 12h14" />
            </svg>
            <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M5 12h14" />
            </svg>
          </div>
          <div class="historial-info">
            <span class="historial-tipo">{{ h.tipo === 'abono' ? 'Abono' : 'Retiro' }}</span>
            <span class="historial-fecha">{{ formatDateTime(h.created_at) }}</span>
          </div>
          <span class="historial-monto" :class="h.tipo === 'abono' ? 'hm-abono' : 'hm-retiro'">
            {{ h.tipo === 'abono' ? '+' : '-' }} {{ formatCurrency(h.monto).replace('COP', '').trim() }}
          </span>
        </div>
      </div>

      <!-- Paginación -->
      <div v-if="historialTotalPages > 1" class="pagination" style="display:flex; justify-content:center; gap:8px; margin-top:20px; padding-top:20px; border-top:1px solid var(--border);">
        <button class="btn-ghost btn-sm" :disabled="historialCurrentPage === 1" @click="setHistorialPage(historialCurrentPage - 1)">Anterior</button>
        
        <div style="display:flex; align-items:center; gap:4px;">
          <button 
            v-for="p in historialTotalPages" :key="p"
            @click="setHistorialPage(p)"
            :class="['btn-sm', historialCurrentPage === p ? 'btn-accent' : 'btn-ghost']"
            style="min-width:32px; height:32px; padding:0; border-radius:6px; display:flex; align-items:center; justify-content:center;"
          >
            {{ p }}
          </button>
        </div>
        
        <button class="btn-ghost btn-sm" :disabled="historialCurrentPage === historialTotalPages" @click="setHistorialPage(historialCurrentPage + 1)">Siguiente</button>
      </div>

      <div class="form-actions" style="margin-top:20px;">
        <button type="button" class="btn-ghost" @click="showHistorialModal = false" style="flex:1;">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.goal-card {
  position: relative;
}

.goal-card .goal-pct {
  right: 46px;
}

.goal-history-btn {
  position: absolute;
  top: 14px;
  right: 12px;
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.2s;
  opacity: 0.6;
  display: flex;
  align-items: center;
  justify-content: center;
}

.goal-history-btn:hover {
  opacity: 1;
  background: var(--surface-2);
  color: var(--accent);
}

@media (max-width: 600px) {
  .goal-history-btn {
    top: 10px;
    right: 10px;
  }
  .goal-card .goal-pct {
    right: 40px;
  }
}

.goal-btn.danger {
  color: var(--red);
  border-color: var(--red);
  transition: all 0.2s;
}

.goal-btn.danger:hover {
  background: var(--red);
  color: white;
}

/* ── Historial List ── */
.historial-list {
  max-height: 400px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.historial-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 8px;
  border-radius: 10px;
  transition: background 0.15s;
}

.historial-item:hover {
  background: var(--surface-2);
}

.historial-icon {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.hi-abono {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}

.hi-retiro {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.historial-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.historial-tipo {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--text);
}

.historial-fecha {
  font-size: 0.75rem;
  color: var(--text-muted);
}

.historial-monto {
  font-weight: 700;
  font-size: 0.9rem;
  white-space: nowrap;
}

.hm-abono {
  color: #22c55e;
}

.hm-retiro {
  color: #ef4444;
}
</style>
