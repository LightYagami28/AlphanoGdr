    function selectionText(active){
      if(!active.length){return 'Nessun personaggio selezionato.';}
      const suffix=active.length===1?' selezionato à ':' selezionati à ';
      return active.length+suffix+active.map(person=>person.name).join(', ');
    }
    function itemPlacement(item){
      if(item.slot){return 'Equipaggiato: '+item.slot;}
      return item.container?'Dentro un contenitore':'Nella borsa';
    }
(() => {
  'use strict';
  function setup(root){
    const $=selector=>root.querySelector(selector),chat=root.dataset.chat,selected=new Set(),receipts=new Map();
    let state=null,busy=false,stopped=false,poll=null,tab='health',catalogItem=null,catalogNext=null,catalogAbort=null,inventoryAbort=null,healthBaseline={};
    const feedback=$('[data-participants-feedback]'),roster=$('[data-participants-roster]');
    const element=(tag,text='',className='')=>{const node=document.createElement(tag);node.textContent=text;if(className){node.className=className;}return node;};
    const image=(src,fallback)=>{const img=document.createElement('img');img.src=src;img.alt='';img.loading='lazy';img.addEventListener('error',()=>{if(fallback&&img.getAttribute('src')!==fallback){img.src=fallback;}},{once:true});return img;};
    const people=()=>state?.participants.filter(person=>selected.has(person.id))||[];
    async function request(action,signal,parameters={}){
      const url=new URL('/api/master-participants.php',location.origin);url.searchParams.set('chat',chat);url.searchParams.set('action',action);for(const [key,value]of Object.entries(parameters)){url.searchParams.set(key,value);}
      const response=await fetch(url,{cache:'no-store',credentials:'same-origin',signal});const result=await response.json();if(!response.ok){throw new Error(result.error||'Il pannello non è disponibile.');}return result;
    }
    function controls(){
      $('[data-participants-selection]').textContent=selectionText(people());
      for(const form of root.querySelectorAll('[data-participants-form]')){
        const reward=['crowns','item'].includes(form.dataset.participantsForm);
        form.querySelector('fieldset').disabled=busy||!state?.can_manage||!people().length||(reward&&(!state.session||people().some(person=>!person.member||person.self)));
      }
      for(const button of root.querySelectorAll('[data-inventory-action]')){button.disabled=busy||!state?.can_manage||button.dataset.unavailable==='1';}
      $('[data-participants-refresh]').disabled=busy;
    }
    function resetHealth(){
      const active=people(),form=$('[data-participants-form="health"]');healthBaseline=Object.fromEntries(active.map(person=>[person.id,{hp:person.hp,wounds:person.wounds}]));
      form.elements.hp.value=active.length===1?active[0].hp:'';form.elements.wounds.value=active.length===1?active[0].wounds:'';
      if(active.length>1){form.elements.wounds.placeholder='La stessa descrizione verrà applicata a tutti i selezionati';}
    }
    function participantCard(person){
      const card=element('label','','master-participant'),checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.value=person.id;checkbox.checked=selected.has(person.id);checkbox.setAttribute('aria-label','Seleziona '+person.name);
      checkbox.addEventListener('change',()=>{if(checkbox.checked){selected.add(person.id);}else {selected.delete(person.id);}resetHealth();controls();if(tab==='inventory'){void loadInventory();}});
      const body=element('span','','master-participant-body'),meter=document.createElement('meter');meter.min=0;meter.max=person.max_hp;meter.value=Math.max(0,person.hp);meter.setAttribute('aria-label','Salute di '+person.name);
      body.append(element('strong',person.name),element('small',(person.member?'Nella sessione':'Presente à fuori sessione')+(person.self?' à Tu':'')),element('small',person.hp+' / '+person.max_hp+' PF à '+person.crowns.toLocaleString('it-IT')+window.AlphanoCurrency.text(' Corone')),meter);
      card.replaceChildren(checkbox,image(person.portrait,person.fallback),body);if(person.wounds){card.appendChild(element('span',person.wounds,'master-participant-wounds'));}
      return card;
    }
    function synchronizeSelection(){
      for(const id of selected){if(!state.participants.some(person=>person.id===id)){selected.delete(id);}}
    }
    function restoreFocus(focused){
      if(!focused){return;}
      for(const input of roster.querySelectorAll('input')){if(input.value===focused){input.focus({preventScroll:true});break;}}
    }
    function renderEmptyState(){
      if(!roster.childElementCount){roster.appendChild(element('p',state.participants.length?'Nessun nome corrisponde alla ricerca.':'Nessun personaggio presente in questo luogo.'));}
    }
    function render(next,{reset=false}={}){
      const focused=roster.contains(document.activeElement)?document.activeElement.value:null;
      state=next;synchronizeSelection();
      const filter=$('[data-participants-search]').value.trim().toLocaleLowerCase('it');roster.replaceChildren();
      for(const person of state.participants){
        if(filter&&!person.name.toLocaleLowerCase('it').includes(filter)){continue;}
        roster.appendChild(participantCard(person));
      }
      renderEmptyState();restoreFocus(focused);
      $('[data-participants-notice]').textContent=state.notice;
      if(reset){resetHealth();}controls();root.dispatchEvent(new CustomEvent('alphano:participants-state',{bubbles:true,detail:state}));
    }
    async function refresh(reset=false){
      if(stopped||busy){return;}
      try{render(await request('state',AbortSignal.timeout(8000)),{reset});if(reset&&tab==='inventory'){await loadInventory();}}
      catch(error){if(!stopped){feedback.textContent=error.message;}}
    }
    async function loadCatalog(append=false){
      catalogAbort?.abort();catalogAbort=new AbortController();const status=$('[data-catalog-status]'),grid=$('[data-participants-catalog]');
      status.textContent='Caricamento del catalogo…';if(!append){grid.replaceChildren();}
      try{
        const catalog=await request('catalog',catalogAbort.signal,{q:$('[data-catalog-search]').value,type:$('[data-catalog-type]').value,offset:append?catalogNext||0:0});catalogNext=catalog.next;
        for(const item of catalog.items){const button=element('button','','master-catalog-item');button.type='button';button.dataset.catalogId=item.id;button.setAttribute('aria-pressed',String(catalogItem?.id===item.id));button.append(image(item.image),element('span',item.name),element('small',item.material?'Materia prima':'Oggetto'));button.addEventListener('click',()=>{catalogItem=item;for(const candidate of grid.querySelectorAll('button')){candidate.setAttribute('aria-pressed',String(Number(candidate.dataset.catalogId)===item.id));}$('[data-catalog-selection]').textContent='Da consegnare: '+item.name;});grid.appendChild(button);}
        status.textContent=catalog.total?catalog.total+' oggetti disponibili à scegli una carta per assegnarla.':'Nessun oggetto corrisponde ai filtri.';$('[data-catalog-more]').hidden=catalogNext===null;
      }catch(error){if(error.name!=='AbortError'){status.textContent=error.message;}}
    }
    function itemCondition(item){
      const material=item.material?'Materia prima à ':'';
      const broken=item.broken?'Rotto à ':'';
      return [material+itemPlacement(item),broken+'Durata residua '+item.remaining+' / '+item.durability];
    }
    function inventoryCard(item){
      const card=element('article','','master-inventory-item'),body=element('div');
      const [placement,durability]=itemCondition(item);
      body.append(element('h4',item.name),element('p',placement),element('p',durability));
      const actions=element('div','','master-inventory-actions'),quantity=element('label','Usura da applicare'),input=document.createElement('input');input.type='number';input.min=1;input.max=Math.max(1,item.remaining);input.value=1;quantity.append(input);actions.append(quantity);
      input.disabled=item.broken;for(const [action,label]of [['consume','Consuma'],['break','Rompi'],['remove','Rimuovi']]){const button=element('button',label);button.type='button';button.dataset.inventoryAction=action;button.dataset.unavailable=item.broken&&action!=='remove'?'1':'0';button.addEventListener('click',()=>void mutate(action,{instance:item.id,...(action==='consume'?{quantity:input.value}:{})},item.name));actions.append(button);}
      card.replaceChildren(image(item.image),body,actions);
      return card;
    }
    async function loadInventory(){
      inventoryAbort?.abort();const grid=$('[data-participants-inventory]'),active=people();grid.replaceChildren();
      if(active.length!==1){grid.textContent='Seleziona un solo personaggio per consultarne l’inventario.';return;}
      inventoryAbort=new AbortController();const person=active[0];grid.textContent='Caricamento dell’inventario di '+person.name+'…';
      try{
        const inventory=await request('inventory',inventoryAbort.signal,{target:person.id});grid.replaceChildren();
        if(!inventory.items.length){grid.textContent=person.name+' non possiede oggetti.';return;}
        for(const item of inventory.items){grid.appendChild(inventoryCard(item));}
        controls();
      }catch(error){if(error.name!=='AbortError'){grid.textContent=error.message;}}
    }
    function mutationError(action,active,reason){
      if(!active.length){return 'Seleziona almeno un personaggio.';}
      if(reason.length<5){return 'Descrivi la motivazione prima di intervenire.';}
      if(action==='item'&&!catalogItem){return 'Scegli un oggetto dal catalogo.';}
      return null;
    }
    function actionDetail(action,parameters){
      if(action==='crowns'){return parameters.amount+window.AlphanoCurrency.text(' Corone per personaggio');}
      if(action==='item'){return parameters.quantity+' × '+catalogItem.name+' per personaggio';}
      return 'La modifica sarà registrata sulle schede selezionate.';
    }
    function mutationPayload(action,active,reason,parameters){
      const payload={chat,action,session_id:state.session?.id||0,targets:active.map(person=>person.id),reason,...parameters};
      if(action==='health'){payload.expected=healthBaseline;}
      if(action==='item'){payload.item_id=catalogItem.id;}
      const fingerprint=JSON.stringify(payload);if(!receipts.has(fingerprint)){receipts.set(fingerprint,crypto.randomUUID());}
      payload.request_id=receipts.get(fingerprint);
      return {payload,fingerprint};
    }
    function applyMutationResult(action,result,fingerprint){
      receipts.delete(fingerprint);render(result.state,{reset:true});feedback.textContent=result.message;
      if(action==='note'){$('[data-participants-form="note"]').reset();}
      if(tab==='inventory'){void loadInventory();}
    }
    const MUTATION_TITLES={health:'Aggiorna condizioni',note:'Aggiungi nota riservata',crowns:window.AlphanoCurrency.text('Assegna Corone'),item:'Consegna oggetti',consume:'Consuma oggetto',break:'Rompi oggetto',remove:'Rimuovi oggetto'};
    async function mutate(action,parameters,label=''){
      if(busy||!state?.can_manage){return;}const active=people(),reason=$('[data-participants-reason]').value.trim();
      const error=mutationError(action,active,reason);
      if(error){feedback.textContent=error;if(reason.length<5){$('[data-participants-reason]').focus();}return;}
      const title=MUTATION_TITLES[action];
      busy=true;controls();
      try{
        const detail=label||actionDetail(action,parameters);
        const answer=await window.alphanoDialog.notice({title,message:active.map(person=>person.name).join(', '),detail:detail+'\nMotivazione: '+reason,buttonLabel:'Conferma intervento',cancelLabel:'Annulla'});if(answer!=='ack'){return;}
        const {payload,fingerprint}=mutationPayload(action,active,reason,parameters);
        feedback.textContent='Registrazione dell’intervento…';
        const response=await fetch('/api/master-participants.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf},body:JSON.stringify(payload),signal:AbortSignal.timeout(20000)}),result=await response.json();
        if(!response.ok){throw new Error(result.error||'Intervento non completato.');}
        applyMutationResult(action,result,fingerprint);
      }catch(error){feedback.textContent=error.name==='TimeoutError'?'La risposta del server non è arrivata. Riprova con gli stessi dati: la ricevuta impedisce un doppio intervento.':error.message;}
      finally{busy=false;controls();}
    }
    for(const form of root.querySelectorAll('[data-participants-form]')){form.addEventListener('submit',event=>{event.preventDefault();if(!form.reportValidity()){return;}void mutate(form.dataset.participantsForm,Object.fromEntries(new FormData(form)));});}
    for(const button of root.querySelectorAll('[data-participants-tab]')){button.addEventListener('click',()=>{tab=button.dataset.participantsTab;for(const candidate of root.querySelectorAll('[data-participants-tab]')){candidate.setAttribute('aria-pressed',String(candidate===button));}for(const pane of root.querySelectorAll('[data-participants-pane]')){pane.hidden=pane.dataset.participantsPane!==tab;}if(tab==='inventory'){void loadInventory();}if(tab==='reward'&&!$('[data-participants-catalog]').childElementCount){void loadCatalog();}});}
    $('[data-participants-refresh]').addEventListener('click',()=>void refresh(true));$('[data-participants-search]').addEventListener('input',()=>{if(state){render(state);}});
    $('[data-participants-select-all]').addEventListener('click',()=>{for(const person of state?.participants||[]){if(person.member&&!person.self){selected.add(person.id);}}resetHealth();controls();if(state){render(state);}if(tab==='inventory'){void loadInventory();}});
    $('[data-participants-clear]').addEventListener('click',()=>{selected.clear();resetHealth();if(state){render(state);}if(tab==='inventory'){void loadInventory();}});
    let searchTimer;const search=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>void loadCatalog(),250);};$('[data-catalog-search]').addEventListener('input',search);$('[data-catalog-type]').addEventListener('change',()=>void loadCatalog());$('[data-catalog-more]').addEventListener('click',()=>void loadCatalog(true));
    const pollState=async()=>{if(stopped){return;}await refresh();if(!stopped){poll=setTimeout(pollState,8000);}};
    document.addEventListener('alphano:session-state',event=>{const place=event.detail?.chat_key||event.detail?.chat;if(place===chat||!place){void refresh();}});
    window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(poll);clearTimeout(searchTimer);catalogAbort?.abort();inventoryAbort?.abort();});void pollState();
  }
  for(const root of document.querySelectorAll('[data-master-participants]')){
    if(root.dataset.initialized){continue;}root.dataset.initialized='1';
    setup(root);
  }
})();
