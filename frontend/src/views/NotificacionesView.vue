<script setup>
import { ref, onMounted, inject } from 'vue'
import api from '../services/api'
import Swal from 'sweetalert2'

const data = ref([])
const loading = ref(true)
const refreshHeaderBalance = inject('refreshHeaderBalance', () => {})

const loadNotifications = async () => {
  try {
    const res = await api.get('/notificaciones')
    data.value = res.data
  } catch (error) {
    console.error("Error cargando notificaciones", error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadNotifications()
})

const markAllRead = async () => {
  try {
    await api.put('/notificaciones/read-all')
    data.value = data.value.map(n => ({ ...n, leida: 1 }))
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Todas marcadas como leídas',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
    refreshHeaderBalance()
  } catch (error) {
    console.error(error)
  }
}

const deleteAll = async () => {
  const result = await Swal.fire({
    title: '¿Eliminar todas?',
    text: "Esta acción borrará todo el historial de notificaciones permanentemente.",
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
    confirmButtonText: 'Sí, eliminar todo',
    cancelButtonText: 'Cancelar'
  })

  if (result.isConfirmed) {
    try {
      await api.delete('/notificaciones')
      data.value = []
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Notificaciones eliminadas',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
      refreshHeaderBalance()
    } catch (error) {
      console.error(error)
    }
  }
}

const markAsRead = async (id) => {
  try {
    await api.put(`/notificaciones/${id}/read`)
    const item = data.value.find(n => n.id === id)
    if (item) item.leida = 1
    refreshHeaderBalance()
  } catch (error) {
    console.error(error)
  }
}

const deleteNotification = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar notificación?',
    text: "Esta acción es permanente.",
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
    cancelButtonText: 'Cancelar'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/notificaciones/${id}`)
      data.value = data.value.filter(n => n.id !== id)
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Notificación eliminada',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
      refreshHeaderBalance()
    } catch (error) {
      console.error(error)
    }
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const dateObj = new Date(dateStr)
  return dateObj.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
}
</script>

<template>
  <div v-if="loading" style="padding: 20px;">Cargando notificaciones...</div>
  
  <section v-else class="page-section">
    <div class="notifications-header">
      <div>
        <h2>Notificaciones</h2>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;">Avisos importantes, recordatorios y actualizaciones.</p>
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
        <button @click="markAllRead" class="btn-mark-all" id="btnMarkAll">Marcar todo como leído</button>
        <button @click="deleteAll" class="btn-mark-all" id="btnDeleteAll" style="color: var(--red); border-color: rgba(239,68,68,0.3);">Eliminar todo</button>
      </div>
    </div>
    
    <div v-if="data.length > 0" class="notifications-list" id="notifList">
      <div v-for="n in data" :key="n.id" :class="['notification-item', `notif-${n.tipo}`, n.leida == 0 ? 'is-unread' : '']">
        <div class="notif-icon">{{ n.icono || '🔔' }}</div>
        
        <div class="notif-content">
          <div class="notif-title">{{ n.titulo }}</div>
          <div class="notif-message">{{ n.mensaje }}</div>
          
          <div class="notif-meta">
            <span class="notif-time">
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              {{ formatDate(n.created_at || n.fecha_creacion) }}
            </span>
            <span v-if="n.categoria" class="notif-category">{{ n.categoria.replace('_', ' ').toUpperCase() }}</span>
          </div>
        </div>
        
        <div class="notif-actions">
          <button v-if="n.leida == 0" class="notif-action-btn" @click="markAsRead(n.id)">Marcar leída</button>
          <a v-if="n.accion_url" :href="n.accion_url" class="notif-action-btn primary">{{ n.accion_texto || 'Ver detalles' }}</a>
          <button class="notif-action-btn" @click="deleteNotification(n.id)" title="Eliminar alerta" style="padding: 8px 12px; color: var(--red);">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <div v-else class="empty-state" id="notifEmpty" style="text-align:center; padding: 40px 20px;">
      <div class="empty-icon" style="font-size: 3rem; margin-bottom: 16px;">📭</div>
      <p style="font-size: 1.1rem; color: var(--text); margin-bottom: 8px;">No tienes notificaciones nuevas.</p>
      <small style="color: var(--text-muted);">Aquí aparecerán tus alertas y recordatorios de pagos u objetivos.</small>
    </div>
  </section>
</template>

<style scoped>
.notifications-header {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
}
.notifications-header h2 {
  font-size: 1.25rem; font-weight: 600; color: var(--text); margin: 0;
}
.btn-mark-all {
  background: none; border: 1px solid var(--border); padding: 8px 16px;
  border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 500;
  color: var(--text-muted); cursor: pointer; transition: all 0.2s; font-family: inherit;
}
.btn-mark-all:hover {
  background: var(--surface-2); color: var(--text); border-color: var(--text-faint);
}
.notifications-list { display: flex; flex-direction: column; gap: 12px; }
.notification-item {
  display: flex; align-items: flex-start; gap: 16px; background: var(--surface);
  border: 1px solid var(--border); padding: 18px 22px; border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card); transition: transform 0.2s, box-shadow 0.2s;
  position: relative; overflow: hidden;
}
.notification-item:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }

.notification-item.notif-warning, .notification-item.notif-urgent, .notification-item.notif-info {
  background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.5);
}
.notification-item.notif-success {
  background: rgba(34, 197, 94, 0.05); border-color: rgba(34, 197, 94, 0.4);
}
.notification-item.is-unread {
  background: var(--surface-2); border-color: var(--accent); border-left: 5px solid var(--accent);
}
.notification-item.is-unread.notif-warning, .notification-item.is-unread.notif-urgent, .notification-item.is-unread.notif-info {
  background: rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.6); border-left-color: var(--red);
}
.notification-item.is-unread.notif-success {
  background: rgba(34, 197, 94, 0.12); border-color: rgba(34, 197, 94, 0.6); border-left-color: var(--green);
}
.notification-item.is-unread .notif-title { font-weight: 800; font-size: 1.05rem; }

.notif-icon {
  width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center;
  font-size: 1.4rem; flex-shrink: 0;
}
.notif-warning .notif-icon, .notif-urgent .notif-icon, .notif-info .notif-icon { 
  background: rgba(239,68,68,.12); color: var(--red); 
}
.notif-success .notif-icon { background: rgba(34,197,94,.12); color: var(--green); }

.notif-content { flex: 1; }
.notif-title { font-size: 1rem; font-weight: 600; margin-bottom: 6px; color: var(--text); }
.notif-message { font-size: 0.88rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 12px; }
.notif-meta { display: flex; align-items: center; gap: 12px; font-size: 0.78rem; color: var(--text-faint); }
.notif-time { display: flex; align-items: center; gap: 4px; }
.notif-category {
  background: var(--surface-2); padding: 3px 10px; border-radius: 999px; font-weight: 600; color: var(--text-muted);
}

.notif-actions { display: flex; align-items: flex-start; gap: 8px; flex-direction: column; }
.notif-action-btn {
  background: var(--surface-2); border: none; padding: 8px 16px; border-radius: 8px;
  font-size: 0.82rem; font-weight: 600; color: var(--text); cursor: pointer; transition: all 0.15s;
  font-family: inherit; width: 100%; text-align: center;
}
.notif-action-btn:hover { background: var(--border); color: var(--text); }
.notif-action-btn.primary { background: var(--accent); color: #08130f; }
html[data-theme="light"] .notif-action-btn.primary { color: #ffffff; }
.notif-action-btn.primary:hover { opacity: 0.9; }

@media (min-width: 640px) {
  .notif-actions { flex-direction: row; align-items: center; }
  .notif-action-btn { width: auto; }
}
</style>
