# Auditoría Heurística UX/UI - Proyecto Prosper

Como Consultor Senior de UX/UI y Experto en Product Design, he realizado una revisión profunda de la estructura, componentes, estilos y flujos del frontend de Prosper. A continuación, presento el reporte estructurado de los hallazgos y propuestas de mejora.

---

## 1. Arquitectura de Información y Navegación

### 1.1 Bloqueo abrupto por membresía Pro
- **Ubicación:** `src/router/index.js` (Líneas 46-65)
- **Impacto Negativo:** El sistema intercepta las rutas protegidas (`requiresPro`) verificando `localStorage`, cancela la navegación (`next(false)`) y dispara un modal. Esto genera una experiencia frustrante ("Dead End" o callejón sin salida). El usuario no puede ver qué se está perdiendo, simplemente se le prohíbe el paso.
- **Solución Propuesta:** 
  - **Patrón "Freemium Teaser":** En lugar de bloquear la ruta desde el router, permite que el usuario navegue a las vistas Pro (Ej: `GastosFijosView.vue`), pero renderiza una interfaz bloqueada ("Blurred State" o "Paywall Inline") con un Call to Action claro.
  - Alternativamente, si se mantiene el bloqueo en el router, asegúrate de que los enlaces a estas rutas en el menú lateral muestren visualmente que son "Pro" (ej. con un ícono de candado o corona) ANTES de que el usuario haga clic.

### 1.2 Validación de sesión dependiente de LocalStorage
- **Ubicación:** `src/router/index.js` (Línea 34 y 47)
- **Impacto Negativo:** Depender exclusivamente de `localStorage.getItem('user')` para verificar si un usuario es Pro o tiene sesión puede causar desincronización si el usuario actualiza su plan en otro dispositivo.
- **Solución Propuesta:** Implementar un estado global (ej. Pinia) que se hidrate con una llamada a la API (`/api/me`) al inicializar la app, validando el token y los permisos reales en el backend.

---

## 2. Consistencia Visual (UI)

### 2.1 Uso excesivo de Estilos en Línea (Inline Styles)
- **Ubicación:** `src/views/TarjetasCreditoView.vue` (Ej: `style="color: var(--accent);"`, `style="flex-wrap: wrap; gap: 16px;"`, `style="position:relative; flex:1;"`)
- **Impacto Negativo:** Los estilos en línea tienen mayor especificidad y no pueden ser sobrescritos fácilmente por Media Queries (Responsive Design). Esto rompe la consistencia, infla el HTML y hace que el mantenimiento del diseño escale de forma deficiente.
- **Solución Propuesta:** 
  - Mover todas las reglas de layout (flex, gaps, position) a clases utilitarias en `src/assets/main.css` (Ej: `.flex-wrap`, `.gap-4`, `.relative`).
  - Para los colores dinámicos o condicionales, utilizar clases semánticas (Ej: `<span class="text-accent">`) y definirlas en el CSS global referenciando las variables.

### 2.2 Sistema de Diseño Propio vs Escalabilidad
- **Ubicación:** `src/assets/main.css`
- **Impacto Negativo:** Tienes un archivo CSS monolítico de casi 40KB con variables bien definidas (tokens) y temas (dark, light, blue), lo cual es excelente. Sin embargo, mezclar reset, variables, layout y componentes específicos (como `.cc-card`) en un solo archivo dificulta el mantenimiento a medida que el proyecto crece.
- **Solución Propuesta:** Dividir `main.css` utilizando la arquitectura ITCSS o similar. Separar en archivos: `variables.css`, `reset.css`, `layout.css`, `utilities.css`, y usar la etiqueta `<style scoped>` de Vue para estilos altamente específicos de un componente (como las tarjetas de crédito).

---

## 3. Accesibilidad y Usabilidad (UX)

### 3.1 Input de Moneda con Comportamiento Agresivo
- **Ubicación:** `src/views/DashboardLayout.vue` (`onMontoInput`, Líneas 108-140)
- **Impacto Negativo:** Al forzar que el valor sea reemplazado inmediatamente con `Intl.NumberFormat('es-CO')`, puedes interferir con la navegación por teclado o el borrado si el usuario intenta modificar algo en medio del número. Además, eliminar silenciosamente caracteres no numéricos sin retroalimentación puede confundir a usuarios con lectores de pantalla.
- **Solución Propuesta:**
  - Usar un componente especializado para máscaras de moneda (como `v-money3` o `vue-currency-input`), que manejan correctamente el cursor y la accesibilidad.
  - Proveer el formato visual mientras el input mantiene el valor numérico limpio en el `v-model`.

### 3.2 Feedback Visual de Alertas Modales
- **Ubicación:** `DashboardLayout.vue` (Línea 122)
- **Impacto Negativo:** SweetAlert se dispara de forma invasiva si el gasto supera el balance. Si esto sucede recurrentemente, el modal interrumpe el flujo cognitivo del usuario (Flow State).
- **Solución Propuesta:** Cambiar alertas de "advertencia" (que no son bloqueantes) a validaciones inline debajo del campo de texto (Ej: *"El monto excede tu balance"* en texto rojo suave), y deshabilitar el botón de "Guardar" hasta que se corrija, en lugar de lanzar una alerta intrusiva tras escribir.

---

## 4. Puntos de Fricción

### 4.1 Fricción Extrema en el Registro (Contraseña)
- **Ubicación:** `src/views/LoginView.vue` (`handleRegister` y `checkStrength`, Línea 67)
- **Impacto Negativo:** Para registrarse, se exige `strengthProgress.value < 100`, lo cual fuerza al usuario a cumplir 4 reglas rígidas: 8 caracteres + mayúscula + número + carácter especial. El abandono de carrito (o registro en este caso) aumenta drásticamente cuando los requisitos de contraseña son tan inflexibles y el mensaje de error es un "Popup" genérico.
- **Solución Propuesta:** 
  - Mostrar los requisitos de la contraseña dinámicamente como una lista de verificación visual (✔️ / ❌) debajo del input mientras el usuario escribe.
  - Relajar el límite a un `strengthProgress >= 75` (Contraseña buena) permitiendo al usuario avanzar, o utilizar validadores de entropía moderna (como zxcvbn).

### 4.2 Restricción Silenciosa al Registrar Movimientos
- **Ubicación:** `DashboardLayout.vue` (`handleSaveTx`, Líneas 146-160)
- **Impacto Negativo:** No permitir registrar un gasto mayor al balance total. Existen escenarios del mundo real (Ej: sobregiros, tarjetas de crédito, gastos no registrados aún) donde el usuario sí gasta más de lo que tiene "en balance". Bloquear esta acción de tajo le quita control al usuario sobre su propia herramienta financiera.
- **Solución Propuesta:** Permitir el registro del movimiento pero con un estado visual de "Alerta de Sobregiro" en el balance total (número en rojo o advertencia). Dejar que el usuario decida si fue un error de digitación o un gasto real.

---

## 🚀 Victorias Rápidas (Quick Wins)
*Cambios de alto impacto y bajísimo esfuerzo de desarrollo:*

1. **Checklist visual de Contraseña:** En `LoginView.vue`, reemplaza la barra de progreso por 4 viñetas grises que se vuelvan verdes al cumplir cada regla (Mínimo 8 caracteres, Número, Mayúscula, Símbolo). Elimina el popup de error y simplemente deshabilita el botón "Crear cuenta" hasta que estén en verde.
2. **Eliminar Bloqueo de Balance:** En `DashboardLayout.vue` remueve la restricción de que un gasto no puede ser mayor al balance. Es una app de registro, el usuario debe tener la libertad de reportar saldos negativos.
3. **Indicador Pro en Navegación:** Añade un ícono de candado o estrella (✨) junto al texto de los enlaces Pro en el menú lateral (Gastos Fijos, Estadísticas, etc.) para que el usuario *espere* un muro de pago antes de hacer clic, reduciendo la frustración.
4. **Validaciones Inline en lugar de SweetAlerts:** Para errores de formulario (como contraseñas que no coinciden), renderiza un `<span class="text-red">` debajo del input. Reserva SweetAlert solo para confirmaciones destructivas (Ej: "¿Seguro que deseas eliminar esta cuenta?") o éxitos importantes.
