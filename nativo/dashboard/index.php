<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}

require_once 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
$id_usuario = (int)$_SESSION['id_usuario'];

require_once 'acciones/generar_notificaciones.php';
generarNotificacionesAutomaticas($id_usuario, $db);

$queryUser = "SELECT * FROM usuarios WHERE id_usuario = :id_usuario LIMIT 1";
$stmtUser = $db->prepare($queryUser);
$stmtUser->bindParam(":id_usuario", $id_usuario);
$stmtUser->execute();
$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$user_nombre = $userData ? $userData['nombre'] : 'Usuario';
$user_correo = $userData ? $userData['correo'] : '';
$user_inicial = strtoupper(substr($user_nombre, 0, 1));

$queryBalance = "SELECT tipo, SUM(monto) as total FROM movimientos WHERE id_usuario = :id_usuario GROUP BY tipo";
$stmtBalance = $db->prepare($queryBalance);
$stmtBalance->bindParam(":id_usuario", $id_usuario);
$stmtBalance->execute();

$ingresos_tot = 0;
$gastos_tot = 0;
while ($row = $stmtBalance->fetch(PDO::FETCH_ASSOC)) {
    if ($row['tipo'] == 'ingreso') $ingresos_tot = $row['total'];
    if ($row['tipo'] == 'gasto') $gastos_tot = $row['total'];
}
$global_balance = $ingresos_tot - $gastos_tot;

$queryNotifs = "SELECT COUNT(*) as unread_count FROM notificaciones WHERE id_usuario = :id_usuario AND leida = 0";
$stmtNotifs = $db->prepare($queryNotifs);
$stmtNotifs->execute([':id_usuario' => $id_usuario]);
$unreadNotifs = (int)$stmtNotifs->fetch(PDO::FETCH_ASSOC)['unread_count'];
$badgeText = $unreadNotifs > 5 ? '+5' : $unreadNotifs;
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prosper — Dashboard</title>
  <meta name="description" content="Panel principal de tus finanzas personales.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="styles/main.css?v=<?php echo time(); ?>">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
      .swal-custom-popup {
          border: 1px solid var(--border) !important;
          border-radius: 24px !important;
          font-family: 'Inter', sans-serif !important;
      }
      .swal2-title {
          font-family: 'Space Grotesk', sans-serif !important;
      }
  </style>
</head>

<body data-page="<?php echo htmlspecialchars($_GET['mod'] ?? 'dashboard', ENT_QUOTES, 'UTF-8'); ?>" data-balance="<?php echo $global_balance; ?>">

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
        <a class="nav-item <?php echo (@$_GET['mod'] == 'dashboard' || @$_GET['mod'] == '') ? 'is-active' : ''; ?>" href="index.php?mod=dashboard">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M4 12 L12 4 L20 12 M6 10 V20 H18 V10" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Dashboard</span>
        </a>
        <a class="nav-item <?php echo (@$_GET['mod'] == 'movimientos') ? 'is-active' : ''; ?>" href="index.php?mod=movimientos">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M12 2v20M17 7l-5-5-5 5M7 17l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span>Movimientos</span>
        </a>
        <a class="nav-item <?php echo (@$_GET['mod'] == 'estadisticas') ? 'is-active' : ''; ?>" href="index.php?mod=estadisticas">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M5 20V10M12 20V4M19 20v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <span>Estadísticas</span>
        </a>
        <a class="nav-item <?php echo (@$_GET['mod'] == 'objetivos') ? 'is-active' : ''; ?>" href="index.php?mod=objetivos">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8" />
            <circle cx="12" cy="12" r="5" fill="none" stroke="currentColor" stroke-width="1.8" />
            <circle cx="12" cy="12" r="1.5" fill="currentColor" />
          </svg>
          <span>Objetivos</span>
        </a>
        <a class="nav-item <?php echo (@$_GET['mod'] == 'gastos_fijos') ? 'is-active' : ''; ?>" href="index.php?mod=gastos_fijos">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.8" />
            <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="1.8" />
            <line x1="8" y1="15" x2="16" y2="15" stroke="currentColor" stroke-width="1.8" />
          </svg>
          <span>Gastos Fijos</span>
        </a>
        <a class="nav-item <?php echo (@$_GET['mod'] == 'notificaciones') ? 'is-active' : ''; ?>" href="index.php?mod=notificaciones">
          <svg class="nav-icon" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span style="flex: 1;">Notificaciones</span>
          <span id="sidebarNotifBadge" style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; display: <?php echo $unreadNotifs > 0 ? 'inline-block' : 'none'; ?>;"><?php echo $badgeText; ?></span>
        </a>
      </nav>
      <div class="sidebar-foot" style="display:flex; justify-content:center; gap:12px;">
        <button class="theme-circle" data-set-theme="dark" style="background: linear-gradient(to right, #0c1114 50%, #4fd3a8 50%);" aria-label="Verde Oscuro"></button>
        <button class="theme-circle" data-set-theme="light" style="background: linear-gradient(to right, #ffffff 50%, #000000 50%);" aria-label="Blanco y Negro"></button>
        <button class="theme-circle" data-set-theme="blue" style="background: linear-gradient(to right, #141b2d 50%, #3b82f6 50%);" aria-label="Azul Oscuro"></button>
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
          <?php
            $current_mod = isset($_GET['mod']) ? $_GET['mod'] : 'dashboard';
            if ($current_mod == '') $current_mod = 'dashboard';
            
            $pageTitle = 'Aquí está tu resumen financiero.';
            $pageSubtitle = 'Controla tus ingresos, gastos y metas de forma sencilla.';
            
            if ($current_mod == 'movimientos') {
                $pageTitle = 'Movimientos';
                $pageSubtitle = 'Historial completo de tus ingresos y gastos.';
            } elseif ($current_mod == 'estadisticas') {
                $pageTitle = 'Estadísticas';
                $pageSubtitle = 'Analiza tu desempeño financiero con gráficos detallados.';
            } elseif ($current_mod == 'objetivos') {
                $pageTitle = 'Objetivos';
                $pageSubtitle = 'Supervisa el progreso de tus metas de ahorro.';
            } elseif ($current_mod == 'gastos_fijos') {
                $pageTitle = 'Gastos Fijos';
                $pageSubtitle = 'Administra tus compromisos y pagos recurrentes.';
            } elseif ($current_mod == 'notificaciones') {
                $pageTitle = 'Notificaciones';
                $pageSubtitle = 'Mantente al tanto de tus pagos pendientes y alertas.';
            } elseif ($current_mod == 'perfil') {
                $pageTitle = 'Mi Perfil';
                $pageSubtitle = 'Administra tu información personal y seguridad.';
            }
          ?>
          <div class="topbar-title">
            <h1><?php echo $pageTitle; ?></h1>
            <p><?php echo $pageSubtitle; ?></p>
          </div>
        </div>
        <div class="topbar-actions">
          <div class="topbar-balance" id="topbarBalance">
            <span class="balance-label">Balance total</span>
            <strong class="balance-value" id="headerBalance"><?php echo number_format($global_balance, 2, ',', '.'); ?></strong>
          </div>
          <button class="btn-accent" id="btnNewTx">
            <svg viewBox="0 0 24 24" width="16" height="16">
              <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>Nuevo</span>
          </button>
          <div class="avatar-dropdown">
            <div class="avatar"><?php echo $user_inicial; ?></div>
            <div class="dropdown-menu">
              <div class="dropdown-header">
                <div class="dh-avatar"><?php echo $user_inicial; ?></div>
                <div class="dh-info">
                  <span class="dh-name"><?php echo htmlspecialchars($user_nombre); ?></span>
                  <span class="dh-email"><?php echo htmlspecialchars($user_correo); ?></span>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <a href="index.php?mod=perfil" class="dropdown-item">
                <svg viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
                Mi Perfil
              </a>
              <div class="dropdown-divider"></div>
              <a href="../logout.php" class="dropdown-item text-red">
                <svg viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Cerrar sesión
              </a>
            </div>
          </div>
        </div>
      </header>
        <?php
            if(@$_GET['mod'] == "") {
               require_once("modulos/dashboard.php");
            }else
            if(@$_GET['mod'] == "dashboard") {
                require_once("modulos/dashboard.php");
            }else
            if(@$_GET['mod'] == "estadisticas") {
                require_once("modulos/estadisticas.php");
            }else
            if(@$_GET['mod'] == "movimientos") {
                require_once("modulos/movimientos.php");
            }else
            if(@$_GET['mod'] == "objetivos") {
                require_once("modulos/objetivos.php");
            }else
            if(@$_GET['mod'] == "gastos_fijos") {
                require_once("modulos/gastos_fijos.php");
            }else
            if(@$_GET['mod'] == "notificaciones") {
                require_once("modulos/notificaciones.php");
            }else
            if(@$_GET['mod'] == "perfil") {
                require_once("modulos/perfil.php");
            }
        ?>
    </main>
  </div>

  <!-- ============ MODAL: CONFIRMACIÓN ============ -->
  <div class="modal" id="modalConfirm">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div style="width:64px; height:64px; background:var(--rose-100); color:var(--rose-600); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
        <svg viewBox="0 0 24 24" width="32" height="32">
          <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2 style="font-size:1.25rem; font-weight:700; color:var(--text); margin-bottom:8px;">¿Eliminar Movimiento?</h2>
      <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:24px;">
        Esta acción es permanente y no se puede deshacer. ¿Estás seguro de que deseas continuar?
      </p>
      <div style="display:flex; gap:12px; justify-content:center;">
        <button type="button" class="btn-ghost" id="btnConfirmNo" style="flex:1;">Cancelar</button>
        <button type="button" class="btn-accent" id="btnConfirmYes" style="flex:1; background:var(--rose-500); color:white; border-color:var(--rose-500);">Sí, eliminar</button>
      </div>
    </div>
  </div>

  <!-- ============ MODAL: MOVIMIENTOS ============ -->
  <div class="modal" id="modalTx">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon">💸</div>
        <div class="head-text">
          <h2 id="modalTxTitle">Nuevo Movimiento</h2>
          <p>Añade un ingreso, gasto o ahorro</p>
        </div>
        <button class="modal-close" id="btnCloseTx" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form id="formTx" method="POST" action="acciones/procesar_movimiento.php" autocomplete="off">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="txEditId" value="">
        <input type="hidden" name="type" id="txType" value="ingreso">
        <div class="form-group">
          <label>Tipo de movimiento</label>
          <div class="type-selector" id="txTypeGroup">
            <button type="button" class="type-btn is-active" data-val="ingreso"><span
                class="type-dot dot-green"></span>Ingreso</button>
            <button type="button" class="type-btn" data-val="gasto"><span class="type-dot dot-red"></span>Gasto</button>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label for="txAmount">Monto </label>
            <input type="text" class="currency-format" id="txAmount" name="amount" placeholder="0.00" required>
          </div>
          <div class="form-group form-half">
            <label for="txDate">Fecha</label>
            <input type="date" id="txDate" name="date" required>
          </div>
        </div>
        <div class="form-group">
          <label for="txCategory">Categoría</label>
          <select id="txCategory" name="category" required>
            <optgroup label="Ingreso" data-type="ingreso">
              <option value="Salario">💼 Salario</option>
              <option value="Freelance">💻 Freelance</option>
              <option value="Inversiones">📈 Inversiones</option>
              <option value="Ventas">🛒 Ventas</option>
              <option value="Regalos">🎁 Regalos</option>
              <option value="Otros">📎 Otros</option>
            </optgroup>
            <optgroup label="Gasto" data-type="gasto" disabled style="display:none;">
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
          <label for="txDescription">Descripción</label>
          <input type="text" id="txDescription" name="description" placeholder="Ej: Compras del supermercado">
        </div>
        <div class="form-group" id="txPaymentGroup">
          <label for="txPayment">Método de pago</label>
          <select id="txPayment" name="payment_method">
            <option value="efectivo">💵 Efectivo</option>
            <option value="tarjeta">💳 Tarjeta </option>
            <option value="transferencia">🏦 Transferencia</option>
            <option value="otro">📎 Otro</option>
          </select>
          <input type="text" id="txPaymentOther" name="payment_method_other" style="display:none; margin-top:8px;"
            placeholder="Especificar método de pago...">
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" id="btnCancelTx">Cancelar</button>
          <button type="submit" class="btn-accent">Guardar Movimiento</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ MODAL: NUEVA META ============ -->
  <div class="modal" id="modalGoal">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon">🎯</div>
        <div class="head-text">
          <h2 id="modalGoalTitle">Nuevo Objetivo de Ahorro</h2>
          <p>Planifica tu próximo gran logro</p>
        </div>
        <button class="modal-close" id="btnCloseGoal" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form id="formGoal" method="POST" action="acciones/procesar_objetivo.php" autocomplete="off">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_objetivo" id="goalEditId" value="">
        <div class="form-group">
          <label for="goalName">Nombre del Objetivo</label>
          <input type="text" id="goalName" name="nombre" placeholder="Ej: Fondo de emergencia, Viaje, etc." required>
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label for="goalTarget">Meta a lograr</label>
            <input type="text" class="currency-format" id="goalTarget" name="monto_objetivo" placeholder="0.00" required>
          </div>
          <div class="form-group form-half">
            <label for="goalSaved">Total ahorrado</label>
            <input type="text" class="currency-format" id="goalSaved" name="monto_actual" placeholder="0.00">
            <span id="goalError" style="color: var(--red); font-size: 0.75rem; display: none; margin-top: 4px;">El monto no puede ser mayor a la meta.</span>
          </div>
        </div>
        <div class="form-group">
          <label for="goalDeadline">Fecha Límite</label>
          <input type="date" id="goalDeadline" name="fecha_limite" required>
        </div>
        <div class="form-group">
          <label>Icono del Objetivo</label>
          <div class="emoji-grid" id="goalEmojiGrid">
            <button type="button" class="emoji-btn is-active" data-val="🎯">🎯</button>
            <button type="button" class="emoji-btn" data-val="✈️">✈️</button>
            <button type="button" class="emoji-btn" data-val="🚗">🚗</button>
            <button type="button" class="emoji-btn" data-val="💻">💻</button>
            <button type="button" class="emoji-btn" data-val="🏠">🏠</button>
            <button type="button" class="emoji-btn" data-val="🎓">🎓</button>
            <button type="button" class="emoji-btn" data-val="🛡️">🛡️</button>
            <button type="button" class="emoji-btn" data-val="💍">💍</button>
            <button type="button" class="emoji-btn" data-val="🏥">🏥</button>
            <button type="button" class="emoji-btn" data-val="🎮">🎮</button>
          </div>
          <input type="hidden" id="goalEmoji" name="icono" value="🎯">
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" id="btnCancelGoal">Cancelar</button>
          <button type="submit" class="btn-accent">Guardar Meta</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ MODAL: DEPOSITAR ============ -->
  <div class="modal" id="modalDeposit">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon">💰</div>
        <div class="head-text" style="text-align:left;">
          <h2>Añadir Fondos</h2>
          <p id="depositGoalName">Meta: -</p>
        </div>
        <button class="modal-close" id="btnCloseDeposit" type="button" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form id="formDeposit" method="POST" action="acciones/procesar_objetivo.php" autocomplete="off">
        <input type="hidden" name="action" value="deposit">
        <input type="hidden" name="id_objetivo" id="depositGoalId" value="">
        <div class="form-group" style="text-align:left;">
          <label for="depositAmount">Monto a abonar</label>
          <input type="text" class="currency-format" id="depositAmount" name="monto_deposit" placeholder="0.00" required>
          <span id="depositError" style="color: var(--red); font-size: 0.75rem; display: none; margin-top: 4px;">El monto supera la meta restante.</span>
        </div>
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" id="btnCancelDeposit">Cancelar</button>
          <button type="submit" class="btn-accent">Añadir Fondos</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Hidden Form for Delete Goal -->
  <form id="formDeleteGoal" method="POST" action="acciones/procesar_objetivo.php" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_objetivo" id="deleteGoalId" value="">
  </form>

  <!-- ============ MODAL: NUEVO GASTO FIJO ============ -->
  <div class="modal" id="modalExpense">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon">📅</div>
        <div class="head-text">
          <h2 id="modalExpenseTitle">Nuevo Gasto Fijo</h2>
          <p>Organiza tus pagos recurrentes</p>
        </div>
        <button type="button" class="modal-close" id="btnCloseExpense" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form id="formExpense" method="POST" action="acciones/procesar_gasto_fijo.php" autocomplete="off">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id_gasto_fijo" id="expenseId" value="">
        <div class="form-group">
          <label for="expenseName">Nombre del Gasto Fijo</label>
          <input type="text" id="expenseName" name="nombre" placeholder="Ej: Alquiler, Internet, etc." required>
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label for="expenseAmount">Monto A Pagar</label>
            <input type="text" class="currency-format" id="expenseAmount" name="monto" placeholder="0.00" required>
          </div>
          <div class="form-group form-half">
            <label for="expenseDate">Día de vencimiento</label>
            <input type="number" id="expenseDate" name="dia_vencimiento" placeholder="Ej: 15" required min="1" max="31">
          </div>
        </div>

        <div class="form-group">
          <label>Icono y Color del Gasto</label>
          <div class="emoji-grid" id="expenseEmojiGrid" style="margin-bottom:12px;">
            <button type="button" class="emoji-btn is-active" data-val="🏠">🏠</button>
            <button type="button" class="emoji-btn" data-val="⚡">⚡</button>
            <button type="button" class="emoji-btn" data-val="💧">💧</button>
            <button type="button" class="emoji-btn" data-val="🔥">🔥</button>
            <button type="button" class="emoji-btn" data-val="🌐">🌐</button>
            <button type="button" class="emoji-btn" data-val="📱">📱</button>
            <button type="button" class="emoji-btn" data-val="🛒">🛒</button>
            <button type="button" class="emoji-btn" data-val="🏥">🏥</button>
            <button type="button" class="emoji-btn" data-val="🚗">🚗</button>
            <button type="button" class="emoji-btn" data-val="🏋️">🏋️</button>
            <button type="button" class="emoji-btn" data-val="🍿">🍿</button>
            <button type="button" class="emoji-btn" data-val="🎮">🎮</button>
            <button type="button" class="emoji-btn" data-val="📎">📎</button>
          </div>
          <input type="hidden" id="expenseEmoji" name="icono" value="🏠">
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" id="btnCancelExpense">Cancelar</button>
          <button type="submit" class="btn-accent">Guardar Gasto</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ MODAL: ABONO A GASTO FIJO ============ -->
  <div class="modal" id="modalExpenseDeposit">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon" style="color:var(--accent);">💵</div>
        <div class="head-text">
          <h2>Abonar a Gasto Fijo</h2>
          <p id="depositExpenseName">Gasto Fijo</p>
        </div>
        <button type="button" class="modal-close" id="btnCloseExpenseDeposit" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form id="formExpenseDeposit" method="POST" action="acciones/procesar_gasto_fijo.php" autocomplete="off" data-saved="0" data-target="0">
        <input type="hidden" name="action" value="pay_partial">
        <input type="hidden" name="id_gasto_fijo" id="depositExpenseId" value="">
        <div class="form-group">
          <label for="depositExpenseAmount">Monto a abonar</label>
          <input type="text" class="currency-format" id="depositExpenseAmount" name="abono" placeholder="0.00" required>
          <span id="depositExpenseError" style="color: var(--red); font-size: 0.75rem; display: none; margin-top: 4px;">El monto supera lo restante del gasto.</span>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" id="btnCancelExpenseDeposit">Cancelar</button>
          <button type="submit" class="btn-accent">Abonar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Hidden Form for Delete Expense -->
  <form id="formDeleteExpense" method="POST" action="acciones/procesar_gasto_fijo.php" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id_gasto_fijo" id="deleteExpenseId" value="">
  </form>


  <!-- ============ TOAST ============ -->
  <div class="toast-container" id="toastContainer"></div>

  <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>

</html>