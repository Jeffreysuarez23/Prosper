(() => {
  'use strict';

  /* ==========================================================
     CONFIG & CONSTANTS
     ========================================================== */
  const STORAGE_TX   = 'prosper-transactions';
  const STORAGE_GOAL = 'prosper-goals';
  const THEME_KEY    = 'prosper-theme';
  const PAGE         = document.body.dataset.page; // 'dashboard' | 'movimientos' | 'estadisticas' | 'objetivos'

  const MONTHS_SHORT = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  
  const CAT_ICONS = {
    'Salario': '💰', 'Ventas': '📈', 'Regalos': '🎁',
    'Vivienda': '🏠', 'Comida': '🍔', 'Transporte': '🚗', 'Ocio': '🍿', 'Servicios': '⚡', 'Ropa': '👕',
    'Meta específica': '🎯', 'Fondo de emergencia': '🛡️'
  };

    /* ==========================================================
     DATA LAYER (API Fetch)
     ========================================================== */
  let cachedTx = [];
  let cachedGoals = [];
  let cachedExpenses = [];

  function loadTx() { return cachedTx; }
  async function saveTx(singleTx) { 
    await fetch('api/movimientos.php', { method: 'POST', body: JSON.stringify(singleTx) }); 
    await syncData();
  }
  async function deleteTxAPI(id) {
    await fetch('api/movimientos.php', { method: 'DELETE', body: JSON.stringify({id}) });
    await syncData();
  }
  
  function loadGoals() { return cachedGoals; }
  async function saveGoal(singleGoal) { 
    await fetch('api/objetivos.php', { method: 'POST', body: JSON.stringify(singleGoal) });
    await syncData();
  }
  async function deleteGoalAPI(id) {
    await fetch('api/objetivos.php', { method: 'DELETE', body: JSON.stringify({id}) });
    await syncData();
  }

  function loadExpenses() { return cachedExpenses; }
  async function saveExpense(singleExp) {
    await fetch('api/gastos_fijos.php', { method: 'POST', body: JSON.stringify(singleExp) });
    await syncData();
  }
  async function deleteExpenseAPI(id) {
    await fetch('api/gastos_fijos.php', { method: 'DELETE', body: JSON.stringify({id}) });
    await syncData();
  }

  async function syncData() {
    try {
      const [txRes, gRes, eRes] = await Promise.all([
        fetch('api/movimientos.php'),
        fetch('api/objetivos.php'),
        fetch('api/gastos_fijos.php')
      ]);
      cachedTx = await txRes.json();
      cachedGoals = await gRes.json();
      cachedExpenses = await eRes.json();
    } catch(e) { console.error('API Error', e); }
  }

  function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2,8); }

  /* ==========================================================
     UI HELPERS
     ========================================================== */
  const $ = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => [...ctx.querySelectorAll(sel)];

  function fmt(n) {
    return 'COP ' + Math.abs(n).toLocaleString('es-CO', { minimumFractionDigits:2, maximumFractionDigits:2 });
  }

  // Helper to parse currency string identically to PHP
  function parseCurrency(str) {
    if (!str) return 0;
    // Remove dots, then replace comma with dot
    let c = str.replace(/\./g, '').replace(/,/g, '.');
    c = c.replace(/[^\d.-]/g, '');
    return parseFloat(c) || 0;
  }

  function fmtDate(iso) {
    const d = new Date(iso + 'T00:00:00');
    return `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
  }

  function todayISO() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  }

  function toast(msg, type='success') {
    const c = $('#toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('toast-exit'); setTimeout(() => t.remove(), 300); }, 2800);
  }
  window.toast = toast;

  /* ==========================================================
     1. THEME
     ========================================================== */
  const root = document.documentElement;
  const themeCircles = $$('.theme-circle');

  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    if (themeCircles && themeCircles.length) {
      themeCircles.forEach(btn => btn.classList.toggle('is-active', btn.dataset.setTheme === theme));
    }
  }

  const savedTheme = localStorage.getItem(THEME_KEY) ||
    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  applyTheme(savedTheme);

  if (themeCircles && themeCircles.length) {
    themeCircles.forEach(btn => {
      btn.addEventListener('click', () => {
        const next = btn.dataset.setTheme;
        applyTheme(next);
        localStorage.setItem(THEME_KEY, next);
        requestAnimationFrame(renderPageCharts);
      });
    });
  }

  /* ==========================================================
     2. MOBILE SIDEBAR
     ========================================================== */
  const sidebar = $('#sidebar');
  const menuToggle = $('#menuToggle');
  let overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  document.body.appendChild(overlay);

  function closeSidebar() { if(sidebar) sidebar.classList.remove('is-open'); overlay.classList.remove('is-active'); }
  if (menuToggle) menuToggle.addEventListener('click', () => { sidebar.classList.add('is-open'); overlay.classList.add('is-active'); });
  overlay.addEventListener('click', closeSidebar);

  /* ==========================================================
     PAGE: DASHBOARD
     ========================================================== */
  function renderDashboard() {
    const c = $('#chartDashTrend');
    if (!c || !c.clientWidth) return;
    
    const now = new Date();
    const months = [];
    for (let i=5; i>=0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth()-i, 1);
      months.push({ m: d.getMonth(), y: d.getFullYear(), label: MONTHS_SHORT[d.getMonth()] });
    }
    
    // Mock data for the last 6 months
    const inc = [3200000, 3500000, 3100000, 4000000, 3800000, 4200000];
    const exp = [2100000, 1800000, 2400000, 1900000, 2200000, 2500000];
    
    drawGroupedBarChart(c, {
      data1: inc,
      data2: exp,
      labels: months.map(m => m.label),
      color1: getCSS('--accent') || '#31bd93',
      color2: getCSS('--rose-400') || '#e79cb0',
      legend1: 'Ingresos',
      legend2: 'Gastos'
    });
  }

  /* ==========================================================
     3. BALANCE (shown on all pages)
     ========================================================== */
  function updateHeaderBalance() {
    // Left empty for static HTML mockup
  }

  /* ==========================================================
     4. CHART ENGINE (Pure SVG)
     ========================================================== */
  let chartTooltip = $('#chartTooltip');
  if (!chartTooltip) {
    chartTooltip = document.createElement('div');
    chartTooltip.id = 'chartTooltip';
    chartTooltip.style.cssText = 'position:fixed; background:var(--surface); border:1px solid var(--border); padding:8px 12px; border-radius:8px; font-size:0.85rem; box-shadow:0 4px 12px rgba(0,0,0,0.25); pointer-events:none; opacity:0; transition:opacity 0.2s; z-index:9999; color:var(--text); font-weight:500; transform:translate(-50%, -120%); white-space:nowrap; text-align:center;';
    document.body.appendChild(chartTooltip);
  }
  
  function showChartTooltip(e, html) {
    chartTooltip.innerHTML = html;
    chartTooltip.style.opacity = 1;
    chartTooltip.style.left = e.clientX + 'px';
    chartTooltip.style.top = e.clientY + 'px';
  }
  
  function hideChartTooltip() {
    chartTooltip.style.opacity = 0;
  }

  const svgNS = 'http://www.w3.org/2000/svg';
  const svgEl = (tag, attrs={}) => {
    const n = document.createElementNS(svgNS, tag);
    Object.entries(attrs).forEach(([k,v]) => n.setAttribute(k,v));
    return n;
  };

  function smoothPath(pts) {
    if (pts.length < 2) return '';
    let d = `M ${pts[0][0]} ${pts[0][1]}`;
    for (let i = 0; i < pts.length - 1; i++) {
      const p0 = pts[i===0?i:i-1], p1 = pts[i], p2 = pts[i+1], p3 = pts[i+2<pts.length?i+2:i+1];
      d += ` C ${p1[0]+(p2[0]-p0[0])/6} ${p1[1]+(p2[1]-p0[1])/6}, ${p2[0]-(p3[0]-p1[0])/6} ${p2[1]-(p3[1]-p1[1])/6}, ${p2[0]} ${p2[1]}`;
    }
    return d;
  }

  function getCSS(v) { return getComputedStyle(root).getPropertyValue(v).trim(); }

  function drawGroupedBarChart(container, { data1, data2, labels, color1, color2, legend1, legend2 }) {
    const w = container.clientWidth || 400, h = container.clientHeight || 180;
    if (!w) return;
    const pT=14, pB=28, pX=10;
    const max = Math.max(...data1,...data2,1)*1.15;
    const gW = (w-pX*2)/labels.length, bW = gW*0.28, gap = gW*0.06;
    const svg = svgEl('svg',{viewBox:`0 0 ${w} ${h}`,preserveAspectRatio:'none'});

    const gg = svgEl('g',{class:'chart-grid'});
    [0.25,0.5,0.75,1].forEach(f=>{const y=pT+f*(h-pT-pB);gg.appendChild(svgEl('line',{x1:pX,x2:w-pX,y1:y,y2:y,'stroke-width':1,'stroke-dasharray':'3 4',opacity:.5}));});
    svg.appendChild(gg);

    labels.forEach((lab,i)=>{
      const cx=pX+i*gW+gW/2,x1=cx-bW-gap/2,x2=cx+gap/2;
      
      const bh1_raw = (data1[i]/max)*(h-pT-pB);
      const bh1 = data1[i] > 0 ? Math.max(bh1_raw, 4) : 0;
      const r1 = svgEl('rect',{x:x1,y:h-pB-bh1,width:bW,height:bh1,rx:4,fill:color1,style:'cursor:pointer;transition:opacity 0.15s;'});
      r1.onmouseenter = (e) => { r1.style.opacity=0.8; showChartTooltip(e, `${lab} - ${legend1}<br><span style="color:${color1}">${fmt(data1[i])}</span>`); };
      r1.onmousemove = (e) => { chartTooltip.style.left = e.clientX+'px'; chartTooltip.style.top = e.clientY+'px'; };
      r1.onmouseleave = () => { r1.style.opacity=1; hideChartTooltip(); };
      svg.appendChild(r1);

      const bh2_raw = (data2[i]/max)*(h-pT-pB);
      const bh2 = data2[i] > 0 ? Math.max(bh2_raw, 4) : 0;
      const r2 = svgEl('rect',{x:x2,y:h-pB-bh2,width:bW,height:bh2,rx:4,fill:color2,style:'cursor:pointer;transition:opacity 0.15s;'});
      r2.onmouseenter = (e) => { r2.style.opacity=0.8; showChartTooltip(e, `${lab} - ${legend2}<br><span style="color:${color2}">${fmt(data2[i])}</span>`); };
      r2.onmousemove = (e) => { chartTooltip.style.left = e.clientX+'px'; chartTooltip.style.top = e.clientY+'px'; };
      r2.onmouseleave = () => { r2.style.opacity=1; hideChartTooltip(); };
      svg.appendChild(r2);
    });

    const lg = svgEl('g',{class:'chart-axis'});
    labels.forEach((lab,i)=>{const t=svgEl('text',{x:pX+i*gW+gW/2,y:h-6,'text-anchor':'middle'});t.textContent=lab;lg.appendChild(t);});
    svg.appendChild(lg);

    const ly=8,mc=getCSS('--text-muted');
    svg.appendChild(svgEl('rect',{x:w-150,y:ly-6,width:8,height:8,rx:2,fill:color1}));
    const t1=svgEl('text',{x:w-138,y:ly+2,'font-size':'9',fill:mc});t1.textContent=legend1;svg.appendChild(t1);
    svg.appendChild(svgEl('rect',{x:w-80,y:ly-6,width:8,height:8,rx:2,fill:color2}));
    const t2=svgEl('text',{x:w-68,y:ly+2,'font-size':'9',fill:mc});t2.textContent=legend2;svg.appendChild(t2);

    container.replaceChildren(svg);
  }

  function drawAreaChart(container, { values, labels, color }) {
    const w = container.clientWidth||400, h = container.clientHeight||180;
    if (!w) return;
    const pT=14,pB=22,pX=8;
    const max = Math.max(...values,1)*1.15;
    const stepX = values.length>1?(w-pX*2)/(values.length-1):0;
    const pts = values.map((v,i)=>[pX+i*stepX, pT+(1-v/max)*(h-pT-pB)]);

    const svg = svgEl('svg',{viewBox:`0 0 ${w} ${h}`,preserveAspectRatio:'none'});
    const gg = svgEl('g',{class:'chart-grid'});
    [0.25,0.5,0.75,1].forEach(f=>{const y=pT+f*(h-pT-pB);gg.appendChild(svgEl('line',{x1:pX,x2:w-pX,y1:y,y2:y,'stroke-width':1,'stroke-dasharray':'3 4',opacity:.5}));});
    svg.appendChild(gg);

    const gid='ag'+uid();
    const defs=svgEl('defs'),grad=svgEl('linearGradient',{id:gid,x1:0,y1:0,x2:0,y2:1});
    grad.appendChild(svgEl('stop',{offset:'0%','stop-color':color,'stop-opacity':.3}));
    grad.appendChild(svgEl('stop',{offset:'100%','stop-color':color,'stop-opacity':0}));
    defs.appendChild(grad);svg.appendChild(defs);

    if(pts.length>1){
      const pathD=smoothPath(pts);
      svg.appendChild(svgEl('path',{d:`${pathD} L ${pts[pts.length-1][0]} ${h-pB} L ${pts[0][0]} ${h-pB} Z`,fill:`url(#${gid})`, style:'pointer-events:none;'}));
      svg.appendChild(svgEl('path',{d:pathD,fill:'none',stroke:color,'stroke-width':'2.4','stroke-linecap':'round','stroke-linejoin':'round', style:'pointer-events:none;'}));
      
      const hoverLine = svgEl('line',{y1:pT,y2:h-pB,stroke:getCSS('--border'),'stroke-width':1,'stroke-dasharray':'4 4',style:'opacity:0;pointer-events:none;transition:opacity 0.1s;'});
      svg.appendChild(hoverLine);
      const hoverCirc = svgEl('circle',{r:5,fill:getCSS('--surface'),stroke:color,'stroke-width':'2',style:'opacity:0;pointer-events:none;transition:opacity 0.1s;'});
      svg.appendChild(hoverCirc);
      
      const hitArea = svgEl('rect',{x:0,y:0,width:w,height:h,fill:'transparent',style:'cursor:crosshair;'});
      hitArea.onmousemove = (e) => {
        const rect = svg.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const scaleX = w / rect.width;
        const svgX = mouseX * scaleX;
        
        let closestIdx = 0, minDiff = Infinity;
        pts.forEach((pt, i) => {
          const diff = Math.abs(pt[0] - svgX);
          if (diff < minDiff) { minDiff = diff; closestIdx = i; }
        });
        
        const pt = pts[closestIdx];
        hoverLine.setAttribute('x1', pt[0]); hoverLine.setAttribute('x2', pt[0]); hoverLine.style.opacity = 1;
        hoverCirc.setAttribute('cx', pt[0]); hoverCirc.setAttribute('cy', pt[1]); hoverCirc.style.opacity = 1;
        
        showChartTooltip(e, `${labels[closestIdx]}<br><span style="color:${color}">${fmt(values[closestIdx])}</span>`);
      };
      hitArea.onmouseleave = () => {
        hoverLine.style.opacity = 0; hoverCirc.style.opacity = 0;
        hideChartTooltip();
      };
      svg.appendChild(hitArea);
      
      const lastCirc = svgEl('circle',{cx:pts[pts.length-1][0],cy:pts[pts.length-1][1],r:4.5,fill:getCSS('--surface'),stroke:color,'stroke-width':'2.2',style:'pointer-events:none;'});
      svg.appendChild(lastCirc);
    }

    const lg=svgEl('g',{class:'chart-axis'});
    const skip=labels.length>8?2:1;
    labels.forEach((lab,i)=>{if(i%skip!==0&&i!==labels.length-1)return;const t=svgEl('text',{x:pts[i]?pts[i][0]:0,y:h-4,'text-anchor':'middle'});t.textContent=lab;lg.appendChild(t);});
    svg.appendChild(lg);
    container.replaceChildren(svg);
  }

  function drawDonutChart(container, { data, labels, colors }) {
    const w = container.clientWidth || 150, h = container.clientHeight || 150;
    if (!w) return;
    const r = Math.min(w,h)/2, cx = w/2, cy = h/2;
    const max = data.reduce((a,b)=>a+b,0);
    const svg = svgEl('svg',{viewBox:`0 0 ${w} ${h}`});
    let startAngle = -Math.PI/2;

    container.className = ''; 
    container.style.width = '100%'; container.style.height = '100%';

    if (max === 0) {
      svg.appendChild(svgEl('circle',{cx, cy, r, fill: getCSS('--surface-2')}));
      svg.appendChild(svgEl('circle',{cx, cy, r:r*0.6, fill: getCSS('--surface')}));
      container.replaceChildren(svg);
      return;
    }

    data.forEach((val, i) => {
      const sliceAngle = (val/max) * 2 * Math.PI;
      const endAngle = startAngle + sliceAngle;
      
      const x1 = cx + r * Math.cos(startAngle);
      const y1 = cy + r * Math.sin(startAngle);
      const x2 = cx + r * Math.cos(endAngle);
      const y2 = cy + r * Math.sin(endAngle);
      
      let pathData;
      if (sliceAngle > 2 * Math.PI - 0.001) {
        pathData = `M ${cx} ${cy-r} A ${r} ${r} 0 1 1 ${cx} ${cy+r} A ${r} ${r} 0 1 1 ${cx} ${cy-r} Z`;
      } else {
        const largeArc = sliceAngle > Math.PI ? 1 : 0;
        pathData = `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`;
      }
      
      const cCSS = getCSS(colors[i%colors.length]) || '#ccc';
      const path = svgEl('path',{d:pathData, fill:cCSS, style:'cursor:pointer; transition:opacity 0.15s;'});
      
      path.onmouseenter = (e) => { 
        path.style.opacity = 0.8; 
        const pct = Math.round((val/max)*100);
        showChartTooltip(e, `${labels[i]}<br><span style="color:${cCSS}">${fmt(val)} (${pct}%)</span>`); 
      };
      path.onmousemove = (e) => { chartTooltip.style.left = e.clientX+'px'; chartTooltip.style.top = e.clientY+'px'; };
      path.onmouseleave = () => { path.style.opacity = 1; hideChartTooltip(); };
      
      svg.appendChild(path);
      startAngle += sliceAngle;
    });

    svg.appendChild(svgEl('circle',{cx, cy, r:r*0.6, fill: getCSS('--surface'), style:'pointer-events:none;'}));
    container.replaceChildren(svg);
  }

  /* ==========================================================
     5. TRANSACTION MODAL (available on all pages)
     ========================================================== */
  const modalTx       = $('#modalTx');
  const modalTxTitle   = $('#modalTxTitle');
  const formTx        = $('#formTx');
  const txEditId      = $('#txEditId');
  const txTypeGroup   = $('#txTypeGroup');
  $$('.currency-format').forEach(input => {
    input.addEventListener('input', (e) => {
      let val = e.target.value.replace(/[^0-9,]/g, '');
      const parts = val.split(',');
      if (parts[0]) {
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }
      e.target.value = parts.join(',');
    });
  });

  const txAmount      = $('#txAmount');
  const txCategory    = $('#txCategory');
  const txDescription = $('#txDescription');
  const txDate        = $('#txDate');
  const txPayment     = $('#txPayment');
  const txPaymentGroup= $('#txPaymentGroup');
  const txPaymentOther= $('#txPaymentOther');

  let currentTxType = 'ingreso';

  function parseCOP(val) {
    if (!val) return 0;
    return parseFloat(val.toString().replace(/\./g, '').replace(',', '.'));
  }

  if (txPayment) {
    txPayment.addEventListener('change', () => {
      if (txPaymentOther) {
        if (txPayment.value === 'otro') {
          txPaymentOther.style.display = 'block';
          txPaymentOther.required = true;
        } else {
          txPaymentOther.style.display = 'none';
          txPaymentOther.required = false;
        }
      }
    });
  }

  function openModal(m)  { if(m) m.classList.add('is-active'); }
  function closeModal(m) { if(m) m.classList.remove('is-active'); }

  function openNewTx() {
    if(!txEditId) return;
    txEditId.value = '';
    modalTxTitle.textContent = 'Nuevo Movimiento';
    formTx.reset();
    currentTxType = 'ingreso';
    updateTypeButtons();
    updateCatOptions();
    if(txPaymentOther) {
      txPaymentOther.style.display = 'none';
      txPaymentOther.required = false;
    }
    txDate.value = todayISO();
    openModal(modalTx);
  }

  function openEditTx(id) {
    const tx = loadTx().find(t => t.id === id);
    if (!tx) return;
    txEditId.value = id;
    modalTxTitle.textContent = 'Editar Movimiento';
    currentTxType = tx.type;
    updateTypeButtons();
    updateCatOptions();
    txAmount.value = tx.amount;
    txCategory.value = tx.category;
    txDescription.value = tx.description;
    txDate.value = tx.date;
    
    if (tx.payment_method) {
      const standard = ['efectivo', 'tarjeta', 'transferencia'];
      if (standard.includes(tx.payment_method)) {
        txPayment.value = tx.payment_method;
        if(txPaymentOther) { txPaymentOther.style.display = 'none'; txPaymentOther.required = false; }
      } else {
        txPayment.value = 'otro';
        if(txPaymentOther) { 
          txPaymentOther.value = tx.payment_method;
          txPaymentOther.style.display = 'block'; 
          txPaymentOther.required = true;
        }
      }
    } else {
      txPayment.value = 'efectivo';
      if(txPaymentOther) { txPaymentOther.style.display = 'none'; txPaymentOther.required = false; }
    }
    openModal(modalTx);
  }

  function updateTypeButtons() {
    if(!txTypeGroup) return;
    $$('.type-btn', txTypeGroup).forEach(b => b.classList.toggle('is-active', b.dataset.val === currentTxType));
    
    if (txPaymentGroup) {
      if (currentTxType === 'ahorro') {
        txPaymentGroup.style.display = 'none';
      } else {
        txPaymentGroup.style.display = 'block';
      }
    }
  }

  function updateCatOptions() {
    const txCategory = $('#txCategory');
    const txTypeGroup = $('#txTypeGroup');
    if(!txCategory||!txTypeGroup) return;
    const currentTxType = txTypeGroup.querySelector('.is-active')?.dataset?.val || 'gasto';
    
    // Toggle optgroup visibility
    $$('optgroup', txCategory).forEach(g => {
      const isMatch = g.dataset.type === currentTxType;
      g.style.display = isMatch ? '' : 'none';
      g.disabled = !isMatch;
    });
    
    // Ensure the selected option belongs to the active group
    const activeGroup = txCategory.querySelector(`optgroup[data-type="${currentTxType}"]`);
    if (activeGroup && (!txCategory.value || txCategory.options[txCategory.selectedIndex].parentNode !== activeGroup)) {
      txCategory.value = activeGroup.querySelector('option').value;
    }
  }

  if (txTypeGroup) {
    $$('.type-btn', txTypeGroup).forEach(btn => {
      btn.addEventListener('click', () => {
        currentTxType = btn.dataset.val;
        if($('#txType')) $('#txType').value = currentTxType;
        updateTypeButtons();
        updateCatOptions();
      });
    });
  }

  if (formTx) {
    formTx.addEventListener('submit', () => {
      // Just let native HTML POST submission happen.
    });
  }

  // Modal open/close bindings
  const btnNewTx = $('#btnNewTx');
  if (btnNewTx) btnNewTx.addEventListener('click', openNewTx);
  const btnFirstTx = $('#btnFirstTx');
  if (btnFirstTx) btnFirstTx.addEventListener('click', openNewTx);
  const btnCloseTx = $('#btnCloseTx');
  if (btnCloseTx) btnCloseTx.addEventListener('click', () => closeModal(modalTx));
  const btnCancelTx = $('#btnCancelTx');
  if (btnCancelTx) btnCancelTx.addEventListener('click', () => closeModal(modalTx));
  if (modalTx) modalTx.addEventListener('click', e => { if (e.target === modalTx) closeModal(modalTx); });



  // Expenses Modal bindings
  const modalExpense = $('#modalExpense');
  const btnNewExpense = $('#btnNewExpense');
  const btnCloseExpense = $('#btnCloseExpense');
  const btnCancelExpense = $('#btnCancelExpense');
  const formExpense = $('#formExpense');

  if (btnCloseExpense) btnCloseExpense.addEventListener('click', () => closeModal(modalExpense));
  if (btnCancelExpense) btnCancelExpense.addEventListener('click', () => closeModal(modalExpense));
  if (modalExpense) modalExpense.addEventListener('click', e => { if (e.target === modalExpense) closeModal(modalExpense); });


  const expenseEmojiGrid = $('#expenseEmojiGrid');
  const expenseEmojiInput = $('#expenseEmoji');
  if (expenseEmojiGrid && expenseEmojiInput) {
    $$('.emoji-btn', expenseEmojiGrid).forEach(btn => {
      btn.addEventListener('click', () => {
        $$('.emoji-btn', expenseEmojiGrid).forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        expenseEmojiInput.value = btn.dataset.val;
      });
    });
  }

  const expenseColorGrid = $('#expenseColorGrid');
  const expenseColorInput = $('#expenseColor');
  if (expenseColorGrid && expenseColorInput) {
    $$('.color-btn', expenseColorGrid).forEach(btn => {
      btn.addEventListener('click', () => {
        $$('.color-btn', expenseColorGrid).forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        expenseColorInput.value = btn.dataset.val;
      });
    });
  }
  /* ==========================================================
     6. TRANSACTION LIST ITEM BUILDER
     ========================================================== */
  function buildTxItem(tx, showActions = true) {
    const icon = CAT_ICONS[tx.category] || '📎';
    const prefix = tx.type === 'ingreso' ? '+' : tx.type === 'gasto' ? '-' : '';
    let actionsHTML = '';
    if (showActions) {
      actionsHTML = `
        <div class="tx-item-actions">
          <button class="tx-action edit" data-id="${tx.id}" title="Editar">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 0 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="tx-action del" data-id="${tx.id}" title="Eliminar">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>`;
    }
    return `
      <li class="tx-item">
        <div class="tx-item-icon">${icon}</div>
        <div class="tx-item-info">
          <div class="tx-item-desc">${tx.description}</div>
          <div class="tx-item-meta">${tx.category} · ${fmtDate(tx.date)}</div>
        </div>
        <div class="tx-item-amount is-${tx.type}">${prefix}${fmt(tx.amount)}</div>
        ${actionsHTML}
      </li>`;
  }

  function buildTxTableRow(tx, showActions = true) {
    const icon = CAT_ICONS[tx.category] || '📎';
    const prefix = tx.type === 'ingreso' ? '+' : tx.type === 'gasto' ? '-' : '';
    let actionsHTML = '';
    if (showActions) {
      actionsHTML = `
        <div class="tx-item-actions" style="justify-content:flex-end;">
          <button class="tx-action edit" data-id="${tx.id}" title="Editar">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 0 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button class="tx-action del" data-id="${tx.id}" title="Eliminar">
            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>`;
    }
    return `
      <tr>
        <td style="white-space:nowrap; color:var(--text-muted); font-size:0.85rem;">${fmtDate(tx.date)}</td>
        <td>
          <div class="tx-item-desc" style="font-size:0.95rem;">${tx.description}</div>
        </td>
        <td>
          <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:0.85rem;">
            <div class="tx-item-icon" style="width:28px; height:28px; font-size:0.9rem;">${icon}</div>
            ${tx.category}
          </div>
        </td>
        <td class="tx-item-amount is-${tx.type}" style="text-align:left;">${prefix}${fmt(tx.amount)}</td>
        <td>${actionsHTML}</td>
      </tr>`;
  }

  function attachTxActions() {
    $$('.tx-action.edit').forEach(btn => { btn.onclick = () => openEditTx(btn.dataset.id); });
    $$('.tx-action.del').forEach(btn => { btn.onclick = () => deleteTx(btn.dataset.id); });
  }

  async function deleteTx(id) {
    if (!confirm('¿Eliminar este movimiento?')) return;
    await deleteTxAPI(id);
    toast('Movimiento eliminado');
    updateHeaderBalance();
    if (PAGE === 'dashboard') renderDashboard();
    if (PAGE === 'movimientos') renderTxList();
  }

  /* ==========================================================
     PAGE: MOVIMIENTOS
     ========================================================== */
  function fmtDate(d) {
    if (!d) return '';
    const parts = d.split('-');
    if (parts.length !== 3) return d;
    return `${parts[2]} ${MONTHS_SHORT[parseInt(parts[1])-1]} ${parts[0]}`;
  }

  let currentNavMonth = { y: new Date().getFullYear(), m: new Date().getMonth() };

  function initMovimientos() {
    // Handlers will be attached below

    if ($('#mnPrev')) $('#mnPrev').addEventListener('click', () => {
      if (!currentNavMonth) { const n = new Date(); currentNavMonth = { y: n.getFullYear(), m: n.getMonth() }; }
      else {
        currentNavMonth.m--;
        if (currentNavMonth.m < 0) { currentNavMonth.m = 11; currentNavMonth.y--; }
      }
      renderTxList();
    });
    if ($('#mnNext')) $('#mnNext').addEventListener('click', () => {
      if (!currentNavMonth) { const n = new Date(); currentNavMonth = { y: n.getFullYear(), m: n.getMonth() }; }
      else {
        currentNavMonth.m++;
        if (currentNavMonth.m > 11) { currentNavMonth.m = 0; currentNavMonth.y++; }
      }
      renderTxList();
    });
    if ($('#mnShowAll')) $('#mnShowAll').addEventListener('click', () => {
      currentNavMonth = null;
      renderTxList();
    });

    if ($('#txSearch')) $('#txSearch').addEventListener('input', renderTxList);
    if ($('#filterType')) $('#filterType').addEventListener('change', renderTxList);
    if ($('#filterCat')) $('#filterCat').addEventListener('change', renderTxList);
    const btnClear = $('#btnClearFilters');
    if(btnClear) btnClear.addEventListener('click',()=>{
      ['#txSearch','#filterType','#filterCat'].forEach(sel=>{const el=$(sel);if(el)el.value='';});
      renderTxList();
    });

    renderTxList();
  }

  function renderTxList() {
    let txs = loadTx();
    
    // 1. Filter by Nav Month
    if (currentNavMonth) {
      txs = txs.filter(t => {
        const d = new Date(t.date + 'T12:00:00');
        return d.getFullYear() === currentNavMonth.y && d.getMonth() === currentNavMonth.m;
      });
      const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
      if ($('#mnMonthLabel')) $('#mnMonthLabel').textContent = `${monthNames[currentNavMonth.m]} ${currentNavMonth.y}`;
      if ($('#mnSubLabel')) $('#mnSubLabel').textContent = 'Todos los movimientos del mes';
    } else {
      if ($('#mnMonthLabel')) $('#mnMonthLabel').textContent = 'Todos los meses';
      if ($('#mnSubLabel')) $('#mnSubLabel').textContent = 'Historial completo';
    }

    // 2. Filter by search/type/cat
    const q = ($('#txSearch') ? $('#txSearch').value.toLowerCase() : '');
    const type = ($('#filterType') ? $('#filterType').value : '');
    const cat = ($('#filterCat') ? $('#filterCat').value : '');

    txs = txs.filter(t => {
      if (type && t.type !== type) return false;
      if (cat && t.category !== cat) return false;
      if (q && !t.description.toLowerCase().includes(q) && !t.category.toLowerCase().includes(q)) return false;
      return true;
    });

    // 3. Mini stats
    let income = 0, expense = 0;
    txs.forEach(t => {
      const amt = parseFloat(t.amount);
      if (t.type === 'ingreso') income += amt;
      else if (t.type === 'gasto') expense += amt;
    });
    
    if ($('#txMonthIncome')) $('#txMonthIncome').textContent = fmt(income);
    if ($('#txMonthExpense')) $('#txMonthExpense').textContent = fmt(expense);
    if ($('#txMonthBalance')) $('#txMonthBalance').textContent = fmt(income - expense);

    // 4. Update UI table
    const tbody = $('#txListFull tbody');
    if (!tbody) return;
    
    if ($('#txCount')) $('#txCount').textContent = `${txs.length} registros`;

    const emptyEl = $('#txEmpty');
    const containerEl = $('#txContainer');

    if (txs.length === 0) {
      if(containerEl) containerEl.style.display = 'none';
      if(emptyEl) emptyEl.style.display = 'flex';
    } else {
      if(containerEl) containerEl.style.display = 'block';
      if(emptyEl) emptyEl.style.display = 'none';
      tbody.innerHTML = txs.map(t => buildTxTableRow(t, true)).join('');
      attachTxActions();
    }
  }

  /* ==========================================================
     PAGE: ESTADÍSTICAS
     ========================================================== */
  let currentStatsYear = new Date().getFullYear();
  let statsEventsBound = false;

  function renderStats() {
    if (!statsEventsBound) {
      if ($('#statsPrevYear')) $('#statsPrevYear').addEventListener('click', () => { currentStatsYear--; renderStats(); });
      if ($('#statsNextYear')) $('#statsNextYear').addEventListener('click', () => { currentStatsYear++; renderStats(); });
      statsEventsBound = true;
    }
    if ($('#statsYearLabel')) $('#statsYearLabel').textContent = currentStatsYear;
    if ($('#statsYear')) $('#statsYear').textContent = currentStatsYear;

    // Load data from DB via loadTx()
    const txs = loadTx();

    const months = [];
    for (let m=0; m<12; m++) months.push({m, y: currentStatsYear, label: MONTHS_SHORT[m]});

    const incD = months.map(({m,y})=>txs.filter(t=>{const d=new Date(t.date+'T00:00:00');return t.type==='ingreso'&&d.getMonth()===m&&d.getFullYear()===y;}).reduce((s,t)=>s+parseFloat(t.amount),0));
    const expD = months.map(({m,y})=>txs.filter(t=>{const d=new Date(t.date+'T00:00:00');return t.type==='gasto'&&d.getMonth()===m&&d.getFullYear()===y;}).reduce((s,t)=>s+parseFloat(t.amount),0));
    const yearlyIncome = incD.reduce((a,b)=>a+b, 0);
    const yearlyExpense = expD.reduce((a,b)=>a+b, 0);
    if ($('#statsYearIncome')) $('#statsYearIncome').textContent = fmt(yearlyIncome);
    if ($('#statsYearExpense')) $('#statsYearExpense').textContent = fmt(yearlyExpense);

    // Income vs Expense chart
    const cIvE = $('#chartIncomeVsExpense');
    if(cIvE && cIvE.clientWidth) drawGroupedBarChart(cIvE,{data1:incD,data2:expD,labels:months.map(m=>m.label),color1:getCSS('--accent')||'#31bd93',color2:getCSS('--rose-400')||'#e79cb0',legend1:'Ingresos',legend2:'Gastos'});

    // Category donuts
    const currentTxs = txs.filter(t => new Date(t.date+'T00:00:00').getFullYear() === currentStatsYear);
    renderDonutByType(currentTxs, 'gasto', $('#donutCat'), $('#catLegend'), 'Sin datos de gastos');
    renderDonutByType(currentTxs, 'ingreso', $('#donutIncome'), $('#incomeLegend'), 'Sin datos de ingresos');
  }

  function renderDonutByType(txs, typeStr, donutEl, legendEl, emptyText) {
    if(!donutEl || !legendEl) return;
    
    const filtered = txs.filter(t=>t.type===typeStr);
    const total = filtered.reduce((s,t)=>s+parseFloat(t.amount),0);
    const catTotals = {};
    filtered.forEach(t=>{catTotals[t.category]=(catTotals[t.category]||0)+parseFloat(t.amount);});
    const sorted = Object.entries(catTotals).sort((a,b)=>b[1]-a[1]);
    const colors=['--accent','--rose-400','--blue-400','--amber-400','--slate-400','--sand-400'];
    const dotCls=['dot-accent','dot-rose','dot-blue','dot-amber','dot-slate','dot-sand'];

    if(sorted.length===0){
      drawDonutChart(donutEl, { data: [], labels: [], colors: [] });
      legendEl.innerHTML=`<li style="color:var(--text-faint)">${emptyText}</li>`;
      return;
    }

    const data = sorted.map(s => s[1]);
    const labels = sorted.map(s => s[0]);
    drawDonutChart(donutEl, { data, labels, colors });

    legendEl.innerHTML=sorted.map(([cat,amt],i)=>{
      const pct=Math.round((amt/total)*100);
      return `<li><span class="dot ${dotCls[i%dotCls.length]}"></span>${cat} <em>${pct}%</em></li>`;
    }).join('');
  }

  /* ==========================================================
     PAGE: OBJETIVOS
     ========================================================== */
  function initObjetivos() {
    // Goal modal setup
    const emojiPicker=$('#goalEmojiGrid');
    const goalIcon=$('#goalEmoji');
    
    if(emojiPicker) {
      $$('.emoji-btn',emojiPicker).forEach(btn=>{
        btn.addEventListener('click', () => {
          goalIcon.value=btn.dataset.val;
          $$('.emoji-btn',emojiPicker).forEach(b=>b.classList.toggle('is-active',b.dataset.val===goalIcon.value));
        });
      });
    }

    // Modal closing events
    const modalGoal=$('#modalGoal'), btnCloseGoal=$('#btnCloseGoal'), btnCancelGoal=$('#btnCancelGoal');
    if(btnCloseGoal) btnCloseGoal.addEventListener('click',()=>closeModal(modalGoal));
    if(btnCancelGoal) btnCancelGoal.addEventListener('click',()=>closeModal(modalGoal));
    if(modalGoal) modalGoal.addEventListener('click',e=>{if(e.target===modalGoal)closeModal(modalGoal);});

    const modalDeposit=$('#modalDeposit'), btnCloseDeposit=$('#btnCloseDeposit'), btnCancelDeposit=$('#btnCancelDeposit');
    if(btnCloseDeposit) btnCloseDeposit.addEventListener('click',()=>closeModal(modalDeposit));
    if(btnCancelDeposit) btnCancelDeposit.addEventListener('click',()=>closeModal(modalDeposit));
    if(modalDeposit) modalDeposit.addEventListener('click',e=>{if(e.target===modalDeposit)closeModal(modalDeposit);});


    // Validation for new/edit goal forms
    const formGoal = $('#formGoal');
    const goalTarget = $('#goalTarget');
    const goalSaved = $('#goalSaved');
    const goalError = $('#goalError');
    if (formGoal && goalTarget && goalSaved) {
      formGoal.addEventListener('submit', function(e) {
        e.preventDefault(); // Stop native submission immediately
        
        const tVal = parseCurrency(goalTarget.value);
        const sVal = parseCurrency(goalSaved.value);
        
        if (sVal > tVal) {
          if(goalError) goalError.style.display = 'block';
          goalSaved.style.borderColor = 'var(--red)';
          toast(`El total ahorrado no puede superar la meta de ${fmt(tVal)}`, 'error');
        } else {
          // If valid, submit the form programmatically
          formGoal.submit();
        }
      });
      goalSaved.addEventListener('input', function() {
        if(goalError) goalError.style.display = 'none';
        this.style.borderColor = '';
        
        // Aggressively cap the saved amount to not exceed the target amount
        const targetReal = parseCurrency(goalTarget.value);
        const savedReal = parseCurrency(this.value);
        
        if (savedReal > targetReal) {
          this.value = targetReal.toLocaleString('es-CO', { minimumFractionDigits: 2 });
          toast(`El total ahorrado no puede superar la meta de ${fmt(targetReal)}`, 'error');
        }
      });
      
      goalTarget.addEventListener('input', function() {
        if(goalError) goalError.style.display = 'none';
        goalSaved.style.borderColor = '';
        
        // If target is lowered below saved, optionally cap saved down
        const targetReal = parseCurrency(this.value);
        const savedReal = parseCurrency(goalSaved.value);
        
        if (savedReal > targetReal && targetReal > 0) {
          goalSaved.value = targetReal.toLocaleString('es-CO', { minimumFractionDigits: 2 });
          toast(`La meta no puede ser menor al dinero ya abonado. Ajustando a ${fmt(targetReal)}`, 'error');
        }
      });
    }

    // Validation for deposit form
    const formDeposit = $('#formDeposit');
    const depositAmount = $('#depositAmount');
    const depositError = $('#depositError');
    if (formDeposit && depositAmount) {
      formDeposit.addEventListener('submit', function(e) {
        e.preventDefault(); // Stop native submission immediately
        
        const depositReal = parseCurrency(depositAmount.value);
        const saved = parseFloat(formDeposit.getAttribute('data-saved')) || 0;
        const target = parseFloat(formDeposit.getAttribute('data-target')) || 0;
        const globalBalance = parseFloat(document.body.dataset.balance) || 0;
        
        if (depositReal > globalBalance) {
          if(depositError) depositError.style.display = 'block';
          depositAmount.style.borderColor = 'var(--red)';
          toast('Saldo insuficiente', 'error');
        } else if (saved + depositReal > target) {
          if(depositError) depositError.style.display = 'block';
          depositAmount.style.borderColor = 'var(--red)';
          toast(`Supera la meta. Intentas ahorrar ${fmt(saved + depositReal)} pero el máximo es ${fmt(target)}`, 'error');
        } else {
          // If valid, submit the form programmatically
          formDeposit.submit();
        }
      });
      depositAmount.addEventListener('input', function() {
        if(depositError) depositError.style.display = 'none';
        this.style.borderColor = '';
        
        // Aggressively cap the input value
        const depositReal = parseCurrency(this.value);
        const saved = parseFloat(formDeposit.getAttribute('data-saved')) || 0;
        const target = parseFloat(formDeposit.getAttribute('data-target')) || 0;
        const globalBalance = parseFloat(document.body.dataset.balance) || 0;
        
        const maxRemaining = target - saved;
        // The max allowed is the smallest between what remains for the goal and the user's available balance
        const maxAllowed = Math.min(maxRemaining, globalBalance);
        
        if (depositReal > maxAllowed && maxAllowed > 0) {
          // Format the max allowed value and overwrite the input immediately
          this.value = maxAllowed.toLocaleString('es-CO', { minimumFractionDigits: 2 });
          if (depositReal > globalBalance && globalBalance < maxRemaining) {
            toast('Saldo insuficiente', 'error');
          } else {
            toast(`Solo puedes abonar hasta ${fmt(maxAllowed)}`, 'error');
          }
        } else if (depositReal > maxAllowed && maxAllowed <= 0) {
          this.value = '0,00';
          if (globalBalance <= 0) {
            toast('Saldo insuficiente', 'error');
          }
        }
      });
    }
  }

  // Global functions for Objetivos
  window.openNewGoal = function() {
    const formGoal=$('#formGoal'), goalEditId=$('#goalEditId');
    const modalGoalTitle=$('#modalGoalTitle'), goalIcon=$('#goalEmoji');
    const goalSaved=$('#goalSaved');
    
    if(goalEditId) goalEditId.value='';
    if(modalGoalTitle) modalGoalTitle.textContent='Nueva Meta de Ahorro';
    if(formGoal) formGoal.reset(); 
    if(goalSaved) goalSaved.value='';
    if(goalIcon) {
      goalIcon.value='🎯'; 
      const emojiPicker=$('#goalEmojiGrid');
      if(emojiPicker) $$('.emoji-btn', emojiPicker).forEach(b=>b.classList.toggle('is-active',b.dataset.val==='🎯'));
    }
    openModal($('#modalGoal'));
  };

  window.openEditGoal = function(id, name, target, saved, deadline, icon) {
    const formGoal=$('#formGoal'), goalEditId=$('#goalEditId');
    const goalName=$('#goalName'), goalIcon=$('#goalEmoji'), goalTarget=$('#goalTarget'), goalDeadline=$('#goalDeadline');
    const goalSaved=$('#goalSaved');
    const modalGoalTitle=$('#modalGoalTitle');
    
    if(goalEditId) goalEditId.value = id; 
    if(modalGoalTitle) modalGoalTitle.textContent = 'Editar Meta';
    if(goalName) goalName.value = name; 
    if(goalIcon) goalIcon.value = icon || '🎯';
    if(goalTarget) goalTarget.value = target ? Number(target).toLocaleString('es-CO', { minimumFractionDigits: 2 }) : ''; 
    if(goalSaved) goalSaved.value = saved ? Number(saved).toLocaleString('es-CO', { minimumFractionDigits: 2 }) : ''; 
    if(goalDeadline) goalDeadline.value = deadline || ''; 
    
    const emojiPicker=$('#goalEmojiGrid');
    if(emojiPicker) $$('.emoji-btn', emojiPicker).forEach(b=>b.classList.toggle('is-active',b.dataset.val===(icon || '🎯')));
    
    openModal($('#modalGoal'));
  };

  window.openDeposit = function(id, name, saved, target) {
    const depositGoalId=$('#depositGoalId'), depositAmount=$('#depositAmount'), depositGoalName=$('#depositGoalName');
    if(depositGoalId) depositGoalId.value = id;
    if(depositGoalName) depositGoalName.textContent = `Meta: ${name} — Ahorrado: ${fmt(saved)} de ${fmt(target)}`;
    if(depositAmount) depositAmount.value = '';
    
    const formDeposit=$('#formDeposit');
    if (formDeposit) {
      formDeposit.setAttribute('data-saved', saved);
      formDeposit.setAttribute('data-target', target);
    }

    openModal($('#modalDeposit'));
  };

  window.deleteGoal = function(id) {
    const deleteForm = $('#formDeleteGoal');
    const deleteGoalId = $('#deleteGoalId');
    const modal = document.getElementById('modalConfirm');
    if (modal) {
      const titleEl = modal.querySelector('h2');
      if (titleEl) titleEl.textContent = '¿Eliminar Objetivo?';
      modal.classList.add('is-active');
      const btnYes = document.getElementById('btnConfirmYes');
      const btnNo = document.getElementById('btnConfirmNo');
      const onYes = () => { cleanup(); if(deleteGoalId) deleteGoalId.value = id; if(deleteForm) deleteForm.submit(); };
      const onNo = () => { cleanup(); };
      const cleanup = () => { modal.classList.remove('is-active'); btnYes.removeEventListener('click', onYes); btnNo.removeEventListener('click', onNo); };
      btnYes.addEventListener('click', onYes);
      btnNo.addEventListener('click', onNo);
    } else {
      if (confirm('¿Estás seguro de que deseas eliminar este objetivo?')) {
        if(deleteGoalId) deleteGoalId.value = id;
        if(deleteForm) deleteForm.submit();
      }
    }
  };

  // Global functions for Gastos Fijos
  window.openExpenseDeposit = function(id, name, saved, target) {
    const formExpenseDeposit=$('#formExpenseDeposit'), depositExpenseId=$('#depositExpenseId'), depositExpenseAmount=$('#depositExpenseAmount'), depositExpenseName=$('#depositExpenseName');
    
    if(depositExpenseId) depositExpenseId.value = id;
    if(depositExpenseName) depositExpenseName.textContent = name;
    if(depositExpenseAmount) depositExpenseAmount.value = '';
    
    if(formExpenseDeposit) {
      formExpenseDeposit.setAttribute('data-saved', saved);
      formExpenseDeposit.setAttribute('data-target', target);
    }
    
    openModal($('#modalExpenseDeposit'));
  };

  window.openNewExpense = function() {
    const formExpense = $('#formExpense'), expenseId = $('#expenseId');
    const modalExpenseTitle = $('#modalExpenseTitle'), expenseEmoji = $('#expenseEmoji');
    
    if (expenseId) expenseId.value = '';
    if (modalExpenseTitle) modalExpenseTitle.textContent = 'Nuevo Gasto Fijo';
    if (formExpense) formExpense.reset(); 
    if (expenseEmoji) {
      expenseEmoji.value = '🏠'; 
      const emojiPicker = $('#expenseEmojiGrid');
      if (emojiPicker) $$('.emoji-btn', emojiPicker).forEach(b => b.classList.toggle('is-active', b.dataset.val === '🏠'));
    }
    openModal($('#modalExpense'));
  };

  window.openEditExpense = function(id, name, amount, date, icon) {
    const formExpense = $('#formExpense'), expenseId = $('#expenseId');
    const modalExpenseTitle = $('#modalExpenseTitle');
    const expenseName = $('#expenseName'), expenseAmount = $('#expenseAmount'), expenseDate = $('#expenseDate');
    const expenseEmoji = $('#expenseEmoji');
    
    if (expenseId) expenseId.value = id; 
    if (modalExpenseTitle) modalExpenseTitle.textContent = 'Editar Gasto Fijo';
    if (expenseName) expenseName.value = name; 
    if (expenseEmoji) expenseEmoji.value = icon || '🏠';
    if (expenseAmount) expenseAmount.value = amount ? Number(amount).toLocaleString('es-CO', { minimumFractionDigits: 2 }) : ''; 
    if (expenseDate) expenseDate.value = date || ''; 

    
    const emojiPicker = $('#expenseEmojiGrid');
    if (emojiPicker) $$('.emoji-btn', emojiPicker).forEach(b => b.classList.toggle('is-active', b.dataset.val === (icon || '🏠')));
    
    openModal($('#modalExpense'));
  };

  window.deleteExpense = function(id) {
    const deleteForm = $('#formDeleteExpense');
    const deleteExpenseId = $('#deleteExpenseId');
    const modal = document.getElementById('modalConfirm');
    if (modal) {
      const titleEl = modal.querySelector('h2');
      if (titleEl) titleEl.textContent = '¿Eliminar Gasto Fijo?';
      modal.classList.add('is-active');
      const btnYes = document.getElementById('btnConfirmYes');
      const btnNo = document.getElementById('btnConfirmNo');
      const onYes = () => { cleanup(); if(deleteExpenseId) deleteExpenseId.value = id; if(deleteForm) deleteForm.submit(); };
      const onNo = () => { cleanup(); };
      const cleanup = () => { modal.classList.remove('is-active'); btnYes.removeEventListener('click', onYes); btnNo.removeEventListener('click', onNo); };
      btnYes.addEventListener('click', onYes);
      btnNo.addEventListener('click', onNo);
    } else {
      if (confirm('¿Estás seguro de que deseas eliminar este gasto fijo?')) {
        if(deleteExpenseId) deleteExpenseId.value = id;
        if(deleteForm) deleteForm.submit();
      }
    }
  };

  // Bindings for Modals Gastos Fijos
  const modalExpenseDeposit = $('#modalExpenseDeposit');
  const btnCancelExpenseDeposit = $('#btnCancelExpenseDeposit');
  const btnCloseExpenseDeposit = $('#btnCloseExpenseDeposit');
  if (btnCancelExpenseDeposit) btnCancelExpenseDeposit.addEventListener('click', () => closeModal(modalExpenseDeposit));
  if (btnCloseExpenseDeposit) btnCloseExpenseDeposit.addEventListener('click', () => closeModal(modalExpenseDeposit));
  if (modalExpenseDeposit) modalExpenseDeposit.addEventListener('click', e => { if (e.target === modalExpenseDeposit) closeModal(modalExpenseDeposit); });

  const formExpenseDeposit = $('#formExpenseDeposit');
  const depositExpenseAmount = $('#depositExpenseAmount');
  const depositExpenseError = $('#depositExpenseError');
  
  if (formExpenseDeposit && depositExpenseAmount) {
    formExpenseDeposit.addEventListener('submit', function(e) {
      e.preventDefault();
      const depositReal = parseCurrency(depositExpenseAmount.value);
      const saved = parseFloat(formExpenseDeposit.getAttribute('data-saved')) || 0;
      const target = parseFloat(formExpenseDeposit.getAttribute('data-target')) || 0;
      const globalBalance = parseFloat(document.body.dataset.balance) || 0;
      
      if (depositReal > globalBalance) {
        if(depositExpenseError) depositExpenseError.style.display = 'block';
        depositExpenseAmount.style.borderColor = 'var(--red)';
        toast('Saldo insuficiente', 'error');
      } else if (saved + depositReal > target) {
        if(depositExpenseError) depositExpenseError.style.display = 'block';
        depositExpenseAmount.style.borderColor = 'var(--red)';
        toast(`Supera la meta. Intentas abonar ${fmt(saved + depositReal)} pero el máximo es ${fmt(target)}`, 'error');
      } else {
        formExpenseDeposit.submit();
      }
    });

    depositExpenseAmount.addEventListener('input', function() {
      if(depositExpenseError) depositExpenseError.style.display = 'none';
      this.style.borderColor = '';
      const depositReal = parseCurrency(this.value);
      const saved = parseFloat(formExpenseDeposit.getAttribute('data-saved')) || 0;
      const target = parseFloat(formExpenseDeposit.getAttribute('data-target')) || 0;
      const globalBalance = parseFloat(document.body.dataset.balance) || 0;
      
      const maxRemaining = target - saved;
      const maxAllowed = Math.min(maxRemaining, globalBalance);
      
      if (depositReal > maxAllowed && maxAllowed > 0) {
        this.value = maxAllowed.toLocaleString('es-CO', { minimumFractionDigits: 2 });
        if (depositReal > globalBalance && globalBalance < maxRemaining) {
          toast('Saldo insuficiente', 'error');
        } else {
          toast(`Solo puedes abonar hasta ${fmt(maxAllowed)}`, 'error');
        }
      } else if (depositReal > maxAllowed && maxAllowed <= 0) {
        this.value = '0,00';
        if (globalBalance <= 0) {
          toast('Saldo insuficiente', 'error');
        }
      }
    });
  }

  // Bindings for Pay Expense
  window.payExpense = function(id) {
    const payForm = $('#formPayExpense');
    const payExpenseId = $('#payExpenseId');
    if (confirm('¿Marcar este gasto como pagado? (Se descontará de tu balance)')) {
      if(payExpenseId) payExpenseId.value = id;
      if(payForm) payForm.submit();
    }
  };

  /* ==========================================================
     CHARTS REDRAW ON RESIZE
     ========================================================== */
  function renderPageCharts() {
    if (PAGE==='dashboard') renderDashboard();
    if (PAGE==='estadisticas') renderStats();
  }

  let resizeTimer;
  window.addEventListener('resize',()=>{clearTimeout(resizeTimer);resizeTimer=setTimeout(renderPageCharts,120);});

  /* ==========================================================
     INIT
     ========================================================== */
  function checkEmptyStates() {
    // Dashboard
    const recentEmpty = document.getElementById('recentEmpty');
    const recentTxList = document.getElementById('recentTxList');
    if (recentEmpty && recentTxList) {
      if (cachedTx.length === 0) {
        recentEmpty.style.display = 'flex';
        recentTxList.style.display = 'none';
      } else {
        recentEmpty.style.display = 'none';
        recentTxList.style.display = 'block';
      }
    }

    // Movimientos
    const txEmpty = document.getElementById('txEmpty');
    const txContainer = document.getElementById('txContainer');
    const txPagination = document.getElementById('txPagination');
    if (txEmpty && txContainer) {
      if (cachedTx.length === 0) {
        txEmpty.style.display = 'flex';
        txContainer.style.display = 'none';
        if (txPagination) txPagination.style.display = 'none';
      } else {
        txEmpty.style.display = 'none';
        txContainer.style.display = 'block';
        if (txPagination) txPagination.style.display = 'flex';
      }
    }

    // Gastos Fijos
    const expensesEmpty = document.getElementById('expensesEmpty');
    const expensesGrid = document.getElementById('expensesGrid');
    if (expensesEmpty && expensesGrid) {
      if (cachedExpenses.length === 0) {
        expensesEmpty.style.display = 'flex';
        expensesGrid.style.display = 'none';
      } else {
        expensesEmpty.style.display = 'none';
        expensesGrid.style.display = 'grid';
      }
    }

    // Objetivos
    const goalsEmpty = document.getElementById('goalsEmpty');
    const goalsGrid = document.getElementById('goalsGrid');
    if (goalsEmpty && goalsGrid) {
      if (cachedGoals.length === 0) {
        goalsEmpty.style.display = 'flex';
        goalsGrid.style.display = 'none';
      } else {
        goalsEmpty.style.display = 'none';
        goalsGrid.style.display = 'grid';
      }
    }

    // Notificaciones ahora maneja su propio estado en su respectivo módulo.
  }

  function init() {
    switch(PAGE) {
      case 'dashboard':    renderDashboard(); break;
      case 'movimientos':  initMovimientos(); break;
      case 'estadisticas': renderStats(); break;
      case 'objetivos':    initObjetivos(); break;
    }
  }

  async function boot() {
    await syncData();
    checkEmptyStates();
    init();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();

})();
