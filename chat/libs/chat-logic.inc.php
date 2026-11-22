<?php
declare(strict_types=1);

if (!defined('S1192_CHAT_LIBS_CHAT_LOGIC_INC_PHP_68DE83_NBSP')) { define('S1192_CHAT_LIBS_CHAT_LOGIC_INC_PHP_68DE83_NBSP', '&nbsp;'); }

use Alphano\Database\PDOMySQLDriver;
require_once __DIR__.'/../../libs/chat-format.inc.php';

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
    array          $options = []
): array {
    $vampiredamage=(bool)($options['vampiredamage']??false);
    $position=(string)($options['position']??'');
    initSession();

    $now     = time();
    $str     = trim($rawMsg);
    $stanga  = ord('|');
    $onGame  = true;
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
    $sessionIsRunning = (bool)($run['run'] ?? false);
    if (!empty($run['lock']) && empty($pg['fatepower'])) { return ['', 0, $location, '0']; }
    $sessionName      = (int)($run['id'] ?? 0);
    $needsDadi        = (bool)($run['dadi'] ?? false);
    $partecipanti     = (string)($run['partecipanti'] ?? '');
    if ($partecipanti === '') {
        $partecipanti = ';';
    }
    $scriviPart = ! str_contains($partecipanti, ";{$idpg};");

    // 5) meteo (vampire damage)
    $meteo = (int)(getMeteo()['cielo'] ?? 0);

    // 6) preparazione markup
    $link  = "<a href=\"javascript:openProfileId('{$idpg}')\">";
    $name  = htmlentities($pg['nome'], ENT_QUOTES | ENT_SUBSTITUTE);
    if ($umore) {
        $name .= S1192_CHAT_LIBS_CHAT_LOGIC_INC_PHP_68DE83_NBSP . GetUmore($umore);
    }
    $name='<a class="chat-character-name '.alphanoChatGender((string)$pg['sesso']).'" href="javascript:openProfileId(\''.$idpg.'\')">'.$name.'</a>';
    if ($db->query("SELECT 1 FROM equippg WHERE slot='musica' AND idpg=?",[$idpg])) {
        $name .= '&nbsp;<img src=\"/chatimg/musica.gif\" border=\"0\">';
    }
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
    $incog    = ($str[0] ?? '') === '|' && $pg['fatepower'] && $sessionIsRunning;
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
        $araldica .= S1192_CHAT_LIBS_CHAT_LOGIC_INC_PHP_68DE83_NBSP;
    }
    $fPar = "<SPAN class=\"String{$alline}\"><B>";
    $fAzo = "<SPAN class=\"Azione{$alline}\">";

    $first = $str[0] ?? '';
    $out   = '';

    // 7) SWITCH sui comandi
    switch ($first) {
        case '/':
            if(str_starts_with($str,'//')){$onGame=false;$out='<span class="String">'.$ora.'</span> '.$razImg.$fPar.$name.'</B></SPAN> <span class="chat-ooc-text">'.htmlspecialchars(substr($str,2),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span>';break;}
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
            if ($sessionIsRunning && $needsDadi) {
                $out .= '&nbsp;<img height="10" width="10" src="/chatimg/dadod20.gif">&nbsp;' . rand(1,20);
            }
            $out .= '</SPAN>';

            break;
        }


        case '#':
            if (($pg['fatepower']||$pg['admin']) && ($sessionIsRunning||$pg['admin'])) {
                $cmd = htmlentities(substr($str,1));
                $out = "<TABLE class=\"column-width-100\"><TR>"
                     . "<TD class=\"MasterKey\">{$cmd}</TD>"
                     . "</TR></TABLE>";
                $onGame = false;
            }
            break;

        case '%':
            if ($pg['fatepower'] || !empty($GLOBALS['private_chat'])) {
                $txt = htmlentities(substr($str,1));
                $out = "<TABLE class=\"column-width-100\"><TR>"
                     . "<TD class=\"DescrKey\">{$txt}</TD>"
                     . "</TR></TABLE>";
                $onGame = false;
            }
            break;

        case '@':
            if ($pg['fatepower'] || !empty($GLOBALS['private_chat'])) {
                $url = substr($str,1);
                if (!preg_match('~^(https?://|/(?!/))~i', $url)) { return ['',0,$location,'0']; }
                $out = "<DIV class=\"align-center\"><img src=\"" . htmlspecialchars($url) . "\" /></DIV>";
                $onGame = false;
            }
            break;

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
                    $out = $fAzo.$ora.S1192_CHAT_LIBS_CHAT_LOGIC_INC_PHP_68DE83_NBSP
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
            break;
    }

    // Persist a semantic presentation type; no schema change, also retained in saved logs.
    if($out!==''){$type=str_starts_with($str,'//')?'ooc':match($first){'+'=>'action','$'=>'whisper','&'=>'animal','#'=>'master',default=>'speech'};$out='<div data-message-type="'.$type.'">'.$out.'</div>';}

    // 8) vampireDamage
    if ((!function_exists('rpgCreationReady')||!rpgCreationReady()) && $vampiredamage && $out && $razza===2 && !licanTime() && $meteo===0) {
        if (!str_contains($ferite,'Bruciature solari')) {
            $ferite .= ' Bruciature solari';
        }
        if ((int)$pg['puntif'] > MAXPUNTIF/10) {
            $db->execute(
                'UPDATE utenti SET puntif=puntif-?,ferite=? WHERE id=?',
                [MAXPUNTIF/10,$ferite,$idpg]
            );
            vampireDamage($chat,$pg['nome'],false);
        } else {
            $db->execute(
                'UPDATE utenti SET puntif=?,razza=7,ferite="" WHERE id=?',
                [MAXPUNTIF,$idpg]
            );
            vampireDamage($chat,$pg['nome'],true);
        }
    }

    // 9) Conta azioni lican
    if ($inf && $onGame) {
        $db->execute(
            'UPDATE utenti SET azionilican=azionilican+1 WHERE id=?',
            [$idpg]
        );
    }

    // 10) Registrazioni live
    foreach ($db->query('SELECT id FROM registrazioni WHERE chat=?',[$chat]) as $r) {
        $db->execute(
            'INSERT INTO registrazionichat (registrazione,testo) VALUES (?,?)',
            [(int)$r['id'],$out]
        );
    }

    // 11) Log sessione in HTML
    if ($sessionIsRunning) {
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

    return [$out, $dest, $newLoc, $l];
}
