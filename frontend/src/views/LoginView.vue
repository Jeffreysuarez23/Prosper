<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../services/api'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()
const isLogin = ref(true)
const showAdminOptions = ref(false)

onMounted(() => {
  if (route.query.security_breach) {
    // Hyper security alert
    Swal.fire({
      icon: 'error',
      title: 'Acceso Denegado',
      text: 'Intento de acceso a un área restringida. Por seguridad, tu sesión ha sido revocada.',
      background: 'var(--surface)',
      color: 'var(--text)',
      confirmButtonColor: '#ef4444',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' }
    })
    
    // Ensure everything is cleared
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    
    // Clean URL
    router.replace('/login')
  }
})

const goToPanel = () => {
  const token = localStorage.getItem('token');
  // En desarrollo el panel correrá típicamente en 5174 si el frontend usa 5173
  window.location.href = `https://prosper-dashboard.vercel.app/?token=${token}`;
}

const loginData = ref({
  email: '',
  password: ''
})

const registerData = ref({
  nombre: '',
  apellido: '',
  telefono: '',
  email: '',
  password: '',
  password_confirmation: ''
})

const loading = ref(false)
const errorLogin = ref('')
const errorRegister = ref('')

const eyeIconLogin = ref(false)
const eyeIconReg = ref(false)
const eyeIconRegConfirm = ref(false)

const strengthText = ref('Seguridad de la contraseña')
const strengthProgress = ref(0)
const strengthColor = ref('var(--border)')

const handleLogin = async () => {
  errorLogin.value = ''
  loading.value = true
  try {
    const res = await api.post('/login', loginData.value)
    localStorage.setItem('token', res.data.token)
    localStorage.setItem('user', JSON.stringify(res.data.user))
    
    if (res.data.user.role_id === 1) {
      showAdminOptions.value = true
    } else {
      router.push('/')
    }
  } catch (err) {
    errorLogin.value = err.response?.data?.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}

const handleRegister = async () => {
  errorRegister.value = ''

  if (registerData.value.password !== registerData.value.password_confirmation) {
    Swal.fire({
      icon: 'error',
      title: 'Ups...',
      text: 'Las contraseñas no coinciden.',
      background: 'var(--surface)',
      color: 'var(--text)',
      confirmButtonColor: 'var(--accent)',
      customClass: { popup: 'swal-custom-popup' }
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
      confirmButtonColor: 'var(--accent)',
      customClass: { popup: 'swal-custom-popup' }
    })
    return
  }

  loading.value = true
  try {
    const reqData = {
      name: `${registerData.value.nombre} ${registerData.value.apellido}`,
      telefono: registerData.value.telefono,
      email: registerData.value.email,
      password: registerData.value.password,
      password_confirmation: registerData.value.password_confirmation
    }
    await api.post('/register', reqData)
    
    Swal.fire({
      icon: 'success',
      title: '¡Cuenta creada!',
      text: 'Cuenta creada exitosamente, ya puedes iniciar sesión.',
      background: 'var(--surface)',
      color: 'var(--text)',
      confirmButtonColor: 'var(--accent)',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' }
    })

    loginData.value.email = registerData.value.email
    isLogin.value = true

    registerData.value = {
      nombre: '',
      apellido: '',
      telefono: '',
      email: '',
      password: '',
      password_confirmation: ''
    }
    strengthProgress.value = 0
    strengthText.value = 'Seguridad de la contraseña'
    strengthColor.value = 'var(--border)'
  } catch (err) {
    errorRegister.value = err.response?.data?.message || 'Error al registrarse'
  } finally {
    loading.value = false
  }
}

const checkStrength = () => {
  const pwd = registerData.value.password
  let strength = 0
  
  if (pwd.length === 0) {
    strengthProgress.value = 0
    strengthText.value = 'Seguridad de la contraseña'
    strengthColor.value = 'var(--text-muted)'
    return
  }
  
  if (pwd.length >= 8) strength += 25
  if (pwd.match(/[A-Z]/)) strength += 25
  if (pwd.match(/[0-9]/)) strength += 25
  if (pwd.match(/[\W_]/)) strength += 25
  
  strengthProgress.value = strength
  
  if (strength <= 25) {
    strengthColor.value = 'var(--error)'
    strengthText.value = 'Contraseña débil (usa mayúsculas, números y símbolos)'
  } else if (strength <= 50) {
    strengthColor.value = '#f59e0b'
    strengthText.value = 'Contraseña regular'
  } else if (strength <= 75) {
    strengthColor.value = '#3b82f6'
    strengthText.value = 'Contraseña buena'
  } else {
    strengthColor.value = '#10b981'
    strengthText.value = 'Contraseña fuerte'
  }
}

const onNameInput = (e) => registerData.value.nombre = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')
const onLastNameInput = (e) => registerData.value.apellido = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')
const onPhoneInput = (e) => registerData.value.telefono = e.target.value.replace(/[^0-9+\-\s]/g, '')

const toggleAuth = () => {
  isLogin.value = !isLogin.value
}
</script>

<template>
  <div class="login-page">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div :class="['auth-container', { 'show-register': !isLogin }]" id="authContainer">
      
      <!-- LOGIN BOX -->
      <div v-show="!showAdminOptions" class="auth-box" id="loginBox">
        <div class="logo">
          <div class="logo-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
          </div>
          Prosper
        </div>
        
        <h1 class="title">Bienvenido de nuevo</h1>
        <p class="subtitle">Ingresa tus datos para continuar.</p>

        <div v-if="errorLogin" class="error-message">{{ errorLogin }}</div>

        <form @submit.prevent="handleLogin" autocomplete="off">
          <div class="form-group">
            <label>Correo Electrónico</label>
            <input type="email" v-model="loginData.email" class="form-control" required placeholder="tu@correo.com" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Contraseña</label>
            <div class="password-wrapper">
              <input :type="eyeIconLogin ? 'text' : 'password'" v-model="loginData.password" class="form-control" required placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="password-toggle" @click="eyeIconLogin = !eyeIconLogin">
                <svg v-if="!eyeIconLogin" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">
            {{ loading ? 'Cargando...' : 'Iniciar Sesión' }}
          </button>
        </form>

        <div class="toggle-text">
          ¿No tienes cuenta? <a @click="toggleAuth">Regístrate aquí</a>
        </div>
      </div>

      <!-- REGISTER BOX -->
      <div v-show="!showAdminOptions" class="auth-box" id="registerBox">
        <div class="logo">
          <div class="logo-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
          </div>
          Prosper
        </div>
        
        <h1 class="title">Crea tu cuenta</h1>
        <p class="subtitle">Empieza a tomar el control de tus finanzas.</p>

        <div v-if="errorRegister" class="error-message">{{ errorRegister }}</div>

        <form @submit.prevent="handleRegister" autocomplete="off">
          <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
              <label>Nombre</label>
              <input type="text" v-model="registerData.nombre" @input="onNameInput" class="form-control" required placeholder="Tu Nombre" title="Solo se permiten letras" autocomplete="off">
            </div>
            <div class="form-group" style="flex: 1;">
              <label>Apellidos</label>
              <input type="text" v-model="registerData.apellido" @input="onLastNameInput" class="form-control" required placeholder="Tus Apellidos" title="Solo se permiten letras" autocomplete="off">
            </div>
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="tel" v-model="registerData.telefono" @input="onPhoneInput" class="form-control" required placeholder="Tu Teléfono" title="Solo se permiten números y el signo +" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Correo Electrónico</label>
            <input type="email" v-model="registerData.email" class="form-control" required placeholder="tu@correo.com" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Contraseña</label>
            <div class="password-wrapper">
              <input :type="eyeIconReg ? 'text' : 'password'" v-model="registerData.password" @input="checkStrength" class="form-control" required placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="password-toggle" @click="eyeIconReg = !eyeIconReg">
                <svg v-if="!eyeIconReg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
            <div class="password-strength">
              <div :style="{ color: strengthColor, fontSize: '0.8rem', marginTop: '8px' }">{{ strengthText }}</div>
              <div class="strength-bar">
                <div class="strength-progress" :style="{ width: strengthProgress + '%', backgroundColor: strengthColor }"></div>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Confirmar Contraseña</label>
            <div class="password-wrapper">
              <input :type="eyeIconRegConfirm ? 'text' : 'password'" v-model="registerData.password_confirmation" class="form-control" required placeholder="••••••••" autocomplete="new-password">
              <button type="button" class="password-toggle" @click="eyeIconRegConfirm = !eyeIconRegConfirm">
                <svg v-if="!eyeIconRegConfirm" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg v-else viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-primary" :disabled="loading">
            {{ loading ? 'Cargando...' : 'Registrarse' }}
          </button>
        </form>

        <div class="toggle-text">
          ¿Ya tienes cuenta? <a @click="toggleAuth">Inicia Sesión</a>
        </div>
      </div>

      <!-- ADMIN OPTIONS BOX -->
      <div v-if="showAdminOptions" class="auth-box" id="adminOptionsBox">
        <div class="logo">
          <div class="logo-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
          </div>
          Prosper
        </div>
        
        <h1 class="title">Hola Administrador</h1>
        <p class="subtitle">¿A dónde te gustaría ir hoy?</p>

        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 24px;">
          <button @click="goToPanel" class="btn-primary" style="background: linear-gradient(45deg, #a855f7, #ec4899); border: none; margin-top: 0;">
            Ingresar al panel
          </button>
          <button @click="router.push('/')" class="btn-primary" style="background: transparent; border: 1px solid var(--border); color: var(--text); margin-top: 0;">
            Ingresar a prosper
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap');

/* We force the dark theme variables here so the login page always looks good as the native one */
.login-page {
  --bg: #0f172a;
  --surface: #1e293b;
  --surface-hover: #334155;
  --text: #f8fafc;
  --text-muted: #94a3b8;
  --accent: #3b82f6;
  --accent-hover: #2563eb;
  --border: #334155;
  --error: #ef4444;
  --error-bg: rgba(239, 68, 68, 0.1);
  
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: var(--text);
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow-x: hidden;
  overflow-y: auto;
  position: relative;
  padding: 40px 20px;
}

/* Abstract shapes */
.shape {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    opacity: 0.5;
    z-index: 0;
}
.shape-1 {
    width: 400px;
    height: 400px;
    background: #3b82f6;
    top: -100px;
    left: -100px;
}
.shape-2 {
    width: 300px;
    height: 300px;
    background: #10b981;
    bottom: -50px;
    right: -50px;
}

.auth-container {
    width: 100%;
    max-width: 420px;
    position: relative;
    perspective: 1000px;
    margin: auto;
    transition: max-width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10;
}

.auth-container.show-register {
    max-width: 550px;
}

.auth-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    width: 100%;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease;
    transform-style: preserve-3d;
}

#loginBox {
    position: relative;
}

/* Hide register by default using position absolute */
#registerBox {
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    visibility: hidden;
    transform: translateX(50px);
}

.show-register #loginBox {
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    visibility: hidden;
    transform: translateX(-50px);
}

.show-register #registerBox {
    position: relative;
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 32px;
    justify-content: center;
}

.logo-icon {
    width: 32px;
    height: 32px;
    background: var(--accent);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.title {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.75rem;
    margin-bottom: 8px;
    text-align: center;
}

.subtitle {
    color: var(--text-muted);
    text-align: center;
    margin-bottom: 32px;
    font-size: 0.9rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-muted);
}

.form-control {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px 16px;
    border-radius: 12px;
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.btn-primary {
    width: 100%;
    background: var(--accent);
    color: white;
    border: none;
    padding: 14px;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s;
    margin-top: 8px;
}

.btn-primary:hover {
    background: var(--accent-hover);
    transform: translateY(-1px);
}

.toggle-text {
    text-align: center;
    margin-top: 24px;
    font-size: 0.9rem;
    color: var(--text-muted);
}

.toggle-text a {
    color: var(--accent);
    text-decoration: none;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.2s;
}

.toggle-text a:hover {
    color: white;
}

.error-message {
    background: var(--error-bg);
    color: var(--error);
    padding: 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 20px;
    text-align: center;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.password-wrapper {
    position: relative;
}

.password-wrapper .form-control {
    padding-right: 40px;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.password-toggle:hover {
    color: var(--text);
}

.password-strength {
    margin-top: 8px;
}

.strength-bar {
    height: 4px;
    background: var(--border);
    border-radius: 2px;
    margin-top: 4px;
    overflow: hidden;
}

.strength-progress {
    height: 100%;
    width: 0%;
    transition: width 0.3s, background-color 0.3s;
}

/* ── Responsive Login ── */
@media (max-width: 560px) {
    .auth-page {
        padding: 16px;
    }
    .auth-box {
        padding: 24px 20px;
        border-radius: 18px;
    }
    .auth-container.show-register {
        max-width: 100%;
    }
    .title {
        font-size: 1.4rem;
    }
    .subtitle {
        font-size: .82rem;
        margin-bottom: 24px;
    }
    .logo {
        font-size: 1.3rem;
        margin-bottom: 24px;
    }
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    .form-control {
        padding: 10px 14px;
        font-size: .9rem;
    }
    .btn-primary {
        padding: 12px;
        font-size: .92rem;
    }
}
</style>
