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
?>

  <section class="page-section">

    <!-- Year Navigator -->
    <div class="month-navigator" id="statsYearNav" style="margin-bottom:22px;">
      <button class="mn-arrow" id="statsPrevYear" aria-label="Año anterior">
        <svg viewBox="0 0 24 24" width="20" height="20">
          <path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>
      <div class="mn-center">
        <span class="mn-month" id="statsYearLabel">2026</span>
        <span class="mn-sub">Estadísticas anuales</span>
      </div>
      <button class="mn-arrow" id="statsNextYear" aria-label="Año siguiente">
        <svg viewBox="0 0 24 24" width="20" height="20">
          <path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>
    </div>

    <!-- Period summary -->
    <div class="mini-stats" id="statsSummary" style="grid-template-columns: repeat(2, 1fr);">
      <div class="mini-stat ms-income">
        <span class="ms-label">Ingresos Anuales</span>
        <strong class="ms-value" id="statsYearIncome">0,00</strong>
      </div>
      <div class="mini-stat ms-expense">
        <span class="ms-label">Gastos Anuales</span>
        <strong class="ms-value" id="statsYearExpense">0,00</strong>
      </div>
    </div>

    <div class="grid grid-2">
      <article class="card card-chart card-span">
        <div class="card-head">
          <h2>Ingresos vs Gastos</h2>
          <span class="badge" id="statsYear"></span>
        </div>
        <div class="chart-wrap chart-tall" id="chartIncomeVsExpense"></div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Gastos por Categoría</h2>
        </div>
        <div class="donut-stats-wrap">
          <div class="donut-wrap" id="donutCatWrap">
            <div class="donut" id="donutCat" style="--a:0;--b:0;--c:0;--d:0;--e:0;"></div>
          </div>
          <ul class="legend" id="catLegend"></ul>
        </div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Ingresos por Categoría</h2>
        </div>
        <div class="donut-stats-wrap">
          <div class="donut-wrap" id="donutIncomeWrap">
            <div class="donut" id="donutIncome" style="--a:0;--b:0;--c:0;--d:0;--e:0;"></div>
          </div>
          <ul class="legend" id="incomeLegend"></ul>
        </div>
      </article>
    </div>

  </section>
