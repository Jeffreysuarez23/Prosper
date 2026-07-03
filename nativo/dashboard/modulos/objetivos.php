<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

require_once 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
$id_usuario = (int)$_SESSION['id_usuario'];

$search = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = "WHERE id_usuario = :id_usuario";
$params = [':id_usuario' => $id_usuario];

if (!empty($search)) {
    $where .= " AND nombre LIKE :search";
    $params[':search'] = "%$search%";
}

if ($status_filter === 'completed') {
    $where .= " AND monto_actual >= monto_objetivo";
} else if ($status_filter === 'progress') {
    $where .= " AND monto_actual < monto_objetivo";
}

$query = "SELECT * FROM objetivos $where ORDER BY fecha_creacion DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$objetivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_metas = count($objetivos);
$total_ahorrado = 0;
$total_restante = 0;

foreach ($objetivos as $obj) {
    $total_ahorrado += $obj['monto_actual'];
    $restante = $obj['monto_objetivo'] - $obj['monto_actual'];
    if ($restante > 0) {
        $total_restante += $restante;
    }
}
?>
<section class="page-section">

  <!-- Goals summary -->
  <div class="mini-stats">
    <div class="mini-stat ms-income">
      <span class="ms-label">Total metas</span>
      <strong class="ms-value" style="color: #3b82f6;"><?php echo $total_metas; ?></strong>
    </div>
    <div class="mini-stat ms-savings">
      <span class="ms-label">Total Ahorrado</span>
      <strong class="ms-value" style="color: #22c55e;"><?php echo number_format($total_ahorrado, 2, ',', '.'); ?></strong>
    </div>
    <div class="mini-stat ms-expense">
      <span class="ms-label">Restante Global</span>
      <strong class="ms-value"><?php echo number_format($total_restante, 2, ',', '.'); ?></strong>
    </div>
  </div>

  <div class="goals-header" style="flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="index.php" style="display:flex; gap:12px; align-items:center; flex:1; min-width: 300px;">
      <input type="hidden" name="mod" value="objetivos">
      <div class="search-wrap" style="position:relative; flex:1;">
        <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
          <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
          <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
        <input type="search" name="q" placeholder="Buscar objetivos..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
      </div>
      <select name="status" onchange="this.form.submit()" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface); color: white;">
        <option value="">Todas las metas</option>
        <option value="progress" <?php if($status_filter=='progress') echo 'selected'; ?>>En progreso</option>
        <option value="completed" <?php if($status_filter=='completed') echo 'selected'; ?>>Completadas</option>
      </select>
    </form>

    <button class="btn-accent" onclick="openNewGoal()">
      <svg viewBox="0 0 24 24" width="16" height="16">
        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
      </svg>
      <span>Nuevo Objetivo</span>
    </button>
  </div>
  
  <div class="goals-grid" id="goalsGrid" <?php if($total_metas == 0) echo 'style="display:none;"'; ?>>
    <?php foreach($objetivos as $g): 
        $pct = 0;
        if ($g['monto_objetivo'] > 0) {
            $pct = floor(($g['monto_actual'] / $g['monto_objetivo']) * 100);
            if ($pct > 100) $pct = 100;
        }
    ?>
    <div class="goal-card <?php echo ($pct >= 100) ? 'goal-completed' : ''; ?>" <?php if($pct >= 100) echo 'style="border-color: var(--mint-400); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);"'; ?>>
      <div class="goal-pct" <?php if($pct >= 100) echo 'style="background: var(--mint-400); color: white;"'; ?>><?php echo $pct; ?>%</div>
      <div class="goal-card-top">
        <div class="goal-emoji" <?php if($pct >= 100) echo 'style="background: var(--mint-400); color: white; border-color: var(--mint-500);"'; ?>><?php echo htmlspecialchars($g['icono']); ?></div>
        <div>
          <div class="goal-name" <?php if($pct >= 100) echo 'style="color: var(--mint-400);"'; ?>><?php echo htmlspecialchars($g['nombre']); ?> <?php if($pct >= 100) echo '🎉'; ?></div>
          <div class="goal-deadline">Cumple antes del <?php echo date('d M Y', strtotime($g['fecha_limite'])); ?></div>
        </div>
      </div>
      <div class="progress"><span style="width:<?php echo $pct; ?>%"></span></div>
      <div class="goal-numbers" style="align-items: flex-end;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
          <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Dinero abonado</span>
          <span class="goal-saved">$ <?php echo number_format($g['monto_actual'], 2, ',', '.'); ?></span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px; text-align: right;">
          <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Objetivo a lograr</span>
          <span class="goal-target" style="font-size: .95rem; color: var(--text); font-weight: 600;">$ <?php echo number_format($g['monto_objetivo'], 2, ',', '.'); ?></span>
        </div>
      </div>
      <div class="goal-actions">
        <?php if ($pct < 100): ?>
        <button class="goal-btn primary" onclick="openDeposit(<?php echo $g['id_objetivo']; ?>, '<?php echo htmlspecialchars($g['nombre'], ENT_QUOTES); ?>', <?php echo $g['monto_actual']; ?>, <?php echo $g['monto_objetivo']; ?>)">Abonar</button>
        <?php else: ?>
        <button class="goal-btn" style="color: var(--mint-500); border-color: var(--mint-400); font-weight: 600; flex: 2; pointer-events: none;">¡Meta Completada!</button>
        <?php endif; ?>
        <button class="goal-btn" onclick="openEditGoal(<?php echo $g['id_objetivo']; ?>, '<?php echo htmlspecialchars($g['nombre'], ENT_QUOTES); ?>', <?php echo $g['monto_objetivo']; ?>, <?php echo $g['monto_actual']; ?>, '<?php echo $g['fecha_limite']; ?>', '<?php echo htmlspecialchars($g['icono'], ENT_QUOTES); ?>')">Editar</button>
        <button class="goal-btn danger" onclick="deleteGoal(<?php echo $g['id_objetivo']; ?>)">Eliminar</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  
  <div class="empty-state" id="goalsEmpty" <?php if($total_metas > 0) echo 'style="display:none;"'; ?>>
    <div class="empty-icon">🎯</div>
    <p>Aún no tienes metas de ahorro.</p>
    <small>Crea tu primera meta para comenzar a ahorrar con propósito.</small>
  </div>
</section>