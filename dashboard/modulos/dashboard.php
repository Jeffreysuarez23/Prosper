<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$id_usuario = (int)$_SESSION['id_usuario'];

// 1. Obtener totales de todas las fechas (Ingresos, Gastos, Ahorros)
$query = "SELECT tipo, SUM(monto) as total FROM movimientos WHERE id_usuario = :id_usuario GROUP BY tipo";
$stmt = $db->prepare($query);
$stmt->bindParam(":id_usuario", $id_usuario);
$stmt->execute();

$ingresos = 0;
$gastos = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['tipo'] == 'ingreso') $ingresos = $row['total'];
    if ($row['tipo'] == 'gasto') $gastos = $row['total'];
}
$balance = $ingresos - $gastos;

// 2. Obtener los últimos 5 movimientos
$queryTx = "SELECT * FROM movimientos WHERE id_usuario = :id_usuario ORDER BY fecha DESC, id_movimiento DESC LIMIT 5";
$stmtTx = $db->prepare($queryTx);
$stmtTx->bindParam(":id_usuario", $id_usuario);
$stmtTx->execute();
$recentTx = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

$CAT_ICONS = [
    'Salario'=>'💰', 'Ventas'=>'📈', 'Regalos'=>'🎁',
    'Vivienda'=>'🏠', 'Comida'=>'🍔', 'Transporte'=>'🚗', 'Ocio'=>'🍿', 'Servicios'=>'⚡', 'Ropa'=>'👕',
    'Meta específica'=>'🎯', 'Fondo de emergencia'=>'🛡️'
];
$MONTHS_SHORT = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
?>
<section class="page-section">
    <div class="stat-cards" id="statCards">
        <div class="stat-card sc-balance">
            <div class="sc-icon"><svg viewBox="0 0 24 24" width="22" height="22">
                    <path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7Z" fill="none"
                        stroke="currentColor" stroke-width="1.6" />
                    <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.6" />
                </svg></div>
            <span class="sc-label">Balance Disponible</span>
            <strong class="sc-value" id="statBalance">$<?php echo number_format($balance, 2, ',', '.'); ?></strong>
        </div>
        <div class="stat-card sc-income">
            <div class="sc-icon"><svg viewBox="0 0 24 24" width="22" height="22">
                    <path d="M12 19V5M5 12l7-7 7 7" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg></div>
            <span class="sc-label">Ingresos Totales</span>
            <strong class="sc-value" id="statIncome">+$<?php echo number_format($ingresos, 2, ',', '.'); ?></strong>
        </div>
        <div class="stat-card sc-expense">
            <div class="sc-icon"><svg viewBox="0 0 24 24" width="22" height="22">
                    <path d="M12 5v14M5 12l7 7 7-7" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg></div>
            <span class="sc-label">Gastos Totales</span>
            <strong class="sc-value" id="statExpense">-$<?php echo number_format($gastos, 2, ',', '.'); ?></strong>
        </div>
    </div>

    <div class="grid grid-2">
        <article class="card card-chart">
            <div class="card-head">
                <h2>Ingresos vs Gastos (6 meses)</h2>
            </div>
            <div class="chart-wrap" id="chartDashTrend"></div>
        </article>
        <div class="card card-recent">
            <div class="card-head">
                <h2>Movimientos Recientes</h2>
                <a href="index.php?mod=movimientos" class="btn-link">Ver todos</a>
            </div>
            <?php if (count($recentTx) > 0): ?>
            <ul class="tx-list" id="recentTxList">
                <?php foreach ($recentTx as $tx): 
                    $icon = $CAT_ICONS[$tx['categoria']] ?? '📎';
                    $prefix = $tx['tipo'] === 'ingreso' ? '+' : ($tx['tipo'] === 'gasto' ? '-' : '');
                    $dateParts = explode('-', $tx['fecha']);
                    $dateStr = count($dateParts) == 3 ? $dateParts[2] . ' ' . $MONTHS_SHORT[(int)$dateParts[1]-1] . ' ' . $dateParts[0] : $tx['fecha'];
                ?>
                <li class="tx-item">
                    <div class="tx-item-icon"><?php echo $icon; ?></div>
                    <div class="tx-item-info">
                        <div class="tx-item-desc"><?php echo htmlspecialchars($tx['descripcion']); ?></div>
                        <div class="tx-item-meta"><?php echo htmlspecialchars($tx['categoria']); ?> · <?php echo $dateStr; ?></div>
                    </div>
                    <div class="tx-item-amount is-<?php echo $tx['tipo']; ?>">
                        <?php echo $prefix . number_format($tx['monto'], 2, ',', '.'); ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state" id="recentEmpty">
                <div class="empty-icon">💸</div>
                <p>Aún no hay movimientos.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>