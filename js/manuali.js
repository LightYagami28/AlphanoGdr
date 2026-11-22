const renderHTML=(el,markup)=>{const doc=new DOMParser().parseFromString(String(markup??''),'text/html');el.replaceChildren(...[...doc.body.childNodes].map(node=>document.importNode(node,true)));};
(() => {
  'use strict';
  const config=JSON.parse(document.getElementById('manuals-config')?.textContent||'{}');
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const byId=id=>document.getElementById(id),btn=(text,act,id=0)=>'<button type="button" class="action-btn" data-action="' + (act) + '" data-id="' + (id) + '">' + (esc(text)) + '</button>';
  const base=config.baseUrl||'/regole/';
  let data={groups:[],rules:[],images:[],audiences:{}},active=null,requestNumber=0,quill,returnFocus=null;
  async function api(params={}) {
    const response=await fetch(config.public?config.apiUrl+'&'+new URLSearchParams(params):config.apiUrl,config.public?{cache:'no-store'}:{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':config.csrfToken},body:JSON.stringify(params)});
    const value=await response.json();if(!response.ok||!value.success){throw new Error(value.error||'Operazione non riuscita');}return value;
  }
  function toast(text,error=false){const el=byId('toast');el.textContent=text;el.dataset.state=error?'error':'success';el.classList.add('show');clearTimeout(toast.timer);toast.timer=setTimeout(()=>el.classList.remove('show'),4500);}
  function setRoute(rule) {const url=new URL(location.href);url.searchParams.delete('section');url.searchParams.delete('chapter');if(rule){url.searchParams.set(rule.key?'chapter':'section',rule.key||rule.id);}history.replaceState(null,'',url);}
  function accept(value){data=value;const select=byId('manual-group'),old=select.value;renderHTML(select, '<option value="0">Tutti i capitoli</option>'+data.groups.map(g=>'<option value="' + (g.id) + '">' + (esc(g.nome)) + '</option>').join(''));select.value=old||'0';if(!select.value){select.value='0';}}
  function renderIndex(updateRoute=true) {
    ++requestNumber;active=null;byId('manual-detail').hidden=true;const index=byId('manual-index');index.hidden=false;if(updateRoute){setRoute(null);}
    const query=byId('manual-search').value.trim().toLocaleLowerCase('it'),group=Number(byId('manual-group').value),kind=byId('manual-audience').value;
    const selected=data.rules.filter(r=>(!group||r.sezione===group)&&(!query||((r.oggetto) + ' ' + (r.summary) + ' ' + (data.groups.find(g=>g.id===r.sezione)?.nome||'')).toLocaleLowerCase('it').includes(query))&&(kind==='all'||(kind==='public'&&r.managed&&r.audience==='public')||(kind==='staff'&&r.audience!=='public')||(kind==='legacy'&&!r.managed)));
    byId('manual-status').textContent=(selected.length) + ' ' + (selected.length===1?'capitolo disponibile':'capitoli disponibili') + ' ┬À ' + (config.public?'Consultazione libera, anche senza account.':'Le guide riservate compaiono secondo i tuoi incarichi.');
    renderHTML(index, data.groups.filter(g=>selected.some(r=>r.sezione===g.id)||(group===g.id&&config.adminAccademia)).map(g=>{
      const chapters=selected.filter(r=>r.sezione===g.id),editable=config.adminAccademia&&data.rules.filter(r=>r.sezione===g.id).every(r=>r.can_edit);
      return '<section class="manual-collection' + (g.key==='personaggi-storici'?' manual-historical-collection':'') + '"><header><div><span class="manual-eyebrow">' + (g.legacy?'ARCHIVIO ┬À APPROFONDIMENTI':'LA GUIDA DI ALPHANO') + '</span><h2>' + (esc(g.nome)) + '</h2></div><span class="manual-counter">' + (chapters.length) + ' ' + (chapters.length===1?'capitolo':'capitoli') + '</span>' + (editable?'<div class="ui-row-actions">' + (btn('Nuovo capitolo','new-rule',g.id)) + (btn('Rinomina','edit-group',g.id)) + (g.legacy?btn('Elimina raccolta','delete-group',g.id):'') + '</div>':'') + '</header>' + (g.legacy?'<p class="manual-archive-note">Testi di ambientazione e materiali precedenti. Per costi e procedure attuali consulta i capitoli della guida centrale.</p>':'') + '<div class="manual-card-grid">' + (chapters.map(r=>{
        let badge='GUIDA DI GIOCO';
        if(r.audience!=='public'){badge='RISERVATO ┬À '+esc(r.audience_label);}
        else if(g.legacy){badge='APPROFONDIMENTO';}
        return '<a class="manual-chapter-card" href="' + (base) + '?' + (r.key?'chapter='+encodeURIComponent(r.key):'section='+r.id) + '" data-action="read" data-id="' + (r.id) + '"><img src="' + (esc(r.image)) + '" alt="" width="640" height="360" loading="lazy"><div class="manual-card-copy"><span class="manual-eyebrow">' + (badge) + '</span><h3>' + (esc(r.oggetto)) + '</h3><p>' + (esc(r.summary)) + '</p><span class="manual-card-footer">Leggi il capitolo <span aria-hidden="true">Ôåù</span></span></div></a>';}).join('')) + '</div></section>';
    }).join('')||'<p class="ui-empty">Nessun capitolo corrisponde ai filtri. Prova un altro termine o azzera i filtri.</p>');
  }
  function buildRuleMarkup(r,group,related,position){
    const pdfQuery=r.key?'?chapter='+encodeURIComponent(r.key):'';
    const deleteAction=r.managed?'':btn('Elimina capitolo','delete-rule',r.id);
    return '<nav class="manual-reading-toolbar" aria-label="Navigazione del capitolo">' + (btn('ÔåÉ Tutti i capitoli','index')) + '<span>' + (esc(group?.nome||'Manuali')) + '</span>' + (config.public?'<a class="action-btn" href="/#registrazione">Crea il tuo personaggio</a>':'<a class="action-btn" target="_blank" rel="noopener" href="/regole/pdf.php' + (pdfQuery) + '">Scarica PDF</a>') + (r.can_edit?btn('Modifica capitolo','edit-rule',r.id):'') + '</nav><header class="manual-chapter-hero' + (group?.key==='personaggi-storici'&&r.key!=='personaggi-storici'?' manual-character-hero':'') + '"><img src="' + (esc(r.image)) + '" alt="" width="1440" height="810"><div><span class="manual-eyebrow">' + (r.audience==='public'?'ALPHANO ┬À GUIDA AL GIOCO':'GUIDA RISERVATA ┬À '+esc(r.audience_label)) + '</span><h1>' + (esc(r.oggetto)) + '</h1><p>' + (esc(r.summary)) + '</p></div></header><div class="manual-reading-layout"><aside class="manual-reading-nav"><h2>In questo capitolo</h2><nav id="manual-toc" aria-label="Indice del capitolo"></nav>' + (r.can_edit?'<details><summary>Redazione</summary><div class="manual-editor-actions">' + (btn('Sposta su','up',r.id)) + (btn('Sposta gi├╣','down',r.id)) + (deleteAction) + '</div></details>':'') + '</aside><div class="manual-chapter-main"><div id="panel-content" class="manual-reading">' + (r.spiegazione) + '</div>' + (r.screens.length?'<section class="manual-screens"><h2>Guarda come si fa</h2>' + (r.screens.map(s=>'<figure><a href="' + (esc(s.url)) + '" target="_blank" rel="noopener" aria-label="Ingrandisci: ' + (esc(s.title)) + '"><img src="' + (esc(s.url)) + '" alt="' + (esc(s.title)) + '" loading="lazy"></a><figcaption><strong>' + (esc(s.title)) + '</strong><br>' + (esc(s.caption)) + ' Clicca per ingrandire.</figcaption></figure>').join('')) + '</section>':'') + (r.links.length?'<nav class="manual-next-steps" aria-label="Prossimi passi"><h2>Prosegui nel gioco</h2>' + (r.links.map(l=>'<a class="action-btn" href="' + (esc(l.url||l[1])) + '">' + (esc(l.label||l[0])) + ' ÔåÆ</a>').join('')) + '</nav>':'') + '<footer class="manual-chapter-paging">' + (position>0?btn('ÔåÉ '+related[position-1].oggetto,'read',related[position-1].id):'<span></span>') + (position<related.length-1?btn(related[position+1].oggetto+' ÔåÆ','read',related[position+1].id):'') + '</footer></div></div>';
  }
  async function showRule(id,focus=true) {
    const ticket=++requestNumber;byId('manual-index').hidden=true;const article=byId('manual-detail');article.hidden=false;renderHTML(article, '<p class="ui-empty" role="status">Apertura del capitoloÔÇª</p>');
    try{
      const result=await api({azione:'read',id});if(ticket!==requestNumber){return;}const r=result.rule;active=r;setRoute(r);
      const group=data.groups.find(g=>g.id===r.sezione),related=data.rules.filter(x=>x.sezione===r.sezione),position=related.findIndex(x=>x.id===r.id);
      renderHTML(article, buildRuleMarkup(r,group,related,position));
      const headings=[...byId('panel-content').querySelectorAll('h2,h3')];renderHTML(byId('manual-toc'), headings.map((h,i)=>{h.id='manual-paragraph-'+i;return '<a href="#' + (h.id) + '" data-scroll="' + (h.id) + '">' + (esc(h.textContent)) + '</a>';}).join(''));
      byId('manual-status').textContent=(r.audience_label) + ' ┬À Aggiornato il ' + (new Date(r.ultimamodifica*1000).toLocaleDateString('it-IT'));
      if(focus){article.focus({preventScroll:true});}article.scrollIntoView({block:'start'});
    }catch(error){if(ticket===requestNumber){renderHTML(article, '<p class="ui-empty" role="alert">' + (esc(error.message)) + '</p>' + (btn('Torna alla biblioteca','index')));}}
  }
  function closeModal(){byId('modal').classList.remove('open');returnFocus?.focus();}
  function editor(action,rule={}) {
    returnFocus=document.activeElement;const group=action.endsWith('group'),creating=action.startsWith('insert'),modal=byId('modal'),body=byId('modal-body');
    renderHTML(body, '<h2>' + (creating?'Nuova':'Modifica') + ' ' + (group?'raccolta':'capitolo') + '</h2><form id="manual-editor"><label>' + (group?'Nome della raccolta':'Titolo') + '<input name="title" required maxlength="255" value="' + (esc(group?rule.nome||'':rule.oggetto||'')) + '"></label>' + (!group?'<label>Presentazione breve<textarea name="summary" maxlength="1200" rows="3">' + (esc(rule.summary||'')) + '</textarea></label><div class="manual-editor-fields"><label>Visibilit├á<select name="audience">' + (Object.entries(data.audiences).filter(([k])=>!rule.managed||rule.audience==='public'||k===rule.audience).map(([k,v])=>'<option value="' + (k) + '"' + (k===(rule.audience||'public')?' selected':'') + '>' + (esc(v)) + '</option>').join('')) + '</select></label><label>Illustrazione<select name="image">' + (data.images.map(i=>'<option value="' + (esc(i.path)) + '"' + (i.path===rule.image?' selected':'') + '>' + (esc(i.name)) + '</option>').join('')) + '</select></label></div><img class="manual-editor-preview" src="' + (esc(rule.image||data.images[0]?.path)) + '" alt="Anteprima illustrazione"><label>Contenuto</label><div id="quill-editor"></div><p class="manual-editor-note">Le tabelle automatiche, come {{live:progression}}, leggono le regole attuali. Conserva questi riferimenti quando modifichi il testo.</p>':'') + '<div class="ui-row-actions"><button type="submit">Salva modifiche</button><button type="button" data-cancel>Annulla</button></div></form>');
    if(!group){quill=new Quill(byId('quill-editor'),{theme:'snow'});quill.clipboard.dangerouslyPasteHTML(rule.source||'');body.querySelector('[name=image]').onchange=e=>body.querySelector('.manual-editor-preview').src=e.target.value;}
    body.querySelector('[data-cancel]').onclick=closeModal;
    body.querySelector('form').onsubmit=async e=>{
      e.preventDefault();const form=e.currentTarget,values=Object.fromEntries(new FormData(form)),submit=form.querySelector('[type=submit]');submit.disabled=true;
      const groupPayload=creating?{nome_gruppo:values.title}:{gruppo_edit:rule.id,nome_edit_gruppo:values.title};
      const sectionPayload=creating?{gruppo_select:rule.groupId,titolo_sezione:values.title}:{sezione_edit:rule.id,titolo_edit:values.title};
      const payload=group?groupPayload:{...values,spiegazione_edit:quill.root.innerHTML,revision:rule.revision,...sectionPayload};
      try{accept(await api({azione:action,...payload}));closeModal();if(!group&&!creating){await showRule(rule.id);}else {renderIndex();}toast('Modifiche salvate e registrate nello storico.');}catch(error){toast(error.message,true);}finally{submit.disabled=false;}
    };
    modal.classList.add('open');body.querySelector('input').focus();
  }
  async function followChapterLink(e){
    const chapterLink=e.target.closest('a[href]');
    if(!chapterLink||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey){return false;}
    const url=new URL(chapterLink.href,location.href);
    if(url.origin!==location.origin||url.hash||!['/manuali/','/regole/'].includes(url.pathname)){return false;}
    const chapter=data.rules.find(r=>r.key&&r.key===url.searchParams.get('chapter'));
    if(!chapter){return false;}
    e.preventDefault();await showRule(chapter.id);return true;
  }
  async function adminAction(action,id){
    if(action==='new-rule'){return editor('insert-section',{groupId:id});}
    if(action==='edit-group'){return editor('update-group',data.groups.find(g=>g.id===id));}
    if(action==='edit-rule'){const r=(await api({azione:'read',id})).rule;return editor('update-section',r);}
    if(action==='up'||action==='down'){accept(await api({azione:'move-section',id,dir:action,revision:active.revision}));return showRule(id);}
    if(action==='delete-rule'||action==='delete-group'){
      const message=action==='delete-rule'?'Eliminare questo capitolo?':'Eliminare la raccolta e i capitoli contenuti?';
      if(!confirm(message)){return;}
      const operation=action==='delete-rule'?'delete-section':'delete-group';
      accept(await api({azione:operation,id,revision:active?.revision||''}));renderIndex();
    }
  }
  async function dispatch(e) {
    if(await followChapterLink(e)){return;}
    const scroll=e.target.closest('[data-scroll]');if(scroll){e.preventDefault();byId(scroll.dataset.scroll)?.scrollIntoView({behavior:'smooth'});return;}
    const target=e.target.closest('[data-action]');if(!target){return;}e.preventDefault();const id=Number(target.dataset.id),action=target.dataset.action;
    try{
      if(action==='index'){return renderIndex();}if(action==='read'){return showRule(id);}
      if(!config.adminAccademia){return;}
      return await adminAction(action,id);
    }catch(error){toast(error.message,true);}
  }
  document.addEventListener('DOMContentLoaded',async()=>{
    document.querySelector('.manual-library').addEventListener('click',dispatch);
    document.querySelector('.manual-filters').onsubmit=e=>e.preventDefault();
    ['manual-search','manual-group','manual-audience'].forEach(id=>byId(id).addEventListener(id==='manual-search'?'input':'change',()=>renderIndex()));
    document.querySelector('.manual-filters').addEventListener('reset',()=>setTimeout(()=>renderIndex(),0));
    if(config.adminAccademia){byId('create-group-btn').onclick=()=>editor('insert-group');byId('modal').querySelector('.close').onclick=closeModal;byId('modal').onclick=e=>{if(e.target===byId('modal')){closeModal();}};document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeModal();}});}
    try{accept(await api());renderIndex(false);const params=new URLSearchParams(location.search),key=params.get('chapter'),id=Number(params.get('section')),initial=data.rules.find(r=>key?r.key===key:r.id===id);if(initial){void showRule(initial.id,false);}else if(key||id){byId('manual-status').textContent='Il capitolo richiesto non ├¿ disponibile per il tuo account.';setRoute(null);}}catch(error){renderHTML(byId('manual-index'), '<p class="ui-empty" role="alert">' + (esc(error.message)) + '</p>');}
  });
})();
