// js/corporazioni/catalogo.js

const Catalogo = (function(){
  const renderHTML=(el,markup)=>{const doc=new DOMParser().parseFromString(String(markup??''),'text/html');el.replaceChildren(...[...doc.body.childNodes].map(node=>document.importNode(node,true)));};
  // ÔöÇÔöÇÔöÇ VAR DI STATO ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  let catalogo        = [];
  let catalogoPers    = [];
  let catalogoPersOrg = [];
  let riparazioni     = [];
  let tipi            = [];
  let taglie          = [];
  let organizzazioni  = [];
  let materie         = [];
  let oldtree         = 'Catalogo';
  let corpId;
  let isAdminCatalogo = false;
  let isAdminCorp     = false;
  let busy=false;
  const pendingKeys=new Map();

  // ÔöÇÔöÇÔöÇ INIT E CARICAMENTO DATI ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function init(options) {
    corpId           = options.corp;
    taglie           = options.taglie         || [];
    organizzazioni   = options.organizzazioni || [];
    materie          = options.materie        || [];
    isAdminCatalogo  = !!options.admincatalogo;
    isAdminCorp      = !!options.admincorp;
    tipi             = options.tipi           || [];

    fetchData(corpId)
    .then(data => {
    catalogo        = data.catalogo       || [];
    catalogoPers    = data.catalogoPers   || [];
    catalogoPersOrg = data.catalogoPersOrg|| [];
    riparazioni     = data.riparazioni    || [];
    tipi            = data.tipi           || [];
    isAdminCatalogo = !!data.admincatalogo;
    isAdminCorp     = !!data.admincorp;
    const repairTab=document.getElementById('Riparazioni');if(repairTab){repairTab.hidden=!data.canRepair;}
    const departments=new Map([...catalogo,...catalogoPers,...catalogoPersOrg].map(o=>[o.department||String(o.production),o.department||({15:'Armi bianche e da taglio',2:'Armi da fuoco',5:'Armature e scudi',1:'Animali',7:'Attrezzi'})[o.production]||'Altri manufatti']));document.getElementById('catalogue-department')?.replaceChildren(new Option('Tutti i reparti',''),...[...departments].map(([k,v])=>new Option(v,k)));
    setupTabs();
    for(const id of ['catalogue-search','catalogue-department']){document.getElementById(id)?.addEventListener('input',()=>Cambia(oldtree));}
    Cambia(oldtree);
    })
      .catch(err => {const panel=document.getElementById('corpo');if(panel){panel.textContent='Catalogo non disponibile: '+err.message;}});
  }

  function fetchData(corp) {
    return fetch('/api/corporazioni/catalogo.php?corp=' + (encodeURIComponent(corp)), {
      headers: { 'Accept':'application/json' }
    })
    .then(res => {
      if (!res.ok) {throw new Error(res.statusText);}
      return res.json();
    }).then(data=>{if(data.error){throw new Error(data.message || 'Catalogo non disponibile');}return data;});
  }

  // ÔöÇÔöÇÔöÇ SETUP UI ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function setupTabs() {
    ['Catalogo','CatalogoPers','CatalogoPersOrg','Riparazioni'].forEach(view=>{
      const td = document.getElementById(view);
      if (!td) {return;}
      td.onclick = () => Cambia(view);
    });
    const chk = document.getElementById('chkShowAll');
    if (chk) {chk.onchange=()=>MostraTutti(chk.checked);chk.closest('label').hidden=!isAdminCatalogo;}
    const btnAdd = document.getElementById('btnAdd');
    if (btnAdd) {btnAdd.onclick=AggiungiNuovo;btnAdd.hidden=!isAdminCatalogo;}
    const btnBack = document.getElementById('btnBack');
    if (btnBack) {btnBack.onclick = ()=>{location.href='/corporazioni/';};}
  }

  // ÔöÇÔöÇÔöÇ NAVIGAZIONE ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function Cambia(view) {

    oldtree = view;
    ['Catalogo','CatalogoPers','CatalogoPersOrg','Riparazioni'].forEach(id=>{const button=document.getElementById(id);if(button){button.classList.toggle('active',id===view);button.setAttribute('aria-selected',String(id===view));}});
    const fn = Catalogo['Carica'+view];
    if (typeof fn === 'function') {fn();}
    else {console.error('Catalogo.Carica' + (view) + ' non ├¿ una funzione');}
  }

  // ÔöÇÔöÇÔöÇ RENDERING LISTE ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function CaricaCatalogo()        { renderList(catalogo,        'tipo',         'shortDesc'); }
  function CaricaCatalogoPers()    { renderList(catalogoPers,    'destinatario', 'shortDesc'); }
  function CaricaCatalogoPersOrg() { renderList(catalogoPersOrg, 'destorg',      'shortDesc'); }
  function CaricaRiparazioni() { renderList(riparazioni,'tipo','shortDesc'); }

  function availabilityLabel(o){
    if(!o.approvato){return 'In revisione';}
    if(o.rules?.kind==='animal'){return o.available>0?o.available+' disponibili':'Allevamento esaurito';}
    return 'Su ordinazione';
  }
  function buildRuleFacts(o,rules){
    if(rules.kind==='animal'){return '<dt>Allevamento</dt><dd>' + (Number(o.available)) + ' disponibili ┬À addestramento ' + (Number(rules.training||0)) + '/3</dd><dt>Carico massimo</dt><dd>' + (Number(rules.capacity||0)) + ' kg</dd>';}
    if(rules.kind==='container'){return '<dt>Capacit├á</dt><dd>' + (Number(rules.capacity)) + ' kg</dd>';}
    if(rules.kind){return '<dt>Dosi / cariche</dt><dd>' + (Number(rules.charges)) + '</dd><dt>Ricarica / recupero</dt><dd>' + (Number(rules.recharge_rounds)) + ' / ' + (Number(rules.cooldown_rounds)) + ' turni</dd>';}
    return '';
  }
  function renderList(arr, groupKey, labelKey) {
    const lista = document.getElementById('lista'),
          corpo = document.getElementById('corpo');
    renderHTML(lista, '');
    renderHTML(corpo, '');
    if (!arr.length) {
      const count=document.getElementById('catalogue-count');if(count){count.textContent='0 articoli';}
      renderHTML(lista, `<p class="ui-empty">Nessun oggetto in questa sezione.</p>`);
      return;
    }
    const source=arr,search=(document.getElementById('catalogue-search')?.value||'').toLocaleLowerCase(),department=document.getElementById('catalogue-department')?.value||'';
    arr=arr.filter(o=>(!department||(o.department||String(o.production))===department)&&(!search||(o.shortDesc+' '+o.tipo+' '+o.desc).toLocaleLowerCase().includes(search)));
    const count=document.getElementById('catalogue-count');if(count){count.textContent=arr.length+' articoli';}
    if(!arr.length){renderHTML(lista, '<p class="ui-empty">Nessun articolo corrisponde ai filtri.</p>');return;}
    const departments={15:'Armi bianche e da taglio',2:'Armi da fuoco',5:'Armature e scudi'};
    const map = {};
    arr.forEach(o=>{
      const k = groupKey==='tipo'?(o.department||departments[o.production]||o[groupKey]||'Altri manufatti'):o[groupKey]||'';
      if(!map[k]){map[k]=[];}
      map[k].push(o);
    });
    let html = '<div class="catalogue-index">';
    for (const g in map) {
      html += '<section class="catalogue-category"><h2>' + (escapeHtml(g||'Oggetti')) + '</h2><ul>';
      map[g]
        .sort((a,b)=>a[labelKey].localeCompare(b[labelKey]))
        .forEach(o=>{
          const idx = source.indexOf(o);
          html += '<li><button type="button" data-ui-no-icon data-catalogue-item="' + (idx) + '">' + (o.immagine?'<img src="' + (escapeHtml(o.immagine)) + '" alt="" loading="lazy">':'') + '<span><strong>' + (escapeHtml(o[labelKey])) + '</strong><small>' + (escapeHtml(o.tipo||'')) + ' ┬À ' + (Number(o.peso||0)) + ' kg</small><span class="catalogue-quality">' + (escapeHtml(o.quality||'')) + ' ┬À ' + (availabilityLabel(o)) + '</span></span><span aria-hidden="true">ÔÇ║</span></button></li>';
        });
      html += '</ul></section>';
    }
    html += '</div>';
    renderHTML(lista, html);
    lista.onclick=event=>{const button=event.target.closest('[data-catalogue-item]');if(!button){return;}lista.querySelectorAll('[aria-current]').forEach(el=>el.removeAttribute('aria-current'));button.setAttribute('aria-current','true');Mostra(Number(button.dataset.catalogueItem));};
    renderHTML(corpo, '<p class="ui-empty">Seleziona un oggetto per consultarne dettagli e ordinazione.</p>');
  }

  // ÔöÇÔöÇÔöÇ MOSTRA DETTAGLI ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function Mostra(idx) {
    if(oldtree==='Riparazioni'){if(riparazioni[idx]){renderRepair(riparazioni[idx]);}return;}
    let rows=catalogo;
    if(oldtree==='CatalogoPers'){rows=catalogoPers;}
    else if(oldtree==='CatalogoPersOrg'){rows=catalogoPersOrg;}
    if(rows[idx]){renderDetails(rows[idx]);}
  }
  function MostraRip(idx) { renderRepair(riparazioni[idx]); }

  function materialsTable(values) {
    const rows=Object.entries(values || {}).filter(([,quantity])=>Number(quantity)>0);
    return rows.length ? '<div class="ui-table-scroll" tabindex="0" role="region" aria-label="Materie prime necessarie"><table class="ui-data-table"><thead><tr><th scope="col">Materia</th><th scope="col">Quantit├á</th></tr></thead><tbody>' + (rows.map(([material,quantity])=>'<tr><td>' + (escapeHtml(material)) + '</td><td>' + (Number(quantity)) + '</td></tr>').join('')) + '</tbody></table></div>' : '<p>Nessuna materia prima richiesta.</p>';
  }
  function renderDetails(o) {
    const panel=document.getElementById('corpo');
    const rules=o.rules||{};const ruleFacts=buildRuleFacts(o,rules);
    const orderForm=o.approvato ? '<form id="catalogo-order-' + (o.id) + '" class="ui-form" method="post" action="/api/corporazioni/catalogo.php" onsubmit="event.preventDefault();Catalogo.OrdinaObj(' + (o.id) + ',this);return false" aria-label="Ordina ' + (escapeHtml(o.shortDesc)) + '">\n      <input type="hidden" name="azione" value="ordina"><input type="hidden" name="id" value="' + (o.id) + '"><input type="hidden" name="corp" value="' + (corpId) + '">\n      <label for="catalogo-order-quantity-' + (o.id) + '">Quantit├á</label>\n      <input id="catalogo-order-quantity-' + (o.id) + '" name="qta" type="number" min="1" max="10000" step="1" value="1" required aria-describedby="catalogo-order-feedback-' + (o.id) + '">\n      <button type="submit">Ordina oggetto</button>\n      <p id="catalogo-order-feedback-' + (o.id) + '" class="notice" role="status" aria-live="polite" hidden></p>\n    </form>' : '<p>Oggetto non ancora approvato.</p>';
    let actions='';
    if(isAdminCatalogo || isAdminCorp){actions+='<button type="button" onclick="Catalogo.ModificaObj(' + (o.id) + ')">Modifica</button>';}
    if(isAdminCorp){actions+='<button type="button" onclick="Catalogo.CancellaObj(' + (o.id) + ')">Ritira dal catalogo</button>';}
    renderHTML(panel, '<article class="ui-form catalogue-detail"><button type="button" class="catalogue-back" data-ui-no-icon onclick="document.getElementById(\'lista\').scrollIntoView({block:\'start\'})">ÔåÉ Torna alla vetrina</button><h2>' + (escapeHtml(o.shortDesc)) + '</h2><div class="equipment-detail ui-reading"><img src="' + (escapeHtml(o.immagine || '/assets/placeholder.svg')) + '" alt="' + (escapeHtml(o.shortDesc)) + '" onload="Catalogo.Resize(this)"><p>' + (escapeHtml(o.desc).replaceAll('\n','<br>')) + '</p></div><dl class="catalogue-facts">' + (ruleFacts) + '<dt>Tipo</dt><dd>' + (escapeHtml(o.tipo)) + '</dd><dt>Taglia</dt><dd>' + (escapeHtml(o.taglia)) + '</dd><dt>Peso</dt><dd>' + (Number(o.peso)) + ' kg</dd><dt>Qualit├á e durata</dt><dd>' + (escapeHtml(o.quality)) + ' ┬À ' + (Number(o.durata)) + '</dd>' + (o.destinatario ? '<dt>Destinatario</dt><dd>' + (escapeHtml(o.destinatario)) + '</dd>' : '') + (o.destorg ? '<dt>Organizzazione</dt><dd>' + (escapeHtml(o.destorg)) + '</dd>' : '') + (o.lavorazione ? '<dt>Lavorazione</dt><dd>' + (escapeHtml(o.lavorazione)) + '</dd>' : '') + '</dl><h3>Materie per pezzo</h3>' + (materialsTable(o.materie)) + '<p>La corporazione proporr├á prezzo e quantit├á. Il pagamento avviene alla consegna.</p>' + (orderForm) + '<div class="ui-actions">' + (actions) + '</div></article>');
    if(matchMedia('(max-width:760px)').matches){panel.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion:reduce)').matches?'auto':'smooth',block:'start'});}
  }
  function renderRepair(o) {
    renderHTML(document.getElementById('corpo'), '<article class="ui-form"><h2>Ripara ' + (escapeHtml(o.shortDesc)) + '</h2><p>Danneggiamento: ' + (Number(o.damage)) + '%. La riparazione consuma queste materie dal magazzino della tua corporazione.</p>' + (materialsTable(o.materie)) + '<button type="button" onclick="Catalogo.RiparaObj(' + (o.id) + ')">Ripara oggetto</button></article>');
  }
  function OrdinaObj(id,form=document.getElementById('catalogo-order-'+id)) {
    if(!form || form.dataset.completed==='true'){return;}
    const input=form.elements.namedItem('qta');
    const raw=input.value;
    if(!/^\d+$/.test(raw) || Number(raw)<1 || Number(raw)>10000){
      input.setAttribute('aria-invalid','true');
      showFeedback('Inserisci una quantit├á intera tra 1 e 10000.',true,form);window.alphanoCommerce?.notify('Inserisci una quantit├á intera tra 1 e 10000.',true);
      input.focus();return;
    }
    input.removeAttribute('aria-invalid');
    return postAction({azione:'ordina',id,qta:Number(raw)},form);
  }
  function RiparaObj(id) {
    return postAction({azione:'ripara',id});
  }
  function AggiungiNuovo() {
    buildForm({
      id:0, shortDesc:'', desc:'', tipo:'', taglia:'', peso:0,
      mani:1, sacro:false, restore:0, lavorazione:'',
      destinatario:'', destorg:0, materie:{}, approvato:true
    });
  }
  function ModificaObj(id) {
    let o = catalogo.find(x=>x.id===id)
         || catalogoPers.find(x=>x.id===id)
         || catalogoPersOrg.find(x=>x.id===id);
    if (o) {buildForm(o);}
  }
  async function CancellaObj(id) {
    if (await window.alphanoDialog.notice({title:'Ritira dal catalogo',message:'Vuoi ritirare questo oggetto dalla vetrina?',buttonLabel:'Ritira',cancelLabel:'Indietro'})!=='ack') {return;}
    return postAction({ azione:'cancellaobj', id });
  }
  function MostraTutti(v) {
    Cambia(oldtree);
  }

  // ÔöÇÔöÇÔöÇ FORM DINAMICO ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function buildForm(o) {
    o = {...o, tipo:o.tipoId || o.tipo, taglia:o.tagliaId ?? o.taglia, destorg:o.destorgId ?? o.destorg};
    //  Se arriva o.tipo come stringa, trasformalo in ID
    if (typeof o.tipo === 'string') {
      const found = tipi.find(t=>t.nome===o.tipo);
      o.tipo = found ? found.id : tipi[0]?.id || 0;
    }
    // Garantisco che tipo e taglia siano interi
    if (!Number.isInteger(o.tipo)) {o.tipo = tipi.length>0 ? tipi[0].id : 0;}
    if (!Number.isInteger(o.taglia)) {o.taglia = 0;}

    const corpo = document.getElementById('corpo');
    const options=(list,current)=>list.map((item,index)=>{const id=typeof item==='object'?item.id:index;const label=typeof item==='object'?item.nome:item;return '<option value="' + (Number(id)) + '"' + (Number(id)===Number(current)?' selected':'') + '>' + (escapeHtml(label)) + '</option>';}).join('');
    const html='<form enctype="multipart/form-data" id="objForm" class="ui-form catalogue-edit" data-ui-fields="true">\n      <input type="hidden" name="azione" value="' + (o.id?'modifica':'aggiungi') + '"><input type="hidden" name="id" value="' + (Number(o.id||0)) + '"><input type="hidden" name="corp" value="' + (corpId) + '">\n      <h2>' + (o.id?'Modifica oggetto':'Nuovo oggetto') + '</h2>\n      <div class="ui-form-fields">\n      <label>Nome<input name="shortDesc" value="' + (escapeHtml(o.shortDesc||'')) + '" maxlength="255" required></label>\n      <label>Immagine<input type="file" name="immagine" accept="image/*"><input type="hidden" name="imgurl" value="' + (escapeHtml(o.immagine||'')) + '"></label>\n      <label class="catalogue-full">Descrizione<textarea name="desc" rows="6" required>' + (escapeHtml(o.desc||'')) + '</textarea></label>\n      <label>Tipo<select name="tipo" required>' + (options(tipi,o.tipo)) + '</select></label>\n      <label>Taglia<select name="taglia" required>' + (options(taglie,o.taglia)) + '</select></label>\n      <label>Peso (kg)<input type="number" name="peso" min="0" step="0.1" value="' + (Number(o.peso||0)) + '" required></label>\n      <label>Mani<select name="mani"><option value="1"' + (Number(o.mani)===1?' selected':'') + '>Una mano</option><option value="2"' + (Number(o.mani)===2?' selected':'') + '>Due mani</option></select></label>\n      <label>Ripristino PF<input type="number" name="restore" value="' + (Number(o.restore||0)) + '" min="0"></label>\n      <label>Lavorazione<input name="lavorazione" value="' + (escapeHtml(o.lavorazione||'')) + '"></label>\n      <label><input type="checkbox" name="sacro"' + (o.sacro?' checked':'') + '> Oggetto sacro</label>\n      <label><input type="checkbox" name="pers"' + ((o.destinatario||o.destorg)?' checked':'') + '> Personalizzato</label>\n      <label>Destinatario<input name="destinatario" value="' + (escapeHtml(o.destinatario||'')) + '"></label>\n      <label>Organizzazione destinataria<select name="destorg"><option value="0">Nessuna</option>' + (options(organizzazioni,o.destorg)) + '</select></label>\n      </div>\n      <fieldset><legend>Materie prime per pezzo</legend><div class="catalogue-materials">' + (materie.map(m=>'<label>' + (escapeHtml(m)) + '<input type="number" name="' + (escapeHtml(m)) + '" value="' + (Number(o.materie?.[m]||0)) + '" min="0"></label>').join('')) + '</div></fieldset>\n      <div class="ui-row-actions"><button type="submit">Salva oggetto</button><button type="button" onclick="Catalogo.Cambia(Catalogo.currentView())">Annulla</button></div>\n    </form>';
    renderHTML(corpo, html);
    corpo.querySelector('form').onsubmit=event=>{event.preventDefault();submitForm();};
  }

  function submitForm() {
    const form = document.getElementById('objForm');
    const data = new FormData(form);
    fetch('/api/corporazioni/catalogo.php', {
      method:'POST',
      body:data
    })
    .then(r=>r.json())
    .then(result=> {
      if (result.success === false || result.error) {throw new Error(result.msg || result.message || 'Operazione non riuscita');}
      return fetchData(corpId);
    })
    .then(data=>{
      catalogo        = data.catalogo         || [];
      catalogoPers    = data.catalogoPers     || [];
      catalogoPersOrg = data.catalogoPersOrg  || [];
      riparazioni     = data.riparazioni      || [];
      Cambia(oldtree);
    })
    .catch(error => showFeedback(error.message || 'Salvataggio non riuscito. Riprova.',true));
  }

  // ÔöÇÔöÇÔöÇ HELPERS ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  function showFeedback(message,error=false,form=null,orderLink=false) {
    const feedback=form?.querySelector('[role="status"]') || document.getElementById('catalogo-feedback');
    if(!feedback){return;}
    feedback.hidden=false;
    feedback.classList.toggle('error',error);
    feedback.textContent=message;
    if(orderLink){const link=document.createElement('a');link.href='/trasferimenti/';link.textContent=' Vai ai miei ordini';feedback.appendChild(link);}
  }
  async function postAction(payload,form=null) {
    if(busy){return;}
    busy=true;
    payload.corp=corpId;
    const identity=JSON.stringify(payload);
    if(!pendingKeys.has(identity)){pendingKeys.set(identity,crypto.randomUUID());}
    payload._request_key=pendingKeys.get(identity);
    document.querySelectorAll('#corpo button').forEach(button=>button.disabled=true);
    showFeedback(payload.azione==='ordina' ? 'Invio dellÔÇÖordineÔÇª' : 'Operazione in corsoÔÇª',false,form);
    try {
      const response=await fetch('/api/corporazioni/catalogo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const result=await response.json();
      if(!response.ok || result.success===false || result.error) {
        if(response.status<500){pendingKeys.delete(identity);}
        throw new Error(result.msg || result.message || 'Operazione non riuscita. Riprova.');
      }
      pendingKeys.delete(identity);
      if(payload.azione==='ordina'){
        if(form){form.dataset.completed='true';form.elements.namedItem('qta').readOnly=true;}
        showFeedback(result.msg || 'Ordine creato correttamente.',false,form,true);window.alphanoCommerce?.changed();window.alphanoCommerce?.notify(result.msg||'Ordine inviato.',false,result.orderId);
      }else{
        const data=await fetchData(corpId);
        catalogo=data.catalogo || [];catalogoPers=data.catalogoPers || [];catalogoPersOrg=data.catalogoPersOrg || [];riparazioni=data.riparazioni || [];
        Cambia(oldtree);
        showFeedback(result.msg || 'Operazione completata.');
      }
      return result;
    } catch(error) {const message=['TypeError','SyntaxError'].includes(error.name)?'Connessione interrotta. Puoi riprovare lÔÇÖinvio.':(error.message||'Operazione non riuscita. Riprova.');showFeedback(message,true,form);window.alphanoCommerce?.notify(message,true);}
    finally {
      busy=false;
      document.querySelectorAll('#corpo button').forEach(button=>button.disabled=!!form && form.dataset.completed==='true' && form.contains(button));
    }
  }

  function Resize(img){
    img.decoding='async';
  }

  function escapeHtml(s){
    return String(s||'')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#39;');
  }

  // ÔöÇÔöÇÔöÇ EXPORT ÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇÔöÇ
  return {currentView:()=>oldtree,
    init,
    Cambia,
    CaricaCatalogo, CaricaCatalogoPers, CaricaCatalogoPersOrg, CaricaRiparazioni,
    Mostra, MostraRip,
    OrdinaObj, RiparaObj, AggiungiNuovo, ModificaObj, CancellaObj, MostraTutti,
    Resize,
    submitForm
  };
})();
 
