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
        
        <div class="goal-card-top">
          <div class="goal-emoji" :style="getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'background: var(--mint-400); color: white; border-color: var(--mint-500);' : ''">{{ g.icono || '🎯' }}</div>
          <div>
            <div class="goal-name" :style="getPct(g.monto_actual, g.monto_objetivo) >= 100 ? 'color: var(--mint-400);' : ''">
              {{ g.nombre }} <span v-if="getPct(g.monto_actual, g.monto_objetivo) >= 100">🎉</span>
            </div>
            <div class="goal-deadline">Cumple antes del {{ formatDate(g.fecha_limite) }}</div>
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
          <input type="text" class="form-control" v-model="displayDepositAbono" @input="formatInputDeposit" placeholder="0.00" required>
        </div>
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" @click="showDepositModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" style="background:var(--accent);" :disabled="isSubmitting">Añadir Fondos</button>
        </div>
      </form>
    </div>
  </div>
</template>
