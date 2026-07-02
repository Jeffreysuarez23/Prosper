<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}
?>
<style>
/* Estilos específicos para la sección de Notificaciones */
.notifications-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.notifications-header h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--text);
  margin: 0;
}
.btn-mark-all {
  background: none;
  border: 1px solid var(--border);
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}
.btn-mark-all:hover {
  background: var(--surface-2);
  color: var(--text);
  border-color: var(--text-faint);
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 18px 22px;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  transition: transform 0.2s, box-shadow 0.2s;
  position: relative;
  overflow: hidden;
}
.notification-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.notification-item.notif-warning,
.notification-item.notif-urgent,
.notification-item.notif-info {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.5);
}

.notification-item.notif-success {
    background: rgba(34, 197, 94, 0.05);
    border-color: rgba(34, 197, 94, 0.4);
}

.notification-item.is-unread {
  background: var(--surface-2);
  border-color: var(--accent);
  border-left: 5px solid var(--accent);
}
.notification-item.is-unread.notif-warning,
.notification-item.is-unread.notif-urgent,
.notification-item.is-unread.notif-info {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.6);
    border-left-color: var(--red);
}
.notification-item.is-unread.notif-success {
    background: rgba(34, 197, 94, 0.12);
    border-color: rgba(34, 197, 94, 0.6);
    border-left-color: var(--green);
}
.notification-item.is-unread .notif-title {
  font-weight: 800;
  font-size: 1.05rem;
}
.notification-item.is-unread::before {
  display: none;
}

.notif-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: grid;
  place-items: center;
  font-size: 1.4rem;
  flex-shrink: 0;
}
.notif-warning .notif-icon, .notif-urgent .notif-icon, .notif-info .notif-icon { 
  background: rgba(239,68,68,.12); 
  color: var(--red); 
}
.notif-success .notif-icon { background: rgba(34,197,94,.12); color: var(--green); }

.notif-content {
  flex: 1;
}
.notif-title {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--text);
}
.notif-message {
  font-size: 0.88rem;
  color: var(--text-muted);
  line-height: 1.5;
  margin-bottom: 12px;
}
.notif-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.78rem;
  color: var(--text-faint);
}
.notif-time {
  display: flex;
  align-items: center;
  gap: 4px;
}
.notif-category {
  background: var(--surface-2);
  padding: 3px 10px;
  border-radius: 999px;
  font-weight: 600;
  color: var(--text-muted);
}

.notif-actions {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex-direction: column;
}
.notif-action-btn {
  background: var(--surface-2);
  border: none;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text);
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
  width: 100%;
  text-align: center;
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

<section class="page-section">
  
  <div class="notifications-header">
    <div>
      <h2>Notificaciones</h2>
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 4px;">Avisos importantes, recordatorios y actualizaciones.</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;">
      <button class="btn-mark-all" id="btnMarkAll">Marcar todo como leído</button>
      <button class="btn-mark-all" id="btnDeleteAll" style="color: var(--red); border-color: rgba(239,68,68,0.3);">Eliminar todo</button>
    </div>
  </div>
  <div class="notifications-list" id="notifList">
    <!-- Las notificaciones se cargarán por JS -->
  </div>

  <div class="empty-state" id="notifEmpty" style="display:none; text-align:center; padding: 40px 20px;">
    <div class="empty-icon" style="font-size: 3rem; margin-bottom: 16px;">📭</div>
    <p style="font-size: 1.1rem; color: var(--text); margin-bottom: 8px;">No tienes notificaciones nuevas.</p>
    <small style="color: var(--text-muted);">Aquí aparecerán tus alertas y recordatorios de pagos u objetivos.</small>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();

    document.getElementById('btnMarkAll').addEventListener('click', async () => {
        try {
            const res = await fetch('api/notificaciones.php?action=mark_all_read', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                loadNotifications();
            }
        } catch (e) { console.error('Error:', e); }
    });

    document.getElementById('btnDeleteAll').addEventListener('click', async () => {
        const modal = document.getElementById('modalConfirm');
        if (modal) {
            const titleEl = modal.querySelector('h2');
            if (titleEl) titleEl.textContent = '¿Eliminar Todas las Notificaciones?';
            modal.classList.add('is-active');
            
            const btnYes = document.getElementById('btnConfirmYes');
            const btnNo = document.getElementById('btnConfirmNo');
            
            const cleanup = () => { 
                modal.classList.remove('is-active'); 
                btnYes.removeEventListener('click', onYes); 
                btnNo.removeEventListener('click', onNo); 
            };
            
            const onNo = () => { cleanup(); };
            
            const onYes = async () => {
                cleanup();
                try {
                    const res = await fetch('api/notificaciones.php?action=delete_all', { method: 'POST' });
                    const data = await res.json();
                    if (data.success) loadNotifications();
                } catch (e) { console.error('Error:', e); }
            };
            
            btnYes.addEventListener('click', onYes);
            btnNo.addEventListener('click', onNo);
        } else {
            if(confirm('¿Eliminar todas las notificaciones?')) {
                try {
                    const res = await fetch('api/notificaciones.php?action=delete_all', { method: 'POST' });
                    const data = await res.json();
                    if(data.success) loadNotifications();
                } catch(e) { console.error('Error:', e); }
            }
        }
    });
});

async function loadNotifications() {
    try {
        const res = await fetch('api/notificaciones.php?action=get&t=' + Date.now());
        const data = await res.json();
        console.log("NOTIFICATIONS API RESPONSE:", data);
        
        const list = document.getElementById('notifList');
        const empty = document.getElementById('notifEmpty');
        
        list.innerHTML = '';
        
        if (data.success && data.data && data.data.length > 0) {
            empty.style.display = 'none';
            list.style.display = 'flex';
            
            const totalUnread = data.unread_count || 0;
            const badgeText = totalUnread > 5 ? '+5' : totalUnread;
            
            data.data.forEach(n => {
                const isUnread = n.leida == 0 ? 'is-unread' : '';
                const timeStr = new Date(n.fecha_creacion).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
                
                const notifHTML = `
                    <div class="notification-item notif-${n.tipo} ${isUnread}">
                        <div class="notif-icon">${n.icono || '🔔'}</div>
                        <div class="notif-content">
                            <div class="notif-title">${n.titulo}</div>
                            <div class="notif-message">${n.mensaje}</div>
                            <div class="notif-meta">
                                <span class="notif-time">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    ${timeStr}
                                </span>
                                ${n.categoria ? `<span class="notif-category">${n.categoria.replace('_', ' ').toUpperCase()}</span>` : ''}
                            </div>
                        </div>
                        <div class="notif-actions">
                            ${n.leida == 0 ? `<button class="notif-action-btn" onclick="markAsRead(${n.id_notificacion})">Marcar leída</button>` : ''}
                            ${n.accion_url ? `<a href="${n.accion_url}" class="notif-action-btn primary">${n.accion_texto || 'Ver detalles'}</a>` : ''}
                            <button class="notif-action-btn" onclick="deleteNotification(${n.id_notificacion})" title="Eliminar alerta" style="padding: 8px 12px; color: var(--red);"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6"/></svg></button>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', notifHTML);
            });
            
            const badge = document.getElementById('sidebarNotifBadge');
            if (badge) {
                if (totalUnread > 0) {
                    badge.textContent = badgeText;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        } else {
            empty.style.display = 'block';
            const badge = document.getElementById('sidebarNotifBadge');
            if (badge) badge.style.display = 'none';
        }
    } catch (e) {
        console.error('Error fetching notifications:', e);
    }
}

async function markAsRead(id) {
    try {
        const formData = new FormData();
        formData.append('id', id);
        const res = await fetch('api/notificaciones.php?action=mark_read', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            loadNotifications();
        }
    } catch (e) {
        console.error('Error marking as read:', e);
    }
}

async function deleteNotification(id) {
    const modal = document.getElementById('modalConfirm');
    if (modal) {
        const titleEl = modal.querySelector('h2');
        if (titleEl) titleEl.textContent = '¿Eliminar Notificación?';
        
        modal.classList.add('is-active');
        
        const btnYes = document.getElementById('btnConfirmYes');
        const btnNo = document.getElementById('btnConfirmNo');
        
        const cleanup = () => { 
            modal.classList.remove('is-active'); 
            btnYes.removeEventListener('click', onYes); 
            btnNo.removeEventListener('click', onNo); 
        };
        
        const onNo = () => { cleanup(); };
        
        const onYes = async () => {
            cleanup();
            try {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('api/notificaciones.php?action=delete', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    loadNotifications();
                }
            } catch (e) {
                console.error('Error deleting:', e);
            }
        };
        
        btnYes.addEventListener('click', onYes);
        btnNo.addEventListener('click', onNo);
    } else {
        if(confirm('¿Eliminar notificación?')) {
            try {
                const formData = new FormData();
                formData.append('id', id);
                const res = await fetch('api/notificaciones.php?action=delete', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) loadNotifications();
            } catch(e) { console.error('Error:', e); }
        }
    }
}
</script>
