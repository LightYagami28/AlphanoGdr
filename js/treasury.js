(() => {
 'use strict';
 const root=document.querySelector('[data-treasury-scope]');if(!root){return;}
 const feedback=root.querySelector('[data-treasury-feedback]'),scope=root.dataset.treasuryScope,id=Number(root.dataset.treasuryId),receipts=new Map();let busy=false;
 const notice=async options=>window.alphanoDialog?window.alphanoDialog.notice(options):'ack';
 const setBusy=value=>{busy=value;for(const b of root.querySelectorAll('button')){b.disabled=value;}};
  function prepareData(form,action,data){
    if(action==='save'||action==='mandate'){data.enabled=form.elements.enabled.checked?1:0;}
    if(action==='save'){data.roles={};for(const input of form.querySelectorAll('[data-salary-role]')){data.roles[input.dataset.salaryRole]=input.value;}}
  }
  async function confirmAction(action,data,feedback){
    if(!['payroll','donate'].includes(action)){return true;}
    const answer=await notice({title:action==='payroll'?'Eroga gli stipendi':'Conferma donazione',message:action==='payroll'?'Gli stipendi saranno accreditati ai membri usando il budget attuale.':'Vuoi versare '+Number(data.amount)+window.AlphanoCurrency.text(' Corone personali a questa comunità?'),buttonLabel:'Conferma',cancelLabel:'Annulla'}).catch(error=>{feedback.textContent=error.message;return 'dismiss';});if(answer!=='ack'){setBusy(false);return;}
    const key=JSON.stringify([action,data]);if(!receipts.has(key)){receipts.set(key,crypto.randomUUID());}data.request_id=receipts.get(key);
    return true;
  }
  function renderBalances(value){
    feedback.textContent='Operazione registrata. Bilancio aggiornato.';root.querySelector('[data-treasury-balance]').textContent=value.entity.budget+window.AlphanoCurrency.text(' Corone');root.querySelector('[data-payroll-total]').textContent=value.payroll_total+window.AlphanoCurrency.text(' Corone');root.querySelector('[data-payroll-deadline]').textContent=Number(value.policy.enabled)?new Date(value.policy.next_run*1000).toLocaleString('it-IT'):'Non attiva';
    const save=root.querySelector('[data-treasury-action="save"]');if(save){save.elements.revision.value=value.policy.revision;}
    const mandate=root.querySelector('[data-treasury-action="mandate"]');mandate.elements.revision.value=value.donation?.revision||0;
  }
  function renderHistory(history){
    const tbody=root.querySelector('[data-treasury-history] tbody');tbody.replaceChildren();for(const entry of history){const row=document.createElement('tr');for(const text of [new Date(entry.created_at*1000).toLocaleString('it-IT'),(entry.kind==='payroll'?'Stipendi':'Donazione')+' · #'+entry.id,entry.amount+window.AlphanoCurrency.text(' Corone'),({applied:'Eseguito',waiting:'In attesa',failed:'Non eseguito',pending:'In preparazione'}[entry.status]||entry.status)+' · '+entry.detail]){const cell=document.createElement('td');cell.textContent=text;row.append(cell);}tbody.append(row);}
  }
  function clearReceipt(requestId){
    for(const[key,request]of receipts){if(request===requestId){receipts.delete(key);}}
  }
  for(const form of root.querySelectorAll('[data-treasury-action]')){form.addEventListener('submit',async event=>{
   event.preventDefault();if(busy){return;}setBusy(true);const action=form.dataset.treasuryAction,data=Object.fromEntries(new FormData(form));
   prepareData(form,action,data);
   if(!await confirmAction(action,data,feedback)){setBusy(false);return;}
   feedback.textContent='Operazione in corso…';
   try{const response=await fetch('/api/treasury.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({action,scope,id,...data})});const value=await response.json();if(!response.ok){throw new Error(value.error||'Operazione non completata.');}
    renderBalances(value);
    renderHistory(value.history);
    clearReceipt(data.request_id);
   }catch(error){feedback.textContent=error.message;await notice({title:'Operazione da verificare',message:error.message});}finally{setBusy(false);}
  });}
})();
