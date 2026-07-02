<?php
// Prevent direct access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

if (!isset($db)) {
    require_once 'config/database.php';
    $dbClass = new Database();
    $db = $dbClass->getConnection();
}
$id_usuario = (int)$_SESSION['id_usuario'];

$search = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "WHERE id_usuario = :id_usuario";
$params = [':id_usuario' => $id_usuario];

if (!empty($search)) {
    $where .= " AND nombre LIKE :search";
    $params[':search'] = "%$search%";
}

$query = "SELECT * FROM gastos_fijos $where ORDER BY dia_vencimiento ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$all_gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gastos = [];
$current_month = date('Y-m');
$today_day = (int)date('j');

foreach ($all_gastos as $g) {
    $last_paid_month = $g['fecha_ultimo_pago'] ? date('Y-m', strtotime($g['fecha_ultimo_pago'])) : '';
    $pagado = ($last_paid_month === $current_month) ? (float)$g['monto_pagado_mes'] : 0;
    $is_paid = ($pagado >= $g['monto']);
    $diff = $g['dia_vencimiento'] - $today_day;
    
    $status = 'pending';
    if ($is_paid) {
        $status = 'paid';
    } else if ($diff <= 3) {
        $status = 'urgent';
    }

    if ($status_filter === 'paid' && $status !== 'paid') continue;
    if ($status_filter === 'pending' && $status === 'paid') continue;
    if ($status_filter === 'urgent' && $status !== 'urgent') continue;

    $gastos[] = $g;
}

$total_gastos = 0;
foreach ($gastos as $g) {
    $total_gastos += $g['monto'];
}

$today = date('d/m/Y');
?>
<style>
/* Estilos específicos para Gastos Fijos */
.expense-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:22px;
  box-shadow:var(--shadow-card); transition:transform .2s, box-shadow .2s;
  position:relative; overflow:hidden;
}
.expense-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.12); }
.expense-card.is-paid { border-left: 4px solid var(--green); background: linear-gradient(90deg, rgba(34,197,94,0.08) 0%, var(--surface) 100%); }
.expense-card.is-pending { border-left: 4px solid var(--amber-500); background: linear-gradient(90deg, rgba(245,158,11,0.08) 0%, var(--surface) 100%); }
.expense-card.is-urgent { border-left: 4px solid var(--red); background: linear-gradient(90deg, rgba(239,68,68,0.08) 0%, var(--surface) 100%); }
.expense-emoji {
  width:48px; height:48px; border-radius:14px; flex-shrink:0;
  background:var(--surface-2); display:grid; place-items:center;
  font-size:1.5rem; border:1px solid var(--border);
}
.expense-status-badge {
  position:absolute; top:16px; right:18px;
  font-family:var(--font-mono); font-weight:700; font-size:.75rem;
  padding:4px 10px; border-radius:999px;
}
.status-paid { background: rgba(34,197,94,.12); color: var(--green); }
.status-pending { background: rgba(245,158,11,.12); color: var(--amber-500); }
.status-urgent { background: rgba(239,68,68,.12); color: var(--red); }
.goal-actions { margin-top:16px; display:flex; gap:8px; }
.goal-btn { flex:1; padding:8px 0; border-radius:var(--radius-md); font-size:.8rem; font-weight:600; cursor:pointer; background:transparent; border:1px solid var(--border); color:var(--text); transition:all .2s; }
.goal-btn:hover { background:var(--surface-2); }
.goal-btn.danger { color:var(--red); border-color:var(--red); }
.goal-btn.danger:hover { background:var(--red); color:white; }
</style>

<section class="page-section">

  <!-- Summary -->
  <div class="mini-stats">
    <div class="mini-stat ms-income">
      <span class="ms-label">Fecha</span>
      <strong class="ms-value" id="fixedPaid"><?php echo $today; ?></strong>
    </div>
    <div class="mini-stat ms-balance">
      <span class="ms-label">Gastos Registrados</span>
      <strong class="ms-value" id="fixedPending"><?php echo count($gastos); ?></strong>
    </div>
    <div class="mini-stat ms-expense">
      <span class="ms-label">Total Gastos Fijos</span>
      <strong class="ms-value" id="fixedTotal">$ <?php echo number_format($total_gastos, 2, ',', '.'); ?></strong>
    </div>
  </div>

  <div class="goals-header" style="flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="index.php" style="display:flex; gap:12px; align-items:center; flex:1; min-width: 300px;">
      <input type="hidden" name="mod" value="gastos_fijos">
      <div class="search-wrap" style="position:relative; flex:1;">
        <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
          <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
          <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
        <input type="search" name="q" placeholder="Buscar gastos..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
      </div>
      <select name="status" onchange="this.form.submit()" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface); color: white;">
        <option value="">Todos los estados</option>
        <option value="pending" <?php if($status_filter=='pending') echo 'selected'; ?>>Pendientes / Atrasados</option>
        <option value="paid" <?php if($status_filter=='paid') echo 'selected'; ?>>Pagados</option>
      </select>
    </form>

    <button class="btn-accent" id="btnNewExpense" onclick="openNewExpense()">
      <svg viewBox="0 0 24 24" width="16" height="16">
        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      </svg>
      <span>Nuevo Gasto Fijo</span>
    </button>
  </div>

  <div class="goals-grid" id="expensesGrid">
    <?php 
    foreach ($gastos as $g): 
      $last_paid_month = $g['fecha_ultimo_pago'] ? date('Y-m', strtotime($g['fecha_ultimo_pago'])) : '';
      
      $pagado = ($last_paid_month === $current_month) ? (float)$g['monto_pagado_mes'] : 0;
      $is_paid = ($pagado >= $g['monto']);
      $pct = min(100, round(($pagado / $g['monto']) * 100));
      
      if ($is_paid) {
          $statusClass = 'is-paid';
          $statusBadgeClass = 'status-paid';
          $statusText = '✅ Pagado';
      } else {
          $diff = $g['dia_vencimiento'] - $today_day;
          $statusClass = 'is-pending';
          $statusBadgeClass = 'status-pending';
          $statusText = '➖ Pendiente';
          
          if ($diff < 0) {
              $statusClass = 'is-urgent';
              $statusBadgeClass = 'status-urgent';
              $statusText = '⚠️ Pasado/Atrasado';
          } else if ($diff <= 3) {
              $statusClass = 'is-urgent';
              $statusBadgeClass = 'status-urgent';
              $statusText = '⚠️ Vence pronto';
          }
      }
    ?>
    <div class="expense-card <?php echo $statusClass; ?>">
      <div class="expense-status-badge <?php echo $statusBadgeClass; ?>"><?php echo $statusText; ?></div>
      <div style="display:flex; gap:16px; align-items:center; margin-bottom:16px;">
        <div class="expense-emoji" style="color:var(--text); border-color:var(--border);"><?php echo htmlspecialchars($g['icono']); ?></div>
        <div>
          <h3 style="font-size:1.05rem; font-weight:600; color:var(--text);"><?php echo htmlspecialchars($g['nombre']); ?></h3>
          <p style="font-size:0.85rem; color:var(--text-muted);">Vence el día <?php echo $g['dia_vencimiento']; ?></p>
        </div>
      </div>
      <div class="progress"><span style="width:<?php echo $pct; ?>%"></span></div>
      <div class="goal-numbers" style="align-items: flex-end; margin-top: 12px; margin-bottom: 16px;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
          <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Dinero abonado</span>
          <span class="goal-saved">$ <?php echo number_format($pagado, 2, ',', '.'); ?></span>
        </div>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
          <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Cantidad Total</span>
          <span class="goal-target">$ <?php echo number_format($g['monto'], 2, ',', '.'); ?></span>
        </div>
      </div>
      <div class="goal-actions">
        <?php if (!$is_paid): ?>
        <button class="goal-btn primary" onclick="openExpenseDeposit(<?php echo $g['id_gasto_fijo']; ?>, '<?php echo htmlspecialchars($g['nombre'], ENT_QUOTES); ?>', <?php echo $pagado; ?>, <?php echo $g['monto']; ?>)" style="background:var(--accent); color:white; border:none;">Abonar Gasto</button>
        <?php else: ?>
        <button class="goal-btn" style="color:var(--green); border-color:var(--green); font-weight:600; pointer-events:none;">Pagado este mes</button>
        <?php endif; ?>
        <button class="goal-btn" onclick="openEditExpense(<?php echo $g['id_gasto_fijo']; ?>, '<?php echo htmlspecialchars($g['nombre'], ENT_QUOTES); ?>', <?php echo $g['monto']; ?>, <?php echo $g['dia_vencimiento']; ?>, '<?php echo htmlspecialchars($g['icono'], ENT_QUOTES); ?>')">Editar</button>
        <button class="goal-btn danger" onclick="deleteExpense(<?php echo $g['id_gasto_fijo']; ?>)">Eliminar</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  
  <div class="empty-state" id="expensesEmpty" <?php if(count($gastos) > 0) echo 'style="display:none;"'; ?>>
    <div class="empty-icon">📅</div>
    <p>No has registrado gastos fijos.</p>
    <small>Añade tus gastos mensuales recurrentes para llevar un mejor control.</small>
  </div>
</section>
