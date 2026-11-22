<?php
declare(strict_types=1);
const MANUALS_INC_SECONDI = ' secondi';
require_once __DIR__.'/player-runtime.inc.php';
require_once __DIR__.'/default-art.inc.php';
require_once __DIR__.'/rpg-combat.inc.php';
require_once __DIR__.'/libequip.inc.php';
require_once __DIR__.'/public-guides.inc.php';
require_once __DIR__.'/world-chronology.inc.php';

/** Public pages always use a guest identity, even if an administrator is logged in. */
function alphanoManualGuest():array { return ['id'=>0]; }
function alphanoManualPublicPayload(array $payload):array {
    $convert=static function(array $rule):array {
        unset($rule['revision'],$rule['source']);$rule['can_edit']=false;
        foreach($rule['screens'] as &$screen){ $screen['url'].='&public=1'; }unset($screen);
        $rule['links']=array_values(array_filter($rule['links'],static fn($l)=>str_starts_with($l['url']??'', '/regole/?')));
        foreach($rule['links'] as &$link){ $link['url']=str_replace('/regole/?','/manuali/?',$link['url']); }unset($link);
        return $rule;
    };
    if(isset($payload['rules'])){ $payload['rules']=array_map($convert,$payload['rules']); }
    if(isset($payload['rule'])){ $payload['rule']=$convert($payload['rule']); }
    return $payload;
}

/** One policy for the index, direct chapters, screenshots, exports and editors. */
function alphanoManualCatalog():array {
    static $data;return $data??=json_decode(file_get_contents(__DIR__.'/../config/manuals-guide.json'),true,64,JSON_THROW_ON_ERROR);
}
function alphanoManualReady():bool {
    static $ready;return $ready??=(bool)connectDB()->query("SHOW COLUMNS FROM regole LIKE 'manual_key'");
}
function alphanoManualUser(int $id):array {
    $u=connectDB()->query('SELECT * FROM utenti WHERE id=?',[$id])[0]??[];
    if(!$u||!empty($u['bloccato'])){ throw new DomainException('Accesso ai manuali non disponibile.',403); }
    return $u;
}
function alphanoManualAudiences():array {
    return ['public'=>'Tutti i giocatori','city'=>'Governo cittadino','guild'=>'Gestione corporazioni','organization'=>'Gestione organizzazioni','master'=>'Master di gioco','academy'=>'Accademia e redazione','healer'=>'Curatori e gestione anime','admin'=>'Amministratori generali'];
}
function alphanoManualRole(string $role,array $u):bool {
    if(!empty($u['bloccato'])){ return false; }if(!empty($u['admin'])){ return true; }
    return match($role){
        'public'=>true,
        'city'=>!empty($u['sindaco'])&&(bool)connectDB()->query('SELECT id FROM araldiche WHERE id=? AND city>0',[(int)$u['araldica']]),
        'guild'=>!empty($u['corporazione'])&&(!empty($u['admincorporazione'])||!empty($u['admincatalogo'])||!empty($u['admintrasfcorp'])),
        'organization'=>!empty($u['organizzazione'])&&!empty($u['adminorganizzazione']),
        'master'=>!empty($u['fatepower']),
        'academy'=>!empty($u['adminaccademia'])||!empty($u['adminregole']),
        'healer'=>!empty($u['adminferite'])||!empty($u['adminmorti'])||!empty($u['adminstatosociale']),
        default=>false,
    };
}
function alphanoManualDefinition(string $key):?array {
    static $map;if($map===null){ $map=array_column(alphanoManualCatalog()['chapters'],null,'key'); }return $map[$key]??null;
}
function alphanoManualCanRead(array $rule,array $u):bool {
    $definition=alphanoManualDefinition((string)($rule['manual_key']??''));
    // A staff chapter can never be made public by changing its label or URL.
    return alphanoManualRole((string)($definition['audience']??'public'),$u)&&alphanoManualRole((string)($rule['audience']??'public'),$u);
}
function alphanoManualCanEdit(array $rule,array $u):bool {
    return alphanoManualCanRead($rule,$u)&&(!empty($u['admin'])||(!empty($u['adminaccademia'])&&in_array($rule['audience']??'public',['public','academy'],true)));
}
function alphanoManualImage(string $image):string {
    if($image===''||$image==='/assets/placeholder.svg'){ return alphanoDefaultArt('manual'); }
    if(alphanoIsDefaultArt($image)){ return $image; }
    if(!preg_match('~^/uploads/(?:manuals/covers|forum/boards)/[a-z0-9-]+\.webp$~D',$image)||!is_file(dirname(__DIR__).$image)){ throw new DomainException('Scegli un’illustrazione della biblioteca.',422); }
    return $image;
}
function alphanoManualDefaultImage(array $rule):string {
    return alphanoDefaultArt('manual');
}
function alphanoManualRevision(array $rule):string {
    return hash('sha256',json_encode(array_intersect_key($rule,array_flip(['id','sezione','pos','oggetto','spiegazione','ultimamodifica','manual_key','audience','image','summary'])),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function alphanoManualTable(array $heads,array $rows):string {
    $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    return '<div class="manual-table-wrap"><table><thead><tr>'.implode('',array_map(static fn($v)=>'<th scope="col">'.$h($v).'</th>',$heads)).'</tr></thead><tbody>'.implode('',array_map(static fn($row)=>'<tr>'.implode('',array_map(static fn($v)=>'<td>'.$h($v).'</td>',$row)).'</tr>',$rows)).'</tbody></table></div>';
}
/** Public mechanics are rendered from the same catalogue as the character sheet. */
function alphanoManualLive(string $name):string {
    $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    if($name==='world-calendar'){$c=alphanoWorldCalendar();return '<p><strong>Data attuale in gioco: '.$h($c['label']).'.</strong> '.$h($c['moon_convention']).'</p>';}
    if($name==='board-games'){
        require_once __DIR__.'/board-games.inc.php';if(!bgReady()){ return '<p>I parametri saranno disponibili all’attivazione del tavolo.</p>'; }$s=bgSettings();
        return alphanoManualTable(['Parametro del tavolo','Valore attuale'],[['Sistema',$s['enabled']?'Attivo':'Disabilitato'],['Scacchi / Dama',($s['scacchi']?'Sì':'No').' / '.($s['dama']?'Sì':'No')],['Tempo totale consentito',$s['min_total'].'–'.$s['max_total'].' secondi per giocatore'],['Predefiniti: totale / mossa / incremento',$s['default_total'].' / '.$s['default_move'].' / '.$s['default_increment'].MANUALS_INC_SECONDI],['Validità invito',$s['invite_seconds'].MANUALS_INC_SECONDI],[alphanoCurrencyText('Massimo Corone / beni per giocatore'),$s['max_stake_coins'].' / '.$s['max_stake_assets']],['Premio massimo nelle ultime 24 ore',$s['reward_daily'].' Intelligenza'],['Mosse individuali / tempo attivo per livello',$s['reward_step_plies'].' / '.$s['reward_step_active'].MANUALS_INC_SECONDI],['Minimo mosse per partecipante',$s['reward_min_each']],['Partite premiate contro lo stesso avversario / 24 ore',$s['reward_pair_daily']],['ELO: minimo mosse / durata',$s['min_rating_plies'].' / '.$s['min_rating_seconds'].MANUALS_INC_SECONDI],['ELO: coefficiente / partite valide per coppia',$s['elo_k'].' / '.$s['pair_rated_daily']],['Premi ed ELO sulla stessa connessione',$s['reward_shared_network']?'Consentiti':'Disabilitati'],['Interazione prima dell’invito: finestra spettatori',$s['spectator_hours'].' ore']]);
    }
    $catalog=rpg_catalog();$settings=rpg_settings();$rules=rpgCombatSettings();$rows=[];
    if($name==='characteristics'){
        foreach($catalog['stats'] as $s){ $rows[]=[$s['name'],$s['description']]; }return alphanoManualTable(['Caratteristica','A cosa serve'],$rows);
    }
    if($name==='progression'){ return alphanoManualTable(['Regola attuale','Valore'],[['Punti iniziali distribuiti personalmente',20],['Costo di +1 a una caratteristica','1 PE'],['Massimo per caratteristica, in ogni forma',$settings['stat_cap']],['PE base per quest',$settings['quest_xp_enabled']?$settings['quest_xp_base']:'Ricompense automatiche disattivate'],['PE per giro risolto',$settings['quest_xp_per_round']],['Minimo giri / dichiarazioni per il premio',$settings['quest_xp_min_rounds'].' / '.$settings['quest_xp_min_actions']],['Massimo PE per quest / 24 ore',$settings['quest_xp_max'].' / '.$settings['quest_xp_daily_cap']]]); }
    if($name==='races'){
        foreach($catalog['races'] as $r){ if($r['enabled']){ $rows[]=[$r['name'],$r['description'],$r['transformation_enabled']?'Sì':'No',$r['loses_human_language']?'Non parla nella forma speciale':'Linguaggio umano conservato']; } }
        return alphanoManualTable(['Razza','Identità e limiti','Trasformazione','Linguaggio'],$rows);
    }
    if($name==='race-stats'){
        foreach($catalog['races'] as $r){ if($r['enabled']){ $rows[]=[$r['name'],...array_map(static fn($k)=>(int)$r['stats'][$k],RPG_STATS)]; } }
        return alphanoManualTable(['Base razziale',...array_column($catalog['stats'],'name')],$rows);
    }
    if($name==='ethnies'){
        foreach(connectDB()->query('SELECT nome,descrizione FROM etnie ORDER BY id') as $e){ $rows[]=[$e['nome'],trim(preg_replace('/\s+/u',' ',strip_tags(html_entity_decode($e['descrizione'],ENT_QUOTES,'UTF-8'))))]; }
        return alphanoManualTable(['Origine','Descrizione nell’ambientazione'],$rows);
    }
    if($name==='abilities'){
        $raceNames=array_column($catalog['races'],'name','id');
        foreach($catalog['abilities'] as $a){ if($a['enabled']){
            $races=array_map(static fn($id)=>$raceNames[$id]??'Razza',$a['race_ids']);$requirements=[];
            foreach($a['requirements']['stats']??[] as $k=>$v){ $requirements[]=rpg_stat_name($k).' '.$v; }
            foreach($a['requirements']['abilities']??[] as $id){ foreach($catalog['abilities'] as $prior){ if($prior['id']===$id){ $requirements[]=$prior['name']; } } }
            $rows[]=[$a['name'],implode(', ',$races),rpgAbilityLearnable($a)?($a['category']==='innate'?'Innata · 4 gratuite, altre ':'').$a['cost'].' PE':'Innata di razza',implode('; ',$requirements)?:'Nessun prerequisito aggiuntivo',$a['cooldown']?$a['cooldown'].' giri':'Nessun recupero'];
        }
        }
        return alphanoManualTable(['Capacità','Razze di base','Acquisizione','Requisiti','Recupero'],$rows);
    }
    if($name==='human-heritage'){
        foreach($catalog['abilities'] as $a){if(empty($a['rules'])||!$a['enabled']){ continue; }$n=$a['rules']['number'];$humanOnly=in_array($n,rpgLineagePolicy()['human_only'],true);$learn=rpgAbilityLearnable($a);$access=$humanOnly?'Soltanto Umani':($learn?'Apprendibile dalle quattro stirpi trasformabili':'Assegnata dalla razza');$form=in_array($n,rpgLineagePolicy()['lycan_controlled_body'],true)?'Lycan controllato o forma umana':'Forma umana per i Lycan';if($humanOnly){ $form='Resistenza automatica umana'; }$note=rpgLineagePolicy()['ancestral_excluded'][$n]??'';$rows[]=[$a['name'],$access,$learn?($a['category']==='innate'?'4 gratuite, altre ':'').$a['cost'].' PE':'Di razza',$form,$note?:'Requisiti e recupero della carta'];}
        return alphanoManualTable(['Capacità umana','Accesso','Costo','Forma','Limiti'],$rows);
    }
    if($name==='human-abilities'){
        $html='';foreach($catalog['abilities'] as $a){if(empty($a['rules'])||!$a['enabled']){ continue; }$r=$a['rules'];$html.='<details class="manual-human-ability"><summary><img src="'.$h($a['image']).'" alt="" width="56" height="56"> '.$h($a['name']).' · '.($r['initial']?'Innata · 4 gratuite, altre '.$a['cost'].' PE':$a['cost'].' PE').'</summary><p>'.$h($a['description']).'</p><p><strong>Attivazione:</strong> '.$r['probability'].'% · '.$r['activation'].' turno/i. <strong>Durata:</strong> '.$r['duration'].' turno/i. <strong>Recupero:</strong> '.$a['cooldown'].' turni completi.</p><p>'.$h($r['bonus_text']).'</p><p><strong>Richiede:</strong> '.$h($r['requirement_text']).'</p></details>';}
        return $html;
    }
    if($name==='racial-malus'){
        $html='';$families=['human'=>'Umani','vampire'=>'Vampiri','lycan'=>'Lycan'];foreach(rpgHumanManual()['malus'] as $m){ $html.='<details class="manual-racial-malus"><summary><img src="'.$h($m['image']).'" alt="" width="64" height="64" loading="lazy"><span>'.$h($m['name']).' · '.$h($families[$m['family']]??$m['family']).'</span></summary><p>'.$h($m['description']).'</p><p><strong>Innesco:</strong> '.$h($m['trigger']).'</p><p><strong>Durata:</strong> '.$h($m['duration']).'</p><p>'.$h($m['effect']).'</p><p>'.$h($m['mechanic'].' '.$m['note']).'</p></details>'; }return $html;
    }
    if($name==='combat'){
        foreach(['attack'=>['Attacco fisico',['strength','dexterity','perception']],'defence'=>['Difesa fisica',['dexterity','constitution','willpower']],'mental'=>['Potere mentale',['charisma','intelligence','willpower']],'resist'=>['Resistenza mentale',['willpower','wisdom']]] as $key=>[$label,$stats]){ $rows[]=[$label,implode(' + ',array_map(static fn($s)=>rpg_stat_name($s).' × '.number_format($settings[$key.'_'.$s]/100,2,',',''),$stats))]; }
        return alphanoManualTable(['Punteggio prima del dado','Composizione attuale'],$rows).alphanoManualTable(['Parametro della scena','Valore attuale'],[['Tempo per turno sotto regia IA',ceil($rules['turn_seconds']/60).' minuti'],['Bonus della difesa dichiarata',$rules['defence_bonus']],['Sostegno per alleato / massimo alleati',$rules['support_bonus'].' / '.$rules['max_support']],['Portata massima armi da tiro',$rules['ranged_max_range']],['Ricarica armi da tiro',$rules['reload_rounds'].' giri'],['Assorbimento della protezione',$rules['armor_absorption']],['Limiti del danno per risoluzione',$rules['damage_min'].'–'.$rules['damage_max']]]);
    }
    if($name==='materials'){ return alphanoManualTable(['Materie prime riconosciute'],array_map(static fn($v)=>[mb_convert_case($v,MB_CASE_TITLE,'UTF-8')],MATERIE_PRIME)); }
    if($name==='permissions'){
        require_once __DIR__.'/admin-governance.inc.php';foreach(alphanoGovernanceFlags() as $key=>$info){ $rows[]=[$info[0],$info[1],match($key){'admin'=>'Tutti gli ambiti; configurazioni e audit generali','admincorporazione','admincatalogo','admintrasfcorp'=>'Solo la propria corporazione e le funzioni delegate','adminorganizzazione'=>'Solo la propria organizzazione','sindaco'=>'Solo la città del mandato','fatepower'=>'Regia umana; nessun accesso tecnico all’IA','adminregole'=>'Flag storico: non sostituisce i controlli effettivi di Accademia',default=>'La singola funzione ricontrolla il permesso prima di operare'}]; }
        return alphanoManualTable(['Delega','Ambito','Limite operativo'],$rows);
    }
    return '';
}
function alphanoManualContent(array $rule):string {
    $html=processText($rule['spiegazione']);
    if(($rule['manual_key']??'')==='primi-passi'){ $html.=alphanoAlignmentManual(); }
    if(($rule['manual_key']??'')==='citta-elezioni'){
        $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html.='<section class="manual-city-guides"><h2>Scegliere una città</h2><p>La cittadinanza è un’appartenenza politica e sociale: non determina la tua razza né la tua etnia. Puoi anche iniziare come Viandante e decidere in gioco.</p>';
        foreach(alphanoCityGuides() as $city){ $html.='<article class="manual-city-guide"><img src="'.$h($city['image']).'" alt="" width="72" height="72"><div><h3>'.$h($city['city']).'</h3><p>'.$h($city['description']).'</p><p><strong>Carica iniziale:</strong> '.$h($city['entry_role']??'Da concordare con il governo cittadino').'.</p><details><summary>Statuto vigente</summary>'.$city['statute_html'].'</details></div></article>'; }
        $html.='</section>';
    }
    return preg_replace_callback('/\{\{live:([a-z-]+)\}\}/',static fn($m)=>$m[1]==='permissions'&&($rule['audience']??'public')!=='admin'?'':alphanoManualLive($m[1]),$html)??$html;
}
function alphanoManualScreens(array $rule):array {
    $key=(string)($rule['manual_key']??'');$out=[];
    foreach(alphanoManualDefinition($key)['screens']??[] as $screen){
        $path=alphanoManualScreenPath($screen['file']);if(!is_file($path)){ continue; }
        $out[]=['key'=>$screen['key'],'title'=>$screen['title'],'caption'=>$screen['caption'],'url'=>'/api/manual-media.php?chapter='.rawurlencode($key).'&screen='.rawurlencode($screen['key'])];
    }
    return $out;
}
function alphanoManualScreenPath(string $file):string {
    if(!preg_match('/^[a-z0-9-]+\.png$/D',$file)){ throw new DomainException('Schermata non disponibile.',404); }
    return rtrim((string)(alphanoConfig('storage_root')?:dirname(__DIR__).'/config/storage'),'/').'/manual-screens/'.$file;
}
function alphanoManualFind(string $key,int $id,array $u):array {
    $db=connectDB();$row=$key!==''&&alphanoManualReady()?($db->query('SELECT r.*,s.nome AS group_name FROM regole r JOIN sezioniregole s ON s.id=r.sezione WHERE r.manual_key=?',[$key])[0]??null):($db->query('SELECT r.*,s.nome AS group_name FROM regole r JOIN sezioniregole s ON s.id=r.sezione WHERE r.id=?',[$id])[0]??null);
    if(!$row||!alphanoManualCanRead($row,$u)){ throw new DomainException('Capitolo non disponibile per questo account.',404); }
    return $row;
}
function alphanoManualPayload(array $rule,array $u,bool $content=true):array {
    $excerpt=processText($rule['spiegazione']);$excerpt=preg_replace('~</(?:p|div|h[1-6]|li|tr)>|<br\s*/?>~i',' ',$excerpt);$plain=trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($excerpt),ENT_QUOTES|ENT_HTML5,'UTF-8')));$definition=alphanoManualDefinition((string)($rule['manual_key']??''));
    $image=($rule['image']??'')?:alphanoManualDefaultImage($rule);
    try{$image=alphanoManualImage($image);}catch(DomainException $e){$image=alphanoManualDefaultImage($rule);}
    $out=['id'=>(int)$rule['id'],'sezione'=>(int)$rule['sezione'],'pos'=>(int)$rule['pos'],'key'=>$rule['manual_key']??'','oggetto'=>$rule['oggetto'],'summary'=>($rule['summary']??'')?:mb_substr($plain,0,210),'image'=>$image,'audience'=>$rule['audience']??'public','audience_label'=>alphanoManualAudiences()[$rule['audience']??'public']??'Riservato','ultimamodifica'=>(int)$rule['ultimamodifica'],'revision'=>alphanoManualRevision($rule),'can_edit'=>alphanoManualCanEdit($rule,$u),'managed'=>$definition!==null,'screens'=>alphanoManualScreens($rule),'links'=>$definition['links']??[]];
    if($content){ $out['spiegazione']=alphanoManualContent($rule); }
    if($content&&$out['can_edit']){ $out['source']=$rule['spiegazione']; }return $out;
}
function alphanoManualData(array $u,bool $content=true):array {
    $db=connectDB();$rules=[];$used=[];
    foreach($db->query('SELECT r.*,s.nome AS group_name FROM regole r JOIN sezioniregole s ON s.id=r.sezione ORDER BY s.pos,s.id,r.pos,r.id') as $r){ if(alphanoManualCanRead($r,$u)){$rules[]=alphanoManualPayload($r,$u,$content);$used[(int)$r['sezione']]=true;} }
    $groups=[];foreach($db->query('SELECT * FROM sezioniregole ORDER BY pos,id') as $g){ if(isset($used[(int)$g['id']])||(!empty($u['admin'])||!empty($u['adminaccademia']))){$groups[]=['id'=>(int)$g['id'],'nome'=>$g['nome'],'key'=>$g['manual_key']??'','legacy'=>empty($g['manual_key'])];} }
    return ['success'=>true,'counts'=>['groups'=>count($groups),'rules'=>count($rules)],'groups'=>$groups,'rules'=>$rules,'audiences'=>array_filter(alphanoManualAudiences(),static fn($label,$role)=>alphanoManualRole($role,$u),ARRAY_FILTER_USE_BOTH),'images'=>array_merge(array_map(static fn($a)=>['path'=>$a['path'],'name'=>$a['title']],array_values(array_filter(alphanoDefaultArtCatalog(),static fn($a)=>$a['kind']==='cover'))),array_map(static fn($c)=>['path'=>$c['image'],'name'=>$c['title']],array_values(array_filter(alphanoManualCatalog()['chapters'],static fn($c)=>alphanoManualRole($c['audience'],$u)))))];
}
