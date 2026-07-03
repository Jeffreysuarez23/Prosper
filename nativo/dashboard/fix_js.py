import re

def main():
    with open('js/script.js', 'r', encoding='utf-8') as f:
        code = f.read()

    # 1. Fix formTx submit
    # We want to replace the whole formTx.addEventListener block
    # It starts with: `if (formTx) {\n    formTx.addEventListener('submit', e => {`
    # It ends with: `    });\n  }` around line 539.
    
    # Let's find it with regex
    tx_pattern = r"(if\s*\(formTx\)\s*\{\s*formTx\.addEventListener\('submit',\s*)e\s*=>\s*\{(.*?)(if\s*\(txEditId\.value\).*?)saveTx\(txs\);\s*closeModal\(modalTx\);(.*?)(\}\);)"
    def tx_repl(m):
        prefix = m.group(1)
        body1 = m.group(2)
        # body1 contains e.preventDefault() and data gathering.
        # We replace the tx array logic
        new_logic = """
      if (txEditId.value) {
        data.id = txEditId.value;
        toast('Movimiento actualizado');
      } else {
        toast('Movimiento guardado');
      }
      await saveTx(data);
      closeModal(modalTx);
"""
        body2 = m.group(4)
        suffix = m.group(5)
        return prefix + "async e => {" + body1 + new_logic + body2 + suffix
        
    code = re.sub(tx_pattern, tx_repl, code, flags=re.DOTALL)


    # 2. Fix formGoal inside initObjetivos
    # if(formGoal) formGoal.addEventListener('submit',e=>{ ... saveGoals(goals);closeModal(modalGoal); });
    goal_pattern = r"(if\s*\(formGoal\)\s*formGoal\.addEventListener\('submit',\s*)e\s*=>\s*\{(.*?)const\s+data=\{name:goalName\.value\.trim\(\)(.*?)(if\s*\(goalEditId\.value\).*?)saveGoals\(goals\);\s*closeModal\(modalGoal\);(.*?\}\);)"
    def goal_repl(m):
        prefix = m.group(1)
        body1 = m.group(2)
        data_decl = "const data={name:goalName.value.trim()" + m.group(3)
        new_logic = """
      if(goalEditId.value){
         data.id = goalEditId.value;
         toast('Meta actualizada');
      } else {
         toast('Meta creada');
      }
      await saveGoal(data);
      closeModal(modalGoal);
"""
        suffix = m.group(5)
        return prefix + "async e => {" + body1 + data_decl + new_logic + suffix
        
    code = re.sub(goal_pattern, goal_repl, code, flags=re.DOTALL)


    # 3. Fix deleteTx
    del_pattern = r"(function\s+deleteTx\s*\(\s*id\s*\)\s*\{.*?)saveTx\(loadTx\(\)\.filter\(t\s*=>\s*t\.id\s*!==\s*id\)\);\s*toast\('Movimiento eliminado'\);(.*?)\}"
    def del_repl(m):
        prefix = m.group(1).replace("function deleteTx", "async function deleteTx")
        new_logic = "await deleteTxAPI(id);\n    toast('Movimiento eliminado');"
        suffix = m.group(2)
        return prefix + new_logic + suffix + "}"
        
    code = re.sub(del_pattern, del_repl, code, flags=re.DOTALL)


    # 4. FormExpense inside script.js (there are two: one global around line 588, one where?)
    # Line 588: if (formExpense) formExpense.addEventListener('submit', e => { e.preventDefault(); toast('Gasto fijo guardado con éxito.'); closeModal(modalExpense); });
    exp_pattern = r"if\s*\(formExpense\)\s*formExpense\.addEventListener\('submit',\s*e\s*=>\s*\{\s*e\.preventDefault\(\);\s*toast\('Gasto fijo guardado con éxito\.'\);\s*closeModal\(modalExpense\);\s*\}\);"
    new_exp_logic = """
  if (formExpense) {
    formExpense.addEventListener('submit', async e => {
      e.preventDefault();
      const expenseName = $('#expenseName');
      const expenseAmount = $('#expenseAmount');
      const expenseDate = $('#expenseDate');
      const expenseFreq = $('#expenseFreq');
      const data = {
        name: expenseName ? expenseName.value.trim() : '',
        amount: expenseAmount ? parseFloat(expenseAmount.value) : 0,
        day_of_month: expenseDate ? parseInt(expenseDate.value) : 1,
        frequency: expenseFreq ? expenseFreq.value : 'mensual',
        emoji: expenseEmojiInput ? expenseEmojiInput.value : '🏠',
        color: expenseColorInput ? expenseColorInput.value : 'blue'
      };
      
      const editId = $('#expenseEditId'); // Assuming it might exist
      if(editId && editId.value) data.id = editId.value;
      
      await saveExpense(data);
      toast('Gasto fijo guardado con éxito.');
      closeModal(modalExpense);
    });
  }
"""
    code = re.sub(exp_pattern, new_exp_logic, code)

    with open('js/script.js', 'w', encoding='utf-8') as f:
        f.write(code)

if __name__ == '__main__':
    main()
