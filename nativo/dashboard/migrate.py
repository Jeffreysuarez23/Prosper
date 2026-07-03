import re

with open('js/script.js', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Replace DATA LAYER
data_layer_new = """  /* ==========================================================
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

  function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2,8); }"""

content = re.sub(
    r'/\*\s*==========================================================\s*DATA LAYER \(localStorage\)\s*==========================================================\s*\*/.*?(?=/\*\s*==========================================================\s*UI HELPERS)', 
    data_layer_new + "\n\n  ", 
    content, 
    flags=re.DOTALL
)

# 2. Modify formTx submit (lines ~ 460)
tx_submit_regex = r"const txs\s*=\s*loadTx\(\);\s*if\s*\(txEditId\.value\)\s*\{.*?\} else \{\s*txs\.unshift\(newTx\);\s*\}.*?saveTx\(txs\);\s*closeModal\(modalTx\);\s*initMovimientos\(\);"
tx_submit_new = """await saveTx(newTx);
      closeModal(modalTx);
      initMovimientos();"""
content = re.sub(tx_submit_regex, tx_submit_new, content, flags=re.DOTALL)

# Modify Tx deletion
tx_delete_regex = r"const txs\s*=\s*loadTx\(\);\s*const filtered\s*=\s*txs\.filter\(t\s*=>\s*t\.id\s*!==\s*id\);\s*saveTx\(filtered\);\s*initMovimientos\(\);"
tx_delete_new = """await deleteTxAPI(id); initMovimientos();"""
content = re.sub(tx_delete_regex, tx_delete_new, content, flags=re.DOTALL)


# 3. Modify formGoal submit
goal_submit_regex = r"const goals\s*=\s*loadGoals\(\);\s*if\s*\(goalEditId\.value\)\s*\{.*?\} else \{\s*goals\.push\(newGoal\);\s*\}.*?saveGoals\(goals\);\s*closeModal\(modalGoal\);\s*initObjetivos\(\);"
goal_submit_new = """await saveGoal(newGoal);
      closeModal(modalGoal);
      initObjetivos();"""
content = re.sub(goal_submit_regex, goal_submit_new, content, flags=re.DOTALL)


# Modify Goal deposit
deposit_submit_regex = r"const goals\s*=\s*loadGoals\(\);\s*const idx\s*=\s*goals\.findIndex.*?saveGoals\(goals\);\s*closeModal\(modalDeposit\);"
deposit_submit_new = """const goals = loadGoals();
      const idx = goals.findIndex(g => g.id === depositGoalId.value);
      if(idx<0) return;
      const g = goals[idx];
      g.current_amount = parseFloat(g.current_amount || 0) + parseFloat(depositAmount.value);
      if (g.current_amount > g.target_amount) g.current_amount = g.target_amount;
      
      await saveGoal(g);
      closeModal(modalDeposit);"""
content = re.sub(deposit_submit_regex, deposit_submit_new, content, flags=re.DOTALL)


# 4. INIT - wait for syncData before init
init_regex = r"if\s*\(document\.readyState\s*===\s*'loading'\)\s*document\.addEventListener\('DOMContentLoaded',\s*init\);\s*else\s*init\(\);"
init_new = """async function boot() {
    await syncData();
    init();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();"""
content = re.sub(init_regex, init_new, content)

with open('js/script.js', 'w', encoding='utf-8') as f:
    f.write(content)

print("Migration applied successfully!")
