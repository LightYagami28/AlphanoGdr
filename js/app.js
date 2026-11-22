import {patchLiveList} from './live-list.js';
import {renderMetamorphosis,updateGameClocks} from './game-status.js';
import {presencePlaceHeading,presenceShowsNpcs,presenceSaveNpcs,presenceGameAction} from './presence-view.js?v=20261031-invites-visible';
import {initFortuneSidebar} from './fortune-sidebar.js?v=20261031-configured-catalog';
import {presentCivicInvitation} from './civic-invitation.js?v=20261027-offices';
import {presentWelcome} from './welcome-message.js?v=20261026';
// js/app.js — SPA light con grouping dinamico, panning immagini oversize,
// aggiorna solo header tabs senza ricaricare la mappa e usa iframe per la mappa
/* global fetch, document, window */

document.addEventListener('DOMContentLoaded', () => {
  const mobileViewport=window.matchMedia('(max-width:900px), (max-width:1100px) and (max-height:500px)');
  window.alphanoSceneFocus=active=>{document.body.classList.toggle('has-focused-chat',!!active);if(!active){document.body.classList.remove('mobile-game-menu');}};
  window.alphanoMobileMenu=()=>window.alphanoMobile?window.alphanoMobile.menu():document.body.classList.toggle('mobile-game-menu');
  const mobileReturn=document.createElement('button');mobileReturn.type='button';mobileReturn.className='mobile-return-chat';mobileReturn.textContent='Torna alla chat';mobileReturn.addEventListener('click',()=>document.body.classList.remove('mobile-game-menu'));document.querySelector('.topbar')?.append(mobileReturn);
  window.alphanoToggleReading=()=>document.querySelector('.game-content')?.classList.toggle('chat-wide');
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const avatarFallback = '/assets/avatar.svg';
  /* === Costanti DOM ===================================================== */
  const script        = document.getElementById('app-script');
  const idpg          = script?.dataset.idpg;
  const menuGroups    = document.getElementById('menu-groups');
  const quicks        = document.querySelectorAll('.quicklinks-box a');
  const avatar        = document.getElementById('profile-avatar');
  const pname         = document.getElementById('profile-name');
  const psub          = document.getElementById('profile-sub');
  const weatherW      = document.getElementById('weather');
  const newsUl        = document.getElementById('news-list');
  const clearNewsBtn  = document.getElementById('clear-news');
  const presentiCount = document.getElementById('presenti-count');
  const presentiList  = document.getElementById('presenti-list');
  const mapBox        = document.getElementById('map');
  const sidebar       = document.querySelector('.sidebar');
  const sidebarToggle = document.getElementById('sidebar-toggle');

  /* ========================================================================
     1. Utility: carica un URL dentro #map tramite iframe isolato
  ======================================================================== */
  let sceneFrame = null;
  let workspace = null;
  function loadIntoMap(url) {
    if (!mapBox) {return;}
    mapBox.innerHTML = '';
    sceneFrame = document.createElement('iframe');
    sceneFrame.className = 'scene-frame';
    sceneFrame.setAttribute('src', url);
    sceneFrame.setAttribute('title', 'Mappa e luogo di gioco');
    sceneFrame.addEventListener('load',sceneChanged);
    mapBox.appendChild(sceneFrame);
  }
  function sceneChanged() { void loadPresenti(); void loadStatus(); }
  function openScene(url) {
    const target=new URL(url,location.origin);
    // Reuse the living scene when consulting a handbook/profile over the chat.
    const current=sceneFrame?.contentWindow?.location;
    if(current && current.pathname+current.search===target.pathname+target.search) {closeWorkspace();}
    else { workspace=null; loadIntoMap(target.pathname+target.search); }
    window.history?.replaceState(null,'','/main.php?view='+encodeURIComponent(target.pathname+target.search));
    window.alphanoSceneFocus(true);window.alphanoMobile?.close();
  }
  function closeWorkspace() {
    workspace?.remove();
    workspace = null;
    window.alphanoMobile?.close();
    if (sceneFrame) {sceneFrame.hidden = false;}
    window.history?.replaceState(null, '', '/main.php');
  }
  /* ========================================================================
     2. Pulisci notizie
  ======================================================================== */
  if (clearNewsBtn) {
    clearNewsBtn.addEventListener('click', async e => {
      e.preventDefault();
      try {
        const res = await fetch('/api/news.php?action=reset',{method:'POST'});
        if (!res.ok) {throw new Error('reset news failed');}
        await window.alphanoNotifications?.readAll();
        await loadNews();
      } catch (err) {
        console.error(err);
      }
    });
  }

  /* ========================================================================
     3. Area centrale condivisa: la colonna destra e il luogo restano disponibili
  ======================================================================== */
  function closeSidebar() {
    window.alphanoMobile?.close();
    sidebar?.classList.remove('active');
    sidebarToggle?.setAttribute('aria-expanded', 'false');
  }
  function openModal(url, w, h, title = '') {
    if (!mapBox) {return;}
    if(url.startsWith('/chat/index.php?') && new URL(url,location.origin).searchParams.get('chat')) { openScene(url); return; }
    closeSidebar();
    if (sceneFrame) {sceneFrame.hidden = true;}
    workspace?.remove();
    workspace = document.createElement('section');
    workspace.className = 'workspace';
    workspace.dataset.sceneNavigator=String(/^\/(?:api\/)?mappa\.php(?:[?#]|$)/.test(url));
    workspace.innerHTML = `<header class="workspace-header"><h1>${esc(title || 'Alphano')}</h1><button type="button" data-return-location>Torna al luogo</button></header>`;
    const frame = document.createElement('iframe');
    frame.className = 'workspace-frame';
    frame.setAttribute('src', url);
    frame.setAttribute('title', title || 'Modulo di gioco');
    const pane = workspace;
    frame.addEventListener('load', () => {
      if (workspace !== pane) {return;}
      // The atlas can replace the active scene once a destination is chosen.
      // Keep its iframe in place: moving an iframe would reload the controller.
      if(pane.dataset.sceneNavigator==='true'&&frame.contentDocument?.body.dataset.view==='chat-index'){
        const oldFrame=sceneFrame,oldContainer=oldFrame?.closest('.scene-container');
        oldFrame?.remove();oldContainer?.remove();
        pane.querySelector('.workspace-header')?.remove();pane.className='scene-container';frame.className='scene-frame';frame.title='Mappa e luogo di gioco';frame.addEventListener('load',sceneChanged);sceneFrame=frame;workspace=null;
        window.history?.replaceState(null,'','/main.php?view='+encodeURIComponent(frame.contentWindow.location.pathname+frame.contentWindow.location.search));
        window.alphanoSceneFocus(true);sceneChanged();window.alphanoMobile?.close();return;
      }
      void loadPresenti();
      void loadNews();
      void loadProfile();
      window.alphanoNotifications?.refresh();
      try {
        const visualMap=!!frame.contentDocument?.body.classList.contains('world-visual');
        pane.classList.toggle('is-visual-map',visualMap);
        const heading = pane.querySelector('h1');
        if (heading && frame.contentDocument?.title) {
          const fullTitle=frame.contentDocument.title,separator=/\s+-\s+/.exec(fullTitle);
          const cleanTitle=separator&&fullTitle.slice(separator.index).toLowerCase().includes('alphano')?fullTitle.slice(0,separator.index):fullTitle;
          heading.textContent = frame.contentDocument.querySelector('.ui-page-heading h1')?.textContent || cleanTitle;
        }
        const innerHeading=frame.contentDocument?.querySelector('.ui-page-heading,[data-page-heading]');
        pane.classList.toggle('has-inner-heading',!!innerHeading);
        if(!visualMap&&innerHeading&&!innerHeading.matches('.scene-toolbar,.rpg-hero')&&!innerHeading.querySelector('[data-return-location]')){
          const back=frame.contentDocument.createElement('button');
          back.type='button';back.dataset.returnLocation='';back.className='ui-return-location';back.textContent='Torna al luogo';back.title='Torna al luogo';back.setAttribute('aria-label','Torna al luogo');
          back.addEventListener('click',closeWorkspace);innerHeading.append(back);
        }
      } catch (_) { /* Le risposte esterne mantengono il titolo del modulo. */ }
    });
    workspace.appendChild(frame);
    workspace.addEventListener('click', event => {
      if (event.target.closest('[data-return-location]')) {closeWorkspace();}
    });
    mapBox.appendChild(workspace);
    window.history?.replaceState(null, '', '/main.php?view=' + encodeURIComponent(url));
  }
  window.alphanoReturnToLocation=()=>{if(workspace){closeWorkspace();}else{loadIntoMap(`/api/mappa.php?idpg=${idpg}`);window.history?.replaceState(null,'','/main.php');}};
  window.alphanoWorkspace = { open: (url, title) => openModal(url, 0, 0, title), close: closeWorkspace };
  initFortuneSidebar();
  function closeNavigation(){
    menuGroups?.querySelectorAll('.tab-list.show').forEach(list=>{list.hidePopover?.();list.removeAttribute('popover');list.classList.remove('show');});
    menuGroups?.querySelectorAll('.tab-btn.active').forEach(button=>{button.classList.remove('active');button.setAttribute('aria-expanded','false');});
  }
  document.addEventListener('click',event=>{if(!event.target.closest?.('#menu-groups')){closeNavigation();}});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'){closeNavigation();}});
  window.addEventListener('resize',closeNavigation);
  document.getElementById('header-return')?.addEventListener('click',()=>{closeNavigation();window.alphanoReturnToLocation();});
  document.querySelector('[data-header-privacy]')?.addEventListener('click',()=>window.alphanoPrivacy?.open());
  /* ========================================================================
     4. Quick-links → modal
  ======================================================================== */
  quicks.forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const [url, w, h] = a.dataset.popup.split(',');
      openModal(url, w, h, a.getAttribute('aria-label') || a.title || a.textContent.trim());
    });
  });

  /* ========================================================================
     5. STATUS HEADER (tabs dinamici + meteo)
  ======================================================================== */
  let menuSnapshot = null;
  let statusRequest = 0;
  function renderRecentScene(scene) {
    const slot=menuGroups.querySelector('[data-navigation-recent-scene]');
    if(!slot){return;}
    slot.hidden=!scene;
    if(!scene){slot.replaceChildren();return;}
    let link=slot.querySelector('a');
    if(!link){
      link=document.createElement('a');link.className='nav-recent-scene';link.dataset.characterMarkers='off';
      const copy=document.createElement('span');copy.className='nav-recent-copy';
      const caption=document.createElement('small');caption.className='nav-recent-caption';
      const label=document.createElement('span');label.className='nav-recent-title';
      const dot=document.createElement('span');dot.className='presence-session-dot nav-recent-dot';dot.setAttribute('aria-hidden','true');
      copy.append(caption,label);link.append(copy,dot);slot.append(link);
      link.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey){return;}event.preventDefault();closeNavigation();openScene(link.getAttribute('href'));});
    }
    const label=[scene.title,scene.city].filter(Boolean).join(' – ');
    link.href=scene.url;
    link.querySelector('.nav-recent-caption').textContent=scene.current?'Torna alla tua chat':'Ultima chat visitata';
    link.querySelector('.nav-recent-title').textContent=label;
    link.querySelector('.nav-recent-dot').hidden=!scene.session_active;
    link.title=scene.session_active?'Quest in corso: '+scene.session_title:'Rientra in '+label;
    link.setAttribute('aria-label','Torna a '+label+(scene.session_active?'. Quest in corso: '+scene.session_title:''));
  }
  window.addEventListener('alphano:appearance-updated',()=>loadStatus());
  function compactMoonText(s,now,albaOggi,tramontoOggi){
    if(s.lunaPhase!=='Luna Piena'){
      const gg=Number(s.daysToFull);
      return `Luna Piena tra ${gg} ${gg===1?'giorno':'giorni'}`;
    }
    return now>=albaOggi&&now<tramontoOggi?`Luna Piena · sorge alle ${s.tramonto}`:`Luna Piena · alba alle ${s.alba}`;
  }
  function healthClass(percent){
    if(percent>=80){return 'green';}
    if(percent>=50){return 'orange';}
    if(percent>=20){return 'yellow';}
    return percent>0?'red':'gray';
  }
  function menuTabLabel(title){
    const label=document.createElement('span');label.className='menu-text';
    const menuName=document.createElement('span');menuName.className='menu-label';menuName.textContent=title;
    label.append(menuName);return label;
  }
  function directMenuButton(item){
    const btn=document.createElement('a');
    btn.className='tab-btn nav-direct';btn.dataset.characterMarkers='off';btn.href=item.url;btn.dataset.url=item.url;btn.dataset.title=item.label;
    btn.addEventListener('click',event=>{
      if(event.ctrlKey||event.metaKey||event.shiftKey||event.altKey){return;}
      if(item.url==='/logout.php'){btn.target='_top';return;}
      event.preventDefault();closeNavigation();openModal(item.url,item.w,item.h,item.label);
    });
    return btn;
  }
  function groupMenuButton(key,groupIcon,image,title){
    const btn=document.createElement('button');btn.type='button';btn.setAttribute('aria-haspopup','true');btn.setAttribute('aria-expanded','false');
    const navIcons={Menu:'compass',Master:'book','Comunità':'users',Amministrazione:'settings',Strumenti:'scroll'};
    if(image){const portrait=document.createElement('img');portrait.className='nav-medallion';portrait.src=image;portrait.width=66;portrait.height=66;portrait.alt='';portrait.draggable=false;portrait.decoding='async';btn.prepend(portrait);}
    else{const glyph=document.createElement('span');glyph.dataset.icon=groupIcon||navIcons[title]||'scroll';glyph.setAttribute('aria-hidden','true');if(window.alphanoUI){glyph.append(window.alphanoUI.icon(glyph.dataset.icon));}btn.prepend(glyph);}
    if(key){const badge=document.createElement('span');badge.className='notification-badge';badge.dataset.notificationGroup=key;badge.hidden=true;badge.textContent='0';btn.append(badge);}
    return btn;
  }
  function menuItemElement(it){
    const li=document.createElement('li');
    li.innerHTML=`
      <a href="${esc(it.url)}" data-character-markers="off" data-url="${esc(it.url)}" data-size="${it.w},${it.h}" data-title="${esc(it.label)}">
        ${it.image?`<img class="nav-item-medallion" src="${esc(it.image)}" width="50" height="50" alt="" loading="lazy" decoding="async">`:`<span class="nav-item-symbol" data-icon="${esc(it.icon||'scroll')}" aria-hidden="true"></span>`}<span class="nav-item-label">${esc(it.label)}</span> <span class="notification-badge" data-notification-link="${esc(it.url)}" hidden>0</span>
      </a>
    `;
    if(window.alphanoUI){li.querySelectorAll('[data-icon]').forEach(mark=>mark.append(window.alphanoUI.icon(mark.dataset.icon)));}
    if(it.url==='/mappa.php'){const recent=document.createElement('div');recent.dataset.navigationRecentScene='';recent.hidden=true;li.append(recent);}
    return li;
  }
  function closeTabMenus(tabs){
    tabs.querySelectorAll('.tab-list.show').forEach(u=>{u.hidePopover?.();u.removeAttribute('popover');u.classList.remove('show');});
    tabs.querySelectorAll('.tab-btn.active').forEach(b=>{b.classList.remove('active');b.setAttribute('aria-expanded','false');});
  }
  function openTabMenu(btn,list,key,tabs){
    const isOpen=btn.classList.contains('active');
    closeTabMenus(tabs);
    if(isOpen){return;}
    if(!mobileViewport.matches){
      const anchor=btn.getBoundingClientRect();
      list.style.setProperty('--menu-left',Math.max(12,Math.min(anchor.left+anchor.width/2-135,window.innerWidth-282))+'px');
      list.style.setProperty('--menu-top',(anchor.bottom+8)+'px');
      list.style.setProperty('--menu-bottom',(window.innerHeight-anchor.top+8)+'px');
    }
    list.classList.add('show');
    if(!mobileViewport.matches&&list.showPopover){list.setAttribute('popover','manual');list.showPopover();}
    btn.classList.add('active');btn.setAttribute('aria-expanded','true');
    if(key==='gioco'){void loadStatus();}
  }
  function buildMenu(s){
    const fallbackGroups=[{key:'gioco',title:'Gioco',icon:'compass',test:()=>true}];
    const groups=s.menuGroups?Object.entries(s.menuGroups).map(([key,definition])=>({...definition,key,test:item=>item.group===key})):fallbackGroups;
    menuGroups.innerHTML='';
    const tabs=document.createElement('div');tabs.className='tabs';
    for(const {title,test,key,icon:groupIcon,image} of groups){
      const items=s.menu.filter(test);if(!items.length){continue;}
      const tab=document.createElement('div');tab.className='tab';if(key){tab.dataset.menuGroup=key;}
      if(items.length===1){
        const btn=directMenuButton(items[0]);btn.append(menuTabLabel(title));btn.title=title;tab.append(btn);tabs.append(tab);continue;
      }
      const btn=groupMenuButton(key,groupIcon,image,title);btn.append(menuTabLabel(title));btn.title=title;
      const list=document.createElement('ul');list.className='tab-list';
      if(key){list.id='navigation-'+key;btn.setAttribute('aria-controls',list.id);}
      for(const it of items){list.appendChild(menuItemElement(it));}
      btn.addEventListener('click',()=>openTabMenu(btn,list,key,tabs));
      list.querySelectorAll('a').forEach(a=>{a.addEventListener('click',e=>{
        if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey){return;}
        if(a.dataset.url==='/logout.php'){a.target='_top';return;}
        e.preventDefault();
        const [w_,h_]=a.dataset.size.split(',');
        openModal(a.dataset.url,w_,h_,a.dataset.title);
        list.hidePopover?.();list.removeAttribute('popover');list.classList.remove('show');
        btn.classList.remove('active');btn.setAttribute('aria-expanded','false');
      });});
      tab.append(btn,list);tabs.append(tab);
    }
    tabs.style.setProperty('--menu-count',String(tabs.children.length));
    menuGroups.appendChild(tabs);
    if(document.dispatchEvent){document.dispatchEvent(new Event('alphano:menu-ready'));}
    const closeMenus=()=>closeTabMenus(tabs);
    tabs.addEventListener('scroll',closeMenus);
    tabs.addEventListener('keydown',event=>{if(event.key==='Escape'){closeMenus();}});
  }
  function renderWeather(s){
    const icons=s.weatherIcons||{};
    const fallback={cielo:{src:'/assets/weather-cloud.svg',label:s.descrCielo},tempo:{src:'/assets/weather-cloud.svg',label:s.descrTempo},vento:{src:'/assets/weather-cloud.svg',label:s.descrVento},luna:{src:'/assets/weather-moon.svg',label:s.lunaPhase}};
    const calendarHtml=s.worldCalendar?`<p class="world-calendar" title="${esc(s.worldCalendar.moon_convention)}"><span data-icon="scroll" aria-hidden="true"></span> ${esc(s.worldCalendar.label)} · ON</p>`:'';
    const iconsHtml=['cielo','tempo','vento','luna'].map(key=>{const asset=icons[key]||fallback[key];return `<figure class="wi" data-tooltip="${esc(asset.description||asset.label)}"><img src="${esc(asset.src)}" alt="${esc(asset.label)}" title="${esc(asset.description||asset.label)}" width="80" height="80"></figure>`;}).join('');
    weatherW.innerHTML=`${calendarHtml}<div class="weather-icons">${iconsHtml}</div><div class="weather-summary"><div class="weather-temperature">${Number(s.temperatura)} °C</div><div class="weather-time" id="weather-time"></div></div>`;
    const [hA,mA]=s.alba.split(':').map(Number);
    const [hT,mT]=s.tramonto.split(':').map(Number);
    const now=new Date();
    const albaOggi=new Date(now.getFullYear(),now.getMonth(),now.getDate(),hA,mA,0);
    const tramontoOggi=new Date(now.getFullYear(),now.getMonth(),now.getDate(),hT,mT,0);
    const albaDomani=new Date(albaOggi.getTime()+86_400_000);
    const timeEl=document.getElementById('weather-time');
    let msg='';
    if(s.lunaPhase!=='Luna Piena'){
      const gg=s.daysToFull;
      msg=`Mancano ${gg} ${gg===1?'giorno':'giorni'} alla prossima Luna Piena.`;
    }else if(now<albaOggi){
      msg=`Notte di Luna Piena! Fai attenzione: la Luna non sarà più visibile alle ${s.alba}.`;
    }else if(now<tramontoOggi){
      msg=`Luna Piena! Fai attenzione: la Luna sorgerà al tramonto alle ${s.tramonto}.`;
    }else if(now<albaDomani){
      msg=`Notte di Luna Piena! Fai attenzione: la Luna non sarà più visibile alle ${s.alba}.`;
    }else{
      msg=`Luna Piena: il sole è sorto. La prossima notte torna a fare attenzione!`;
    }
    timeEl.textContent=compactMoonText(s,now,albaOggi,tramontoOggi);
    timeEl.title=msg;
    timeEl.setAttribute('aria-label',msg);
  }
  async function loadStatus(){
    if(!menuGroups||!idpg){return;}
    const request=++statusRequest;
    try{
      const res=await fetch(`/api/status.php?idpg=${idpg}`,{cache:'no-store'});
      if(!res.ok){throw new Error('status fetch failed');}
      const s=await res.json();
      if(request!==statusRequest){return;}
      const nextMenuSnapshot=JSON.stringify([s.menuGroups||null,s.menu]);
      if(nextMenuSnapshot!==menuSnapshot){buildMenu(s);menuSnapshot=nextMenuSnapshot;}
      renderRecentScene(s.recentScene);
      void Promise.resolve(window.alphanoPrivacy?.firstLogin()).then(()=>presentWelcome(s.welcome,s.csrfToken)).then(()=>presentCivicInvitation(s.civicInvitation,s.csrfToken)).catch(err=>console.error(err));
      if(weatherW){renderWeather(s);}
    }catch(err){console.error(err);}
  }

  /* ========================================================================
     6. NEWS
  ======================================================================== */
  async function loadNews() {
    if (!newsUl) {return;}
    try {
      const res = await fetch('/api/news.php');
      if (!res.ok) {throw new Error('news fetch failed');}
      const arr = await res.json();
      patchLiveList(newsUl,arr.map((t,i) => `<li data-live-key="news:${i}">${esc(t)}</li>`).join(''));
    } catch (err) {
      console.error(err);
    }
  }

  /* ========================================================================
     7. PROFILO
  ======================================================================== */
  let lycanBusy=false,lycanReadPending=false,lycanRevision='',lycanRace=null;
  const lycanKey=state=>JSON.stringify([state.race,state.visible_race_id,state.form,state.nextForm,state.visible,state.canChoose,state.phase,state.label,state.description]);
  function renderTransformation(state){
    if(!state){return;}
    const visibleRace=state.visible_race_id??state.race;
    if(lycanRace!==null&&lycanRace!==visibleRace){
      for(const frame of mapBox.querySelectorAll('iframe'))try{frame.contentDocument?.dispatchEvent(new frame.contentWindow.CustomEvent('alphano:lycan-change',{detail:state}));}catch(_){}
    }
    lycanRace=visibleRace;lycanRevision=lycanKey(state);pname.querySelector('.profile-transform')?.remove();
    if(!state.visible){return;}
    const button=document.createElement('button');button.type='button';button.className='profile-transform';button.dataset.form=state.form;button.setAttribute('aria-label',state.label);button.title=state.label+' · '+state.description;button.setAttribute('aria-pressed',String(state.form!=='human'));button.disabled=lycanBusy;if(window.alphanoUI){button.append(window.alphanoUI.icon('moon'));}
    button.addEventListener('click',async event=>{
      event.stopPropagation();
      if(!state.canChoose){window.alphanoDialog.notice({title:'La metamorfosi',message:state.description,eyebrow:'Alphano · Il personaggio'});return;}
      if(lycanBusy){return;}lycanBusy=true;button.disabled=true;
      try{
        const response=await fetch('/api/transformation.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify({form:state.nextForm||(state.form==='human'?'native':'human')}),signal:AbortSignal.timeout(10000)});
        const data=await response.json();if(!response.ok){throw new Error(data.error||'Trasformazione non riuscita.');}
        await loadProfile();await loadPresenti();
        document.dispatchEvent(new CustomEvent('alphano:lycan-change',{detail:data.transformation}));
      }catch(error){window.alphanoDialog.notice({title:'La metamorfosi',message:error.message,eyebrow:'Alphano · Il personaggio'});}
      finally{lycanBusy=false;const current=pname.querySelector('.profile-transform');if(current){current.disabled=false;if(document.activeElement===document.body){current.focus({preventScroll:true});}}}
    });
    pname.querySelector('span').append(button);
  }
  async function refreshTransformation(){
    if(document.hidden||lycanReadPending||lycanBusy){return;}lycanReadPending=true;
    try{const response=await fetch('/api/transformation.php',{cache:'no-store',signal:AbortSignal.timeout(7000)});if(!response.ok){return;}const data=await response.json();if(lycanKey(data.transformation)!==lycanRevision){await loadProfile();await loadPresenti();}}
    catch(error){console.error('Stato della metamorfosi temporaneamente non disponibile.',error);}
    finally{lycanReadPending=false;}
  }
  function renderAvatar(p,racePortrait){
    const fallback=()=>{avatar.classList.add('is-race-portrait');if(avatar.getAttribute('src')!==racePortrait){avatar.src=racePortrait;}};
    avatar.onerror=fallback;
    avatar.classList.toggle('is-race-portrait',!p.avatar_url||p.avatar_url===racePortrait||p.avatar_url.endsWith('/assets/avatar.svg'));
    avatar.src=p.avatar_url&&!p.avatar_url.endsWith('/assets/avatar.svg')?p.avatar_url:racePortrait;
  }
  function renderProfileCard(p,racePortrait){
    const card=avatar.closest('.profile-card');
    const art=p.sidebar_art_url||racePortrait;
    if(/^\/uploads\/profiles\/banners\/[a-z0-9-]+\.webp$/.test(art)){card.style.setProperty('--profile-card-art',`url("${art}")`);}
    document.getElementById('profile-race').textContent=p.lineage_race_label||p.razza.label||'';
    psub.textContent=[p.allineamento,p.etnia].filter(Boolean).join(' · ');
    document.getElementById('profile-money').textContent=Number(p.denari ?? 0).toLocaleString('it-IT');
  }
  function renderHealthBar(p){
    if(p.salute==null||p.saluteMax==null){return;}
    const pct=Math.max(0,Math.min(100,(p.salute/p.saluteMax)*100));
    const bar=document.getElementById('profile-health-bar');
    if(bar){bar.style.width=pct+'%';bar.className='bar green';}
  }
  function renderPointsBar(p){
    const pts=Number(p.puntif)||0;
    const barPts=document.getElementById('profile-points-bar');
    const maxPoints=Number(p.puntif_max)||1000;
    const percent=Math.max(0,Math.min(100,pts/maxPoints*100));
    barPts.style.width=percent+'%';
    document.getElementById('profile-health-label').textContent=percent>=100?'Completa':`${Math.round(percent)}%`;
    barPts.setAttribute('role','progressbar');
    barPts.setAttribute('aria-valuemin','0');
    barPts.setAttribute('aria-valuemax',String(maxPoints));
    barPts.setAttribute('aria-valuenow',String(pts));
    barPts.setAttribute('aria-label',`Salute: ${pts} / ${maxPoints}`);
    barPts.className='bar-inner '+healthClass(percent);
  }
  async function loadProfile(){
    if(!avatar||!pname||!psub){return;}
    try{
      const res=await fetch('/api/profile.php');
      if(!res.ok){throw new Error('profile fetch failed');}
      const p=await res.json();
      const racePortrait=p.avatar_default_url||avatarFallback;
      avatar.dataset.defaultSrc=racePortrait;
      renderAvatar(p,racePortrait);
      pname.innerHTML=`
        <span class="profile-name-row"><span class="profile-name-text">${esc(p.nome)}</span>${p.razza.icon}</span>
      `;
      renderTransformation(p.transformation);
      renderMetamorphosis(avatar.closest('.profile-card'),p.metamorphosis);
      pname.classList.add('clickable');
      pname.onclick=()=>openModal(`/profili/?Id=${p.id}`,800,600,`Profilo ${p.nome}`);
      renderProfileCard(p,racePortrait);
      renderHealthBar(p);
      renderPointsBar(p);
    }catch(err){console.error(err);}
  }

  /* ========================================================================
     X. PRESENTI
  ======================================================================== */
  document.getElementById('presenti-open')?.addEventListener('click',event=>{event.preventDefault();openModal('/presenti/',1000,750,'Presenti');});
  const presenceToggle=document.getElementById('presenti-show-npcs');
  let presenceData=null;
  if(presenceToggle){presenceToggle.checked=presenceShowsNpcs(idpg);presenceToggle.addEventListener('change',()=>{presenceSaveNpcs(idpg,presenceToggle.checked);if(presenceData){renderPresenti(presenceData);}updateGameClocks();});}
  window.addEventListener('storage',event=>{if(event.key==='alphano:presence:npcs:'+idpg&&presenceToggle){presenceToggle.checked=presenceShowsNpcs(idpg);if(presenceData){renderPresenti(presenceData);}updateGameClocks();}});
  function presenceMailButton(person){
    return person.kind==='player'?`<button class="posta-btn" data-pg-name="${encodeURIComponent(person.name)}" data-idpg="${person.id}" title="Scrivi a ${esc(person.name)}">?</button>`:'';
  }
  function renderPresenti(data){
      const showNpcs=presenceToggle?presenceToggle.checked:presenceShowsNpcs(idpg);
      presentiCount.textContent = showNpcs?data.total:data.players;
      const summary=document.getElementById('presenti-summary');
      if(summary){summary.textContent=`${data.players} ${data.players===1?'giocatore':'giocatori'} · ${showNpcs?data.npcs+' PNG nei luoghi':'PNG nascosti'}`;}
      patchLiveList(presentiList,data.places.map(place=>({...place,members:place.members.filter(person=>showNpcs||person.kind==='player')})).filter(place=>place.members.length||place.session_active).map(place=>`
        <li class="presence-sidebar-place" data-live-key="place:${esc(place.key)}">${presencePlaceHeading(place)}</li>
        ${place.members.map(person=>`<li class="presence-sidebar-person" data-live-key="person:${esc(place.key)}:${esc(person.kind)}:${person.id}" data-kind="${esc(person.kind)}">
          <img src="${esc(person.icon)}" width="16" height="16" alt="${esc(person.race+' · '+person.sex)}" title="${esc(person.race+' · '+person.sex)}" class="razza-icon">
          <a class="presenti-name presence-link" href="${esc(person.profile_url)}" data-url="${esc(person.profile_url)}" data-title="Profilo di ${esc(person.name)}">${esc(person.name)}</a>
          <span class="presence-kind">${person.kind==='npc'?'PNG':'PG'}</span>
          ${presenceGameAction(person)}          ${person.kind==='player'?`<button class="posta-btn" data-pg-name="${encodeURIComponent(person.name)}" data-idpg="${person.id}" title="Scrivi a ${esc(person.name)}">✉</button>`:''}
        </li>`).join('')}`).join('') || '<li data-live-key="empty">Nessun personaggio presente.</li>');
  }
  presentiList?.addEventListener('click',event=>{
    const link=event.target.closest('.presence-link');if(link){event.preventDefault();openModal(link.dataset.url,1000,750,link.dataset.title);return;}
    const btn=event.target.closest('.posta-btn');if(btn){event.preventDefault();openModal(`/posta/writeto.php?pgName=${btn.dataset.pgName}&idpg=${btn.dataset.idpg}`,800,600,`Scrivi a ${decodeURIComponent(btn.dataset.pgName)}`);}
  });
  let presenceLoading=false;
  async function loadPresenti() {
    if (!presentiCount || !presentiList || presenceLoading) {return;}
    presenceLoading=true;
    try {
      const res=await fetch('/api/presenti.php',{cache:'no-store',signal:AbortSignal.timeout(10000)});
      if(!res.ok){throw new Error('presenti fetch failed');}
      presenceData=await res.json();
      if(presenceToggle){presenceToggle.checked=presenceShowsNpcs(idpg);}
      renderPresenti(presenceData);updateGameClocks();
    } catch(err){console.error(err);}finally{presenceLoading=false;}
  }

  /* ========================================================================
     8. INIT + TOGGLE SIDEBAR MOBILE + ESC TO CLOSE
  ======================================================================== */
  document.addEventListener('alphano:lycan-change',refreshTransformation);
  window.addEventListener('message',event=>{if(event.origin===location.origin&&event.data?.type==='alphano:permissions-changed'){loadStatus();loadProfile();}});
  if (idpg) {
    loadStatus();      setInterval(loadStatus,     30_000);
    loadNews();        setInterval(loadNews,       30_000);
    loadPresenti();    setInterval(loadPresenti,   30_000);
    loadProfile();setInterval(loadProfile,30000);setInterval(refreshTransformation,15000);
    const resumePresence=()=>{if(!document.hidden){loadStatus();loadPresenti();refreshTransformation();}};
    document.addEventListener('visibilitychange',resumePresence);
    window.addEventListener('pageshow',resumePresence);
    window.addEventListener('online',resumePresence);
    const initialView = script?.dataset.viewUrl || '';
    const initialScene = /^\/(?:mappa\.php(?:[?#]|$)|chat\/(?:index\.php(?:[?#]|$)|(?:[a-z0-9_-]+)\/(?!assemblea\.php|bacheche\/)))/.test(initialView);
    loadIntoMap(initialScene ? initialView : `/api/mappa.php?idpg=${idpg}`);
    if (initialView && !initialScene) {openModal(initialView, 0, 0, 'Alphano');}
    fetch('/api/log_ingressi.php', { keepalive: true }).catch(()=>{});
  }

  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
      if(mobileViewport.matches&&window.alphanoMobile){window.alphanoMobile.world();return;}
      sidebar.classList.toggle('active');
      sidebarToggle.setAttribute('aria-expanded', String(sidebar.classList.contains('active')));
    });
    mapBox?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !mobileViewport.matches) {closeSidebar();}
    });
  }
  document.dispatchEvent(new Event('alphano:shell-ready'));
});
