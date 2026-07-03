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

// Filtros GET
$search = $_GET['q'] ?? '';
$type_filter = $_GET['type'] ?? '';
$cat_filter = $_GET['cat'] ?? '';
$month_filter = $_GET['month'] ?? date('Y-m');

$where = "WHERE id_usuario = :id_usuario";
$params = [':id_usuario' => $id_usuario];

if (!empty($search)) {
    $where .= " AND (descripcion LIKE :search OR categoria LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($type_filter)) {
    $where .= " AND tipo = :tipo";
    $params[':tipo'] = $type_filter;
}
if (!empty($cat_filter)) {
    $where .= " AND categoria = :cat";
    $params[':cat'] = $cat_filter;
}
if (!empty($month_filter) && $month_filter !== 'all') {
    $where .= " AND DATE_FORMAT(fecha, '%Y-%m') = :month";
    $params[':month'] = $month_filter;
}

// Query for stats
$statsQuery = "SELECT tipo, SUM(monto) as sum_monto FROM movimientos $where GROUP BY tipo";
$stmtStats = $db->prepare($statsQuery);
$stmtStats->execute($params);
$stats = $stmtStats->fetchAll(PDO::FETCH_KEY_PAIR);

$income = $stats['ingreso'] ?? 0;
$expense = $stats['gasto'] ?? 0;
$balance = $income - $expense;

$countQuery = "SELECT COUNT(*) FROM movimientos $where";
$stmtCount = $db->prepare($countQuery);
$stmtCount->execute($params);
$total_records = $stmtCount->fetchColumn();

$limit = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_records / $limit);

$query = "SELECT id_movimiento as id, tipo, monto, fecha, categoria, descripcion, metodo_pago 
          FROM movimientos $where ORDER BY fecha DESC, id_movimiento DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

$CAT_ICONS = [
    'Salario'=>'💼','Freelance'=>'💻','Inversiones'=>'📈','Ventas'=>'🛒','Regalos'=>'🎁',
    'Comida'=>'🍔','Transporte'=>'🚗','Vivienda'=>'🏠','Servicios'=>'⚡','Salud'=>'🏥','Ocio'=>'🎮','Educación'=>'📚','Ropa'=>'👕',
    'Fondo de emergencia'=>'🛡️','Inversión'=>'📊','Meta específica'=>'🎯','Ahorro general'=>'💰',
    'Entre cuentas'=>'🔄','A terceros'=>'👤','Pago de deuda'=>'💳','Otros'=>'📎'
];

$monthNames = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$displayMonth = ($month_filter && $month_filter !== 'all') ? $monthNames[substr($month_filter, 5, 2)] . ' ' . substr($month_filter, 0, 4) : 'Todos los meses';

// Calcular mes anterior y siguiente para navegación simple
$prevMonth = '';
$nextMonth = '';
if ($month_filter !== 'all') {
    $time = strtotime($month_filter . '-01');
    $prevMonth = date('Y-m', strtotime('-1 month', $time));
    $nextMonth = date('Y-m', strtotime('+1 month', $time));
}
?>
<!-- ============ MOVIMIENTOS CONTENT ============ -->
<section class="page-section">

  <!-- Month Navigator -->
  <div class="month-navigator">
    <?php if ($month_filter !== 'all'): ?>
      <a href="?mod=movimientos&month=<?php echo $prevMonth; ?>&type=<?php echo urlencode($type_filter); ?>&cat=<?php echo urlencode($cat_filter); ?>&q=<?php echo urlencode($search); ?>" class="mn-arrow" aria-label="Mes anterior" style="display:flex;align-items:center;justify-content:center;text-decoration:none;color:inherit;">
        <svg viewBox="0 0 24 24" width="20" height="20">
          <path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    <?php endif; ?>
    <div class="mn-center">
      <span class="mn-month"><?php echo $displayMonth; ?></span>
      <span class="mn-sub"><?php echo ($month_filter === 'all') ? 'Historial completo' : 'Todos los movimientos del mes'; ?></span>
    </div>
    <?php if ($month_filter !== 'all'): ?>
      <a href="?mod=movimientos&month=<?php echo $nextMonth; ?>&type=<?php echo urlencode($type_filter); ?>&cat=<?php echo urlencode($cat_filter); ?>&q=<?php echo urlencode($search); ?>" class="mn-arrow" aria-label="Mes siguiente" style="display:flex;align-items:center;justify-content:center;text-decoration:none;color:inherit;">
        <svg viewBox="0 0 24 24" width="20" height="20">
          <path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
    <?php endif; ?>
    <a href="?mod=movimientos&month=all&type=<?php echo urlencode($type_filter); ?>&cat=<?php echo urlencode($cat_filter); ?>&q=<?php echo urlencode($search); ?>" class="mn-all-btn" style="text-decoration:none;color:inherit;padding:4px 8px;">Todos</a>
  </div>

  <!-- Summary mini-cards -->
  <div class="mini-stats">
    <div class="mini-stat ms-balance">
      <span class="ms-label">Balance del mes</span>
      <strong class="ms-value"><?php echo number_format($balance, 2, ',', '.'); ?></strong>
    </div>
    <div class="mini-stat ms-income">
      <span class="ms-label">Ingresos</span>
      <strong class="ms-value"><?php echo number_format($income, 2, ',', '.'); ?></strong>
    </div>
    <div class="mini-stat ms-expense">
      <span class="ms-label">Gastos</span>
      <strong class="ms-value"><?php echo number_format($expense, 2, ',', '.'); ?></strong>
    </div>
  </div>

  <!-- Filters -->
  <form method="GET" action="index.php" class="filters-bar" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:24px;">
    <input type="hidden" name="mod" value="movimientos">
    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
    
    <div class="search-wrap" style="position:relative; flex:1; min-width:200px;">
      <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
        <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
        <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
      </svg>
      <input type="search" name="q" placeholder="Buscar movimientos..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
    </div>
    <select name="type" onchange="this.form.submit()" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px;">
      <option value="">Todos los tipos</option>
      <option value="ingreso" <?php if($type_filter=='ingreso') echo 'selected'; ?>>Ingresos</option>
      <option value="gasto" <?php if($type_filter=='gasto') echo 'selected'; ?>>Gastos</option>
    </select>
    <select name="cat" onchange="this.form.submit()" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px;">
      <option value="">Todas las categorías</option>
      <optgroup label="Ingresos">
        <option value="Salario" <?php if($cat_filter=='Salario') echo 'selected'; ?>>💼 Salario</option>
        <option value="Freelance" <?php if($cat_filter=='Freelance') echo 'selected'; ?>>💻 Freelance</option>
        <option value="Inversiones" <?php if($cat_filter=='Inversiones') echo 'selected'; ?>>📈 Inversiones</option>
        <option value="Ventas" <?php if($cat_filter=='Ventas') echo 'selected'; ?>>🛒 Ventas</option>
        <option value="Regalos" <?php if($cat_filter=='Regalos') echo 'selected'; ?>>🎁 Regalos</option>
      </optgroup>
      <optgroup label="Gastos">
        <option value="Comida" <?php if($cat_filter=='Comida') echo 'selected'; ?>>🍔 Comida</option>
        <option value="Transporte" <?php if($cat_filter=='Transporte') echo 'selected'; ?>>🚗 Transporte</option>
        <option value="Vivienda" <?php if($cat_filter=='Vivienda') echo 'selected'; ?>>🏠 Vivienda</option>
        <option value="Servicios" <?php if($cat_filter=='Servicios') echo 'selected'; ?>>⚡ Servicios</option>
        <option value="Salud" <?php if($cat_filter=='Salud') echo 'selected'; ?>>🏥 Salud</option>
        <option value="Ocio" <?php if($cat_filter=='Ocio') echo 'selected'; ?>>🎮 Ocio</option>
        <option value="Educación" <?php if($cat_filter=='Educación') echo 'selected'; ?>>📚 Educación</option>
        <option value="Ropa" <?php if($cat_filter=='Ropa') echo 'selected'; ?>>👕 Ropa</option>
      </optgroup>
      </optgroup>
    </select>
    <a href="?mod=movimientos" class="btn-outline btn-sm" style="height:40px; display:flex; align-items:center; justify-content:center; text-decoration:none; color:inherit;">Limpiar</a>
  </form>

  <!-- Transaction list -->
  <article class="card">
    <div class="card-head" style="justify-content:center;">
      <h2>Historial de Movimientos</h2>
      <span class="badge"><?php echo count($movimientos); ?> registros</span>
    </div>
    
    <?php if (count($movimientos) > 0): ?>
      <div class="table-responsive">
        <table class="tx-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Descripción</th>
              <th>Categoría</th>
              <th>Monto</th>
              <th style="text-align:right;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($movimientos as $tx): 
              $icon = $CAT_ICONS[$tx['categoria']] ?? '📎';
              $prefix = $tx['tipo'] === 'ingreso' ? '+' : ($tx['tipo'] === 'gasto' ? '-' : '');
              $dateObj = new DateTime($tx['fecha']);
              $dateStr = $dateObj->format('d') . ' ' . $monthNames[$dateObj->format('m')] . ' ' . $dateObj->format('Y');
            ?>
              <tr>
                <td style="white-space:nowrap; color:var(--text-muted); font-size:0.85rem;"><?php echo $dateStr; ?></td>
                <td>
                  <div class="tx-item-desc" style="font-size:0.95rem;"><?php echo htmlspecialchars($tx['descripcion']); ?></div>
                </td>
                <td>
                  <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:0.85rem;">
                    <div class="tx-item-icon" style="width:28px; height:28px; font-size:0.9rem;"><?php echo $icon; ?></div>
                    <?php echo htmlspecialchars($tx['categoria']); ?>
                  </div>
                </td>
                <td class="tx-item-amount is-<?php echo $tx['tipo']; ?>" style="text-align:left;">
                  <?php echo $prefix . number_format($tx['monto'], 2, ',', '.'); ?>
                </td>
                <td style="text-align:right;">
                  <div class="tx-item-actions" style="justify-content:flex-end;">
                    <button class="tx-action edit" onclick="window._openEditTx(<?php echo htmlspecialchars(json_encode($tx)); ?>)" title="Editar">
                      <svg viewBox="0 0 24 24" width="14" height="14"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 0 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <form method="POST" action="acciones/procesar_movimiento.php" style="display:inline-block;" onsubmit="window.confirmDelete(event, this);">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo $tx['id']; ?>">
                      <button type="submit" class="tx-action del" title="Eliminar" style="background:none;border:none;cursor:pointer;">
                        <svg viewBox="0 0 24 24" width="14" height="14"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      
      <?php if ($total_pages > 1): ?>
      <div style="display:flex; justify-content:center; gap:8px; margin-top:20px; margin-bottom:12px;">
        <?php 
          $qsParams = $_GET;
          unset($qsParams['page']);
          $baseQs = http_build_query($qsParams);
          if (!empty($baseQs)) $baseQs .= '&';
          
          if ($page > 1): 
        ?>
            <a href="?<?php echo $baseQs; ?>page=<?php echo $page - 1; ?>" class="btn-outline btn-sm" style="height:36px; display:flex; align-items:center; padding:0 12px; text-decoration:none;">Anterior</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?<?php echo $baseQs; ?>page=<?php echo $i; ?>" class="btn-outline btn-sm" style="height:36px; width:36px; display:flex; align-items:center; justify-content:center; text-decoration:none; <?php echo ($i === $page) ? 'background:var(--accent); color:white; border-color:var(--accent);' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo $baseQs; ?>page=<?php echo $page + 1; ?>" class="btn-outline btn-sm" style="height:36px; display:flex; align-items:center; padding:0 12px; text-decoration:none;">Siguiente</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📊</div>
        <p>No se encontraron movimientos.</p>
        <small>Usa el botón "Nuevo" para agregar uno.</small>
      </div>
    <?php endif; ?>
  </article>

</section>
<script>
  window._openEditTx = function(tx) {
    const modal = document.getElementById('modalTx');
    if(modal) {
      document.getElementById('txEditId').value = tx.id;
      document.getElementById('modalTxTitle').textContent = 'Editar Movimiento';
      
      // Simular click en boton de tipo
      const btn = document.querySelector('.type-btn[data-val="'+tx.tipo+'"]');
      if(btn) btn.click();
      
      let montoStr = tx.monto.toString().replace('.', ',');
      let parts = montoStr.split(',');
      if (parts[0]) {
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }
      document.getElementById('txAmount').value = parts.join(',');
      document.getElementById('txDate').value = tx.fecha.substring(0, 10);
      
      setTimeout(() => {
        document.getElementById('txCategory').value = tx.categoria;
      }, 50);

      document.getElementById('txDescription').value = tx.descripcion;
      
      const standard = ['efectivo', 'tarjeta', 'transferencia'];
      if (standard.includes(tx.metodo_pago)) {
        document.getElementById('txPayment').value = tx.metodo_pago;
        document.getElementById('txPaymentOther').style.display = 'none';
      } else {
        document.getElementById('txPayment').value = 'otro';
        document.getElementById('txPaymentOther').value = tx.metodo_pago;
        document.getElementById('txPaymentOther').style.display = 'block';
      }
      
      modal.classList.add('is-active');
    }
  }
  
  window.confirmDelete = function(e, form) {
    e.preventDefault();
    const modal = document.getElementById('modalConfirm');
    if(modal) {
      const titleEl = modal.querySelector('h2');
      if (titleEl) titleEl.textContent = '¿Eliminar Movimiento?';
      modal.classList.add('is-active');
      const btnYes = document.getElementById('btnConfirmYes');
      const btnNo = document.getElementById('btnConfirmNo');
      
      const onYes = () => {
        cleanup();
        form.submit();
      };
      const onNo = () => {
        cleanup();
      };
      
      const cleanup = () => {
        modal.classList.remove('is-active');
        btnYes.removeEventListener('click', onYes);
        btnNo.removeEventListener('click', onNo);
      };
      
      btnYes.addEventListener('click', onYes);
      btnNo.addEventListener('click', onNo);
    } else {
      // Fallback si no encuentra el modal
      if(confirm("¿Estás seguro de eliminar este movimiento?")) form.submit();
    }
  }

<?php if (isset($_GET['msg'])): ?>
  document.addEventListener('DOMContentLoaded', () => {
    // Retardo leve para asegurar que la animación del DOM esté lista
    setTimeout(() => {
      <?php if ($_GET['msg'] === 'created'): ?>
        if (typeof window.toast === 'function') window.toast('Movimiento guardado con éxito');
      <?php elseif ($_GET['msg'] === 'edited'): ?>
        if (typeof window.toast === 'function') window.toast('Movimiento editado correctamente');
      <?php elseif ($_GET['msg'] === 'deleted'): ?>
        if (typeof window.toast === 'function') window.toast('Movimiento eliminado correctamente');
      <?php endif; ?>
      
      // Limpiar la URL para evitar que se repita la alerta al refrescar
      if (window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('msg');
        window.history.replaceState({path: url.href}, '', url.href);
      }
    }, 100);
  });
<?php endif; ?>
</script>