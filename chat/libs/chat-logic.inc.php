<?php
declare(strict_types=1);

use Alphano\Database\PDOMySQLDriver;
require_once __DIR__.'/../../libs/chat-format.inc.php';


/** Renders the character name once, keeping the original markup. */
function alphanoChatDisplayName($db,int $idpg,int $umore,array $pg): string
{
    $name  = htmlentities($pg['nome'], ENT_QUOTES | ENT_SUBSTITUTE);
    if ($umore) {
        $name .= '&nbsp;' . GetUmore($umore);
    }
    $name='<a class="chat-character-name '.alphanoChatGender((string)$pg['sesso']).'" href="javascript:openProfileId(\''.$idpg.'\')">'.$name.'</a>';
    if ($db->query("SELECT 1 FROM equippg WHERE slot='musica' AND idpg=?",[$idpg])) {
        $name .= '&nbsp;<img src=\"/chatimg/musica.gif\" border=\"0\">';
    }
    return $name;
}

/** Renders the plain speech and action commands. */
function alphanoChatBasicOutput(array &$ctx,callable $render): void
{
    $str=$ctx['str'];$ora=$ctx['ora'];$razImg=$ctx['razImg'];$fPar=$ctx['fPar'];$fAzo=$ctx['fAzo'];
    $name=$ctx['name'];$pg=$ctx['pg'];$location=$ctx['location'];$newLoc=$ctx['newLoc'];$OnGame=$ctx['OnGame'];
    $SessionIsRunning=$ctx['SessionIsRunning'];$NeedsDadi=$ctx['NeedsDadi'];$out=$ctx['out'];
    switch($ctx['first']){
        case '/':
            if(str_starts_with($str,'//')){$OnGame=false;$out='<span class="String">'.$ora.'</span> '.$razImg.$fPar.$name.'</B></SPAN> <span class="chat-ooc-text">'.htmlspecialchars(substr($str,2),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span>';break;}
            // A single slash is ordinary speech.
            $out='<span class="String">'.$ora.'</span> '.$razImg.$name.' '.$render(substr($str,1));break;
        case '+': {
            // rimuovo il ‘+’ dalla stringa
            $azione = substr($str, 1);

            // Resource work is confirmed and committed by the HTTP action engine.
            // The shared text renderer never awards inventory or penalizes accounts.

            // fallback “azione normale”
            if ($pg['imprigionato']) {
                $location = 'prigione';
                $azione   = '[Prigione]' . $azione;
            } elseif (preg_match('/^\s*(\[[^\]]+\])/', $azione, $m)) {
                $newLoc = strtolower(trim($m[1]));
            }

            // **Qui ricreo correttamente time + nome + azione**
            $out = $fAzo . $ora . '</SPAN>'
                . $fAzo . $razImg . $name . ' ' . $render($azione,true) . '</SPAN>';
            if ($SessionIsRunning && $NeedsDadi) {
                $out .= '&nbsp;<img height="10" width="10" src="/chatimg/dadod20.gif">&nbsp;' . rand(1,20);
            }
            $out .= '</SPAN>';

            break;
        }
    }
    $ctx=compact('out','location','newLoc','OnGame');
}

/** Renders master/private/URL commands; an early return carries an immediate reply. */
function alphanoChatStaffCommands(array &$ctx): ?array
{
    $pg=$ctx['pg'];$SessionIsRunning=$ctx['SessionIsRunning'];$str=$ctx['str'];
    $out=$ctx['out'];$OnGame=$ctx['OnGame'];$location=$ctx['location'];
    switch($ctx['first']){
        case '#':
            if (($pg['fatepower']||$pg['admin']) && ($SessionIsRunning||$pg['admin'])) {
                $cmd = htmlentities(substr($str,1));
                $out = "<TABLE class=\"column-width-100\"><TR>"
                     . "<TD class=\"MasterKey\">{$cmd}</TD>"
                     . "</TR></TABLE>";
                $OnGame = false;
            }
            break;

        case '%':
            if ($pg['fatepower'] || !empty($GLOBALS['private_chat'])) {
                $txt = htmlentities(substr($str,1));
                $out = "<TABLE class=\"column-width-100\"><TR>"
                     . "<TD class=\"DescrKey\">{$txt}</TD>"
                     . "</TR></TABLE>";
                $OnGame = false;
            }
            break;

        case '@':
            if ($pg['fatepower'] || !empty($GLOBALS['private_chat'])) {
                $url = substr($str,1);
                if (!preg_match('~^(https?://|/(?!/))~i', $url)) { return ['',0,$location,'0']; }
                $out = "<DIV class=\"align-center\"><img src=\"" . htmlspecialchars($url) . "\" /></DIV>";
                $OnGame = false;
            }
            break;    }
    $ctx=compact('out','OnGame');
    return null;
}

/** Renders whisper, animal, incognito and plain speech commands. */
function alphanoChatRoleCommands($db,int $idpg,array &$ctx,callable $render): void
{
    $str=$ctx['str'];$dest=$ctx['dest'];$razImg=$ctx['razImg'];$araldica=$ctx['araldica'];
    $fPar=$ctx['fPar'];$fAzo=$ctx['fAzo'];$name=$ctx['name'];$ora=$ctx['ora'];
    $incog=$ctx['incog'];$newLoc=$ctx['newLoc'];$out=$ctx['out'];
    switch($ctx['first']){
        case '$':
            if (str_contains($str,';')) {
                [$dst,$msg2] = explode(';',substr($str,1),2);
                $dest = GetPGID(trim($dst)) ?: 0;
                if ($dest) {
                    $out = '<EM>'.$razImg.$araldica
                         . $fPar.$name.'</B></SPAN>'
                         . '<SPAN class="String"><U>'.$render($msg2).'</U></SPAN></EM>';
                }
            }
            break;

        case '&':
            if (str_contains($str,';')) {
                [$anim,$act] = explode(';',substr($str,1),2);
                $row = $db->query(
                    "SELECT ea.shortDesc,epg.nome,epgm.id
                       FROM equip ea
                       JOIN equippg epg ON epg.uid=ea.id
                       JOIN equippg epgm ON epgm.idpg=epg.idpg
                      WHERE epg.idpg=? AND epg.nome=? LIMIT 1",
                    [$idpg,$anim]
                )[0]??null;
                if ($row) {
                    consuma($idpg,(int)$row['id']);
                    $out = $fAzo.$ora.'&nbsp;'
                         .htmlentities($row['nome']).', '
                         .htmlentities($row['shortDesc']).' di '
                         .$name.', '.htmlentities($act).'</SPAN>';
                }
            }
            break;

        case '|':
            if ($incog) {
                $out = '<span class="StringN">'.$ora.' <strong>PNG</strong> '.htmlentities(substr($str,1),ENT_QUOTES|ENT_SUBSTITUTE).'</span>';
            }
            break;

        default:
            // parlato normale
            if (preg_match('/^\s*(\[[^\]]+\])/',$str,$m)) {
                $newLoc = strtolower(trim($m[1]));
            }
            $out = '<SPAN class="String">'.$ora.'</SPAN>'
                 .$razImg.$araldica
                 .$fPar.$name.'</B></SPAN> '
                 .'<SPAN class="String">'.$render($str).'</SPAN>';
            break;    }
    $ctx=compact('dest','newLoc','out');
}

/** Applies sunlight damage for a vampire character. */
function alphanoChatSunlight($db,int $idpg,array $ctx): void
{
    $chat=$ctx['chat'];$nome=$ctx['pg']['nome'];$sesso=$ctx['sesso'];$ferite=$ctx['ferite'];
    $razza=$ctx['razza'];$meteo=$ctx['meteo'];$vampiredamage=$ctx['vampiredamage'];
    if ((!function_exists('rpgCreationReady')||!rpgCreationReady()) && $vampiredamage && $out && $razza===2 && !licanTime() && $meteo===0) {
        if (!str_contains($ferite,'Bruciature solari')) {
            $ferite .= ' Bruciature solari';
        }
        if ((int)$pg['puntif'] > MAXPUNTIF/10) {
            $db->execute(
                'UPDATE utenti SET puntif=puntif-?,ferite=? WHERE id=?',
                [MAXPUNTIF/10,$ferite,$idpg]
            );
            vampireDamage($chat,$pg['nome'],$sesso,false);
        } else {
            $db->execute(
                'UPDATE utenti SET puntif=?,razza=7,ferite="" WHERE id=?',
                [MAXPUNTIF,$idpg]
            );
            vampireDamage($chat,$pg['nome'],$sesso,true);
        }
    }
}

/** Counts a lycanthropy action when the character is active. */
function alphanoChatInfectedActions($db,int $idpg,bool $infected,bool $onGame): void
{
    if ($infected && $onGame) {
        $db->execute(
            'UPDATE utenti SET azionilican=azionilican+1 WHERE id=?',
            [$idpg]
        );
    }
}

/** Stores the message in every active live recording. */
function alphanoChatRecordings($db,string $chat,string $out): void
{
    foreach ($db->query('SELECT id FROM registrazioni WHERE chat=?',[$chat]) as $r) {
        $db->execute(
            'INSERT INTO registrazionichat (registrazione,testo) VALUES (?,?)',
            [(int)$r['id'],$out]
        );
    }
}

/** Writes the running session log and rewards its spectators. */
function alphanoChatSessionLog($db,array $ctx): void
{
    $chat=$ctx['chat'];$sessionName=$ctx['sessionName'];$out=$ctx['out'];
    $scriviPart=$ctx['scriviPart'];$partecipanti=$ctx['partecipanti'];$idpg=$ctx['idpg'];
    if ($SessionIsRunning) {
        $logFile = HTMLROOT . "/sessione/log/{$sessionName}.html";
        if(!empty($GLOBALS['alphano_defer_chat_log'])){ $GLOBALS['alphano_deferred_chat_session']=(int)$sessionName; }
        else { file_put_contents($logFile, $out."<br>\n", FILE_APPEND|LOCK_EX); }
        if ($scriviPart) {
            $db->execute(
                'UPDATE sessioni SET partecipanti=? WHERE id=?',
                [$partecipanti.$idpg.';',$sessionName]
            );
        }
        // reward spettatori…
        $ids = array_filter(explode(';',$partecipanti), fn($v)=>$v!=='' && $v!=$idpg);
        if ($ids && !$db->query('SELECT session_id FROM ai_quests WHERE session_id=?',[$sessionName])) {
            $ph = implode(',', array_fill(0,count($ids),'?'));
            $db->execute(
                "UPDATE utenti SET denari=denari+10 WHERE id IN ($ph) AND chat=?",
                [...$ids,$chat]
            );
        }
    }
}
/**
 * buildChatMessage() – logica pari‐pari di addLchat.inc.php
 * restituisce [html, dest, location, lican]
 */
function buildChatMessage(
    PDOMySQLDriver $db,
    int            $idpg,
    string         $chat,
    string         $rawMsg,
    int            $umore,
    string         $location,
    bool           $vampiredamage,
    string         $position = ''
): array {
    initSession();

    $now     = time();
    $str     = trim($rawMsg);
    $stanga  = ord('|');
    $OnGame  = true;
    $dest    = 0;
    $l      = '0';
    $newLoc  = $location;

    if ($str === '') {
        return ['', 0, $location, '0'];
    }

    // 1) aggiorno posizione
    setPosizione($position !== '' ? $position : $chat, $idpg, $chat);

    // 2) dati PG
    $pg = $db->query("SELECT * FROM utenti WHERE id = ?", [$idpg])[0] ?? null;
    if (!$pg || (int)$pg['bloccato'] === 1) {
        return ['', 0, $location, '0'];
    }

    // 3) prigione
    if ($pg['imprigionato']) {
        $prig = $db->query(
            "SELECT prigione FROM city WHERE id = ?",
            [(int)$pg['imprigionato']]
        )[0]['prigione'] ?? '';
        if (strcasecmp($prig, $chat) !== 0) {
            return ['', 0, $location, '0'];
        }
    }

    // 4) sessione in corso?
    $run = $db->query(
        "SELECT id,run,dadi,partecipanti,`lock`
           FROM sessioni
          WHERE chat = ? AND run = 1",
        [$chat]
    )[0] ?? [];
    $SessionIsRunning = (bool)($run['run'] ?? false);
    if (!empty($run['lock']) && empty($pg['fatepower'])) { return ['', 0, $location, '0']; }
    $sessionName      = (int)($run['id'] ?? 0);
    $NeedsDadi        = (bool)($run['dadi'] ?? false);
    $partecipanti     = (string)($run['partecipanti'] ?? '');
    if ($partecipanti === '') {
        $partecipanti = ';';
    }
    $scriviPart = ! str_contains($partecipanti, ";{$idpg};");

    // 5) meteo (vampire damage)
    $meteo = (int)(getMeteo()['cielo'] ?? 0);

    // 6) preparazione markup
    $link  = "<a href=\"javascript:openProfileId('{$idpg}')\">";
    $name=alphanoChatDisplayName($db,$idpg,$umore,$pg);
    $ora    = date('H:i');
    $razza  = (int)$pg['razza'];
    $sesso  = (string)$pg['sesso'];
    $inf    = (int)$pg['infetto'];
    $ferite = (string)$pg['ferite'];

    // Language follows the configured visible form; raw race retains the sunlight rules.
    require_once __DIR__.'/../../libs/transformations.inc.php';
    $losesLanguage=alphanoLosesHumanLanguage($pg);
    $render=function(string $text,bool $action=false)use($losesLanguage,$sesso,&$l):string{
        if(!$losesLanguage){ return alphanoChatText($text,$sesso,$action); }
        $formatted=alphanoChatLupineText($text,$sesso,$action);if($formatted['speech']){ $l='1'; }return $formatted['html'];
    };
    $razImg   = processRazza(alphanoVisibleRace($pg),(int)$pg['etnia']);
    $razImg   = $link . $razImg . '</a>&nbsp;';
    $incog    = ($str[0] ?? '') === '|' && $pg['fatepower'] && $SessionIsRunning;
    $alline   = $incog ? 'N' : ($pg['allineamento'][1] ?? 'N');
    $araldica = '';
    if (! $incog) {
        if ($pg['organizzazione'] && $pg['caricaorganizzazione']) {
            $araldica = GetCaricaOrg((int)$pg['organizzazione'],(int)$pg['caricaorganizzazione'],false,"araldiche[{$idpg}]");
        } elseif ($pg['corporazione'] && $pg['caricacorporazione']) {
            $araldica = GetCaricaCorp((int)$pg['corporazione'],(int)$pg['caricacorporazione'],false,"araldiche[{$idpg}]");
        } else {
            $araldica = processAraldica((int)$pg['araldica'],"araldiche[{$idpg}]");
        }
        $araldica .= '&nbsp;';
    }
    $fPar = "<SPAN class=\"String{$alline}\"><B>";
    $fAzo = "<SPAN class=\"Azione{$alline}\">";

    $first = $str[0] ?? '';
    $out   = '';

    // 7) SWITCH sui comandi
    $ctx=compact('first','str','ora','razImg','fPar','fAzo','name','pg','location','newLoc','OnGame','SessionIsRunning','NeedsDadi','out','dest','araldica','incog');
    alphanoChatBasicOutput($ctx,$render);
    extract($ctx);



    $early=alphanoChatStaffCommands($ctx);
    if($early!==null){return $early;}
    alphanoChatRoleCommands($db,$idpg,$ctx,$render);
    extract($ctx);


    // Persist a semantic presentation type; no schema change, also retained in saved logs.
    if($out!==''){$type=str_starts_with($str,'//')?'ooc':match($first){'+'=>'action','$'=>'whisper','&'=>'animal','#'=>'master',default=>'speech'};$out='<div data-message-type="'.$type.'">'.$out.'</div>';}

    // 8) vampireDamage
    alphanoChatSunlight($db,$idpg,compact('chat','pg','sesso','ferite','razza','meteo','vampiredamage'));

    // 9) Conta azioni lican
    alphanoChatInfectedActions($db,$idpg,(bool)$inf,$OnGame);

    // 10) Registrazioni live
    alphanoChatRecordings($db,$chat,$out);

    // 11) Log sessione in HTML
    alphanoChatSessionLog($db,compact('chat','sessionName','out','scriviPart','partecipanti','idpg'));

    return [$out, $dest, $newLoc, $l];
}
