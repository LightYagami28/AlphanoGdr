const renderHTML=(el,markup)=>{const doc=new DOMParser().parseFromString(String(markup??''),'text/html');el.replaceChildren(...[...doc.body.childNodes].map(node=>document.importNode(node,true)));};
document.addEventListener('DOMContentLoaded', () => {
  'use strict';
  const F=window.alphanoForum, {esc,button}=F, config=F.config;
  const menu=document.getElementById('menu-list'),title=document.getElementById('panel-title'),panel=document.getElementById('panel-container');
  const modal=document.getElementById('modal'),form=modal.querySelector('form'),modalTitle=modal.querySelector('.modal-title');
  let tipo=config.tipo,showClosed=false,canModerate=false,currentThread=0,page='list',generation=0;
  async function api(params,method='GET') {
    const opts={method,headers:{'X-CSRF-Token':config.csrfToken}};
    let url='/api/discussioni/index.php';
    if(method==='GET'){url+='?'+new URLSearchParams({...params,tipo});}else {opts.body=new URLSearchParams({...params,tipo});}
    const res=await fetch(url,opts),data=await res.json();if(!res.ok){throw new Error(data.error||'Richiesta non riuscita');}return data;
  }
  function controls(item,reply=false){
    const toggleLabel=item.chiusa?'Riapri':'Chiudi',closedValue=item.chiusa?1:0;
    let html='';
    if(!reply){html+=button('Apri','open','data-id="' + (item.id) + '"','book');}
    if(item.can_edit){html+=button('Modifica','edit','data-id="' + (item.id) + '"','settings');}
    if(!reply&&item.can_moderate){html+=button(toggleLabel,'toggle','data-id="' + (item.id) + '" data-closed="' + (closedValue) + '"','shield');}
    if(item.can_edit){html+=button('Elimina','delete','data-id="' + (item.id) + '"','settings');}
    return html;
  }
  async function loadList(){
    const request=++generation;page='list';currentThread=0;title.textContent=config.channels[tipo]+(showClosed?' ┬À Archivio':'');renderHTML(panel, F.notice('CaricamentoÔÇª'));
    try {
      const data=await api({action:showClosed?'list_closed':'list_open'});if(request!==generation){return;}canModerate=!!data.can_moderate;
      const items=await Promise.all(data.threads.map(async item=>({...item,profile:await F.profile(item.author_id)})));if(request!==generation){return;}
      const emptyLabel=showClosed?'chiusa.':'presente.';
      const rows=items.map(item=>{
        const pinned=item.ontop?'In evidenza ┬À ':'';
        const state=item.chiusa?'Chiusa':'Aperta';
        return '<tr><td>' + (F.author(item.author_id,item.profile,item.author)) + '</td><td><button type="button" class="ui-text-action" data-action="open" data-id="' + (item.id) + '"><strong>' + (esc(item.oggetto)) + '</strong></button> ' + (F.newBadge(item.unread)) + '<small class="ui-cell-meta">' + (pinned) + (F.date(item.data)) + '</small></td><td>' + (Number(item.numrisp)) + ' risposte<small class="ui-cell-meta">' + (state) + '</small></td><td><div class="ui-row-actions">' + (controls(item)) + '</div></td></tr>';
      });
      renderHTML(panel, items.length?F.table(['Autore','Discussione','Attivit├á','Azioni'],rows.join(''),'Discussioni di '+config.channels[tipo]):F.notice('Nessuna discussione '+emptyLabel));
    }catch(error){if(request===generation){renderHTML(panel, F.notice(error.message,true));}}
  }
  let currentPosts=[];
  async function openThread(id){
    const request=++generation;page='thread';currentThread=Number(id);renderHTML(panel, F.notice('CaricamentoÔÇª'));
    try {
      const {thread,posts}=await api({action:'show',id});const all=[thread,...posts];
      const rendered=await Promise.all(all.map(async (item,index)=>F.post({id:item.id,userId:item.author_id,name:item.author,profile:await F.profile(item.author_id),message:item.messaggio,title:index===0?item.oggetto:'',timestamp:item.data,actions:(!thread.chiusa?button('Rispondi','reply'):'')+controls(item,true)})));
      if(request!==generation){return;}currentPosts=all;canModerate=!!thread.can_moderate;title.textContent=config.channels[tipo];
      const threadState=thread.chiusa?'Discussione chiusa':'Discussione aperta';
      const replyAction=thread.chiusa?'<p>Questa discussione ├¿ chiusa.</p>':button('Rispondi','reply','data-id="' + (id) + '"');
      const toggleLabel=thread.chiusa?'Riapri':'Chiudi',closedValue=thread.chiusa?1:0;
      const moderateAction=thread.can_moderate?button(toggleLabel,'toggle','data-id="' + (id) + '" data-closed="' + (closedValue) + '"','shield'):'';
      renderHTML(panel, '<div class="forum-thread-bar">' + (button('Torna alle discussioni','back','','book')) + '<span>' + (posts.length) + ' risposte ┬À ' + (threadState) + '</span></div><div class="forum-posts">' + (rendered.join('')) + '</div><footer class="ui-list-footer">' + (replyAction) + (moderateAction) + '</footer>');
      F.markRead('discussion',id,tipo,all);
    }catch(error){if(request===generation){renderHTML(panel, F.notice(error.message,true));}}
  }
  function openForm(action,item={}){
    modalTitle.textContent={create:'Nuova discussione',reply:'Rispondi',modify:'Modifica messaggio'}[action];
    renderHTML(form, '<input type="hidden" name="action" value="' + (action) + '">' + (action==='reply'?'<input type="hidden" name="replyID" value="' + (currentThread) + '">':'') + (action==='modify'?'<input type="hidden" name="id" value="' + (item.id) + '">':'') + '<label>Oggetto<input type="text" name="oggetto" maxlength="255" required value="' + (esc(item.oggetto|| (action==='reply'?'Re: '+(currentPosts[0]?.oggetto||'Discussione'):''))) + '"></label><label>Messaggio<textarea name="messaggio" rows="9" required>' + (esc(item.message_raw??'')) + '</textarea></label>' + (action==='create'&&canModerate?'<label class="forum-check"><input type="checkbox" name="ontop"> In evidenza</label>':'') + (action!=='modify'&&canModerate?'<label class="forum-check"><input type="checkbox" name="chiudi"> Chiudi dopo lÔÇÖinvio</label>':'') + '<div class="ui-row-actions"><button type="submit" data-icon="check">Invia</button><button type="button" data-cancel>Annulla</button></div>');
    F.editor(form);form.querySelector('[data-cancel]').onclick=()=>modal.classList.remove('open');modal.classList.add('open');form.querySelector('input[type=text]').focus();
  }
  form.onsubmit=async event=>{event.preventDefault();const submit=form.querySelector('[type=submit]');submit.disabled=true;try{const data=Object.fromEntries(new FormData(form));await api(data,'POST');modal.classList.remove('open');await(page==='thread'?openThread(currentThread):loadList());}catch(error){alert(error.message);}finally{submit.disabled=false;}};
  async function editPost(id){let item=currentPosts.find(post=>post.id===id);if(!item){item=(await api({action:'show',id})).thread;}return openForm('modify',item);}
  async function toggleThread(id,target){const closed=target.dataset.closed==='1';if(!confirm(closed?'Riaprire la discussione?':'Chiudere la discussione?')){return;}await api({action:closed?'open':'close',id},'POST');}
  async function deletePost(id){if(!confirm('Eliminare questo messaggio e le eventuali risposte?')){return;}await api({action:'delete',id},'POST');if(id===currentThread){await loadList();}}
  panel.addEventListener('click',async event=>{
    const target=event.target.closest('[data-action]');if(!target){return;}
    const id=Number(target.dataset.id),action=target.dataset.action;
    const simple={open:()=>openThread(id),back:()=>loadList(),reply:()=>openForm('reply')};
    try {
      if(simple[action]){return simple[action]();}
      if(action==='edit'){return editPost(id);}
      if(action==='toggle'){return toggleThread(id,target);}
      if(action==='delete'){return deletePost(id);}
      await(page==='thread'?openThread(currentThread):loadList());
    }catch(error){alert(error.message);}
  });
  menu.addEventListener('click',event=>{const target=event.target.closest('[data-tipo]');if(!target){return;}tipo=target.dataset.tipo;menu.querySelectorAll('[data-tipo]').forEach(button=>{button.classList.toggle('active',button===target);button.setAttribute('aria-pressed',String(button===target));});history.replaceState(null,'','?tipo='+encodeURIComponent(tipo));void loadList();});
  document.getElementById('btn-toggle-closed').onclick=event=>{showClosed=!showClosed;const target=event.currentTarget;target.textContent=showClosed?'Mostra aperti':'Mostra chiusi';target.setAttribute('aria-pressed',String(showClosed));void loadList();};
  document.getElementById('btn-create').onclick=()=>openForm('create');modal.querySelector('.close-modal').onclick=()=>modal.classList.remove('open');modal.onclick=event=>{if(event.target===modal){modal.classList.remove('open');}};const initial=Number(new URLSearchParams(location.search).get('thread'));if(initial){void openThread(initial);}else {void loadList();}
});
