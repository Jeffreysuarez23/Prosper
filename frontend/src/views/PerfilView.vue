<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import Swal from 'sweetalert2'

const router = useRouter()
const user = ref({
  nombre: '',
  apellido: '',
  telefono: '',
  correo: ''
})

const pwdForm = ref({
  contrasena_actual: '',
  nueva_contrasena: '',
  confirmar_contrasena: ''
})

const strengthText = ref('Seguridad de la contraseña')
const strengthProgress = ref(0)
const strengthColor = ref('var(--border)')

const eyeIconCurrent = ref(false)
const eyeIconNew = ref(false)
const eyeIconConfirm = ref(false)

onMounted(async () => {
  // En un caso real podrías traer los datos del usuario de un endpoint /me
  // Por ahora sacamos los datos del localStorage que se guardaron en el login
  const userData = JSON.parse(localStorage.getItem('user') || '{}')
  user.value.nombre = userData.name || ''
  user.value.correo = userData.email || ''
  
  // Asumiendo que guardamos apellido en name para simular
  const nameParts = (userData.name || '').split(' ')
  user.value.nombre = nameParts[0] || ''
  user.value.apellido = nameParts.slice(1).join(' ') || ''
  user.value.telefono = userData.telefono || ''
})

const handleUpdateProfile = async () => {
  try {
    const res = await api.put('/user/profile', {
      nombre: user.value.nombre,
      apellido: user.value.apellido,
      telefono: user.value.telefono
    })
    
    // Actualizar localStorage
    const userData = JSON.parse(localStorage.getItem('user') || '{}')
    userData.name = res.data.user.name
    userData.telefono = res.data.user.telefono
    localStorage.setItem('user', JSON.stringify(userData))
    window.dispatchEvent(new CustomEvent('user-updated'))
    
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Perfil actualizado exitosamente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudo actualizar el perfil.',
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { 
        popup: 'swal-custom-popup',
        title: 'swal-custom-title',
        htmlContainer: 'swal-custom-content',
        actions: 'swal-custom-actions',
        confirmButton: 'swal-custom-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'Entendido'
    })
  }
}

const handleUpdatePassword = async () => {
  if (pwdForm.value.nueva_contrasena !== pwdForm.value.confirmar_contrasena) {
    Swal.fire({
      icon: 'error',
      title: 'Ups...',
      text: 'Las contraseñas nuevas no coinciden.',
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { 
        popup: 'swal-custom-popup',
        title: 'swal-custom-title',
        htmlContainer: 'swal-custom-content',
        actions: 'swal-custom-actions',
        confirmButton: 'swal-custom-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'Entendido'
    })
    return
  }

  if (strengthProgress.value < 100) {
    Swal.fire({
      icon: 'warning',
      title: 'Contraseña débil',
      text: 'Debe tener al menos 8 caracteres, una mayúscula, un número y un carácter especial.',
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { 
        popup: 'swal-custom-popup',
        title: 'swal-custom-title',
        htmlContainer: 'swal-custom-content',
        actions: 'swal-custom-actions',
        confirmButton: 'swal-custom-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'Entendido'
    })
    return
  }

  try {
    await api.put('/user/password', {
      contrasena_actual: pwdForm.value.contrasena_actual,
      nueva_contrasena: pwdForm.value.nueva_contrasena
    })

    Swal.fire({
      icon: 'success',
      title: 'Contraseña actualizada',
      text: 'Tu contraseña ha sido actualizada. Por favor, inicia sesión nuevamente.',
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { 
        popup: 'swal-custom-popup',
        title: 'swal-custom-title',
        htmlContainer: 'swal-custom-content',
        actions: 'swal-custom-actions',
        confirmButton: 'swal-custom-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'Iniciar Sesión'
    }).then(() => {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      delete api.defaults.headers.common['Authorization']
      router.push('/login')
    })
  } catch (error) {
    const errorMsg = error.response?.data?.errors?.contrasena_actual?.[0] || 
                     error.response?.data?.errors?.nueva_contrasena?.[0] || 
                     'Error al actualizar la contraseña'
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: errorMsg,
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { 
        popup: 'swal-custom-popup',
        title: 'swal-custom-title',
        htmlContainer: 'swal-custom-content',
        actions: 'swal-custom-actions',
        confirmButton: 'swal-custom-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'Entendido'
    })
  }
}

const checkStrength = () => {
  const pwd = pwdForm.value.nueva_contrasena
  let strength = 0
  if (pwd.length === 0) {
    strengthProgress.value = 0
    strengthColor.value = 'var(--border)'
    strengthText.value = 'Seguridad de la contraseña'
    return
  }
  
  if (pwd.length >= 8) strength += 25
  if (pwd.match(/[A-Z]/)) strength += 25
  if (pwd.match(/[0-9]/)) strength += 25
  if (pwd.match(/[\W_]/)) strength += 25
  
  strengthProgress.value = strength
  
  if (strength < 50) {
    strengthColor.value = '#ef4444' // red
    strengthText.value = 'Débil'
  } else if (strength < 100) {
    strengthColor.value = '#f59e0b' // yellow
    strengthText.value = 'Media'
  } else {
    strengthColor.value = '#22c55e' // green
    strengthText.value = 'Fuerte'
  }
}

const onNameInput = (e) => {
  user.value.nombre = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')
}
const onLastNameInput = (e) => {
  user.value.apellido = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')
}
const onPhoneInput = (e) => {
  user.value.telefono = e.target.value.replace(/[^0-9+\-\s]/g, '')
}
</script>

<template>
  <div class="content-section" style="max-width: 800px; margin: 0 auto; padding-top: 24px;">
    
    <div class="card" style="margin-bottom: 24px;">
      <div class="card-head" style="margin-bottom: 24px;">
        <h2 class="card-title">Información Personal</h2>
      </div>
      <div class="card-body">
        <form @submit.prevent="handleUpdateProfile" autocomplete="off">
          <div class="form-row">
            <div class="form-group form-half">
              <label>Nombre</label>
              <input type="text" v-model="user.nombre" @input="onNameInput" class="form-control" required title="Solo se permiten letras">
            </div>
            <div class="form-group form-half">
              <label>Apellidos</label>
              <input type="text" v-model="user.apellido" @input="onLastNameInput" class="form-control" required title="Solo se permiten letras">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group form-half">
              <label>Teléfono</label>
              <input type="tel" v-model="user.telefono" @input="onPhoneInput" class="form-control" required title="Solo se permiten números y el signo +">
            </div>
            <div class="form-group form-half">
              <label>Correo Electrónico</label>
              <input type="email" :value="user.correo" class="form-control" disabled 
                     style="background: var(--surface-hover); cursor: not-allowed; opacity: 0.7;"
                     title="El correo no se puede cambiar">
            </div>
          </div>

          <div class="form-actions" style="margin-top: 24px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn-accent">Guardar Cambios</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head" style="margin-bottom: 24px;">
        <h2 class="card-title">Seguridad</h2>
      </div>
      <div class="card-body">
        <form @submit.prevent="handleUpdatePassword" autocomplete="off">
          <div class="form-group" style="max-width: 400px;">
            <label>Contraseña Actual</label>
            <div class="password-wrapper">
              <input :type="eyeIconCurrent ? 'text' : 'password'" v-model="pwdForm.contrasena_actual" class="form-control" required placeholder="••••••••">
              <button type="button" class="password-toggle" @click="eyeIconCurrent = !eyeIconCurrent">
                <svg v-if="!eyeIconCurrent" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>

          <div class="form-row" style="margin-top: 16px;">
            <div class="form-group form-half">
              <label>Nueva Contraseña</label>
              <div class="password-wrapper">
                <input :type="eyeIconNew ? 'text' : 'password'" v-model="pwdForm.nueva_contrasena" @input="checkStrength" class="form-control" required placeholder="••••••••">
                <button type="button" class="password-toggle" @click="eyeIconNew = !eyeIconNew">
                  <svg v-if="!eyeIconNew" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                  <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
              </div>
              <div class="password-strength" style="margin-top: 8px;">
                <div style="font-size: 0.8rem; color: var(--text-muted);">{{ strengthText }}</div>
                <div class="strength-bar" style="height: 4px; background: var(--border); border-radius: 2px; margin-top: 4px; overflow: hidden;">
                  <div class="strength-progress" :style="{ height: '100%', width: strengthProgress + '%', backgroundColor: strengthColor, transition: 'width 0.3s, background-color 0.3s' }"></div>
                </div>
              </div>
            </div>
            
            <div class="form-group form-half">
              <label>Confirmar Nueva Contraseña</label>
              <div class="password-wrapper">
                <input :type="eyeIconConfirm ? 'text' : 'password'" v-model="pwdForm.confirmar_contrasena" class="form-control" required placeholder="••••••••">
                <button type="button" class="password-toggle" @click="eyeIconConfirm = !eyeIconConfirm">
                  <svg v-if="!eyeIconConfirm" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                  <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="form-actions" style="margin-top: 24px; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn-accent" style="background: var(--blue-600); border-color: var(--blue-600);">Actualizar Contraseña</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.password-wrapper { position: relative; }
.password-wrapper .form-control { padding-right: 40px; }
.password-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--text-muted);
    cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center;
}
.password-toggle:hover { color: var(--text); }
</style>
