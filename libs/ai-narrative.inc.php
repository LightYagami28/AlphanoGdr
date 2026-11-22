<?php
declare(strict_types=1);
require_once __DIR__ . '/regex-any.inc.php';

const AI_NARRATIVE_CONTROL_PREFIX_PATTERN = '/^(?:\\/\\/|[$#%@|])/u';

/**
 * Bounded, deterministic interpretation of player prose. This is evidence for the
 * dialogue model and rules engine, never an authorization to invent consequences.
 * No database, model, clock or private-memory access belongs in this module.
 */
/** Present bite attempt; merely showing teeth, a past bite or a wish is not one. */
function aiNarrativeBitePattern():string {
    return '(?:affonda|affondo|affondare|affondando)\s+(?:(?:le|i|delle|dei|sue|suoi|proprie|propri|mie|miei|acuminate|acuminati|lunghe|lunghi)\s+){0,4}(?:zanne|denti)';
}
function aiNarrativeActionCue(string $text): bool {
    $text=preg_replace('/\bs[’\x{27}](?=\p{L})/iu','si ',$text)??$text;
    if(preg_match('/\b'.aiNarrativeBitePattern().'\b/iu',$text)){ return true; }
    return (bool)aiRegexAny(['/\b(?:si\s+(?:avvicina))\b/iu','/\b(?:si\s+(?:allontana))\b/iu','/\b(?:si\s+(?:scosta))\b/iu','/\b(?:si\s+(?:volta))\b/iu','/\b(?:si\s+(?:gira))\b/iu','/\b(?:si\s+(?:ferma))\b/iu','/\b(?:si\s+(?:alza))\b/iu','/\b(?:si\s+(?:siede))\b/iu','/\b(?:si\s+(?:china))\b/iu','/\b(?:si\s+(?:inchina))\b/iu','/\b(?:si\s+(?:scaglia))\b/iu','/\b(?:si\s+(?:scaraventa))\b/iu','/\b(?:si\s+(?:avventa))\b/iu','/\b(?:si\s+(?:difende))\b/iu','/\b(?:si\s+(?:libera))\b/iu','/\b(?:si\s+(?:accascia))\b/iu','/\b(?:si\s+(?:gratta))\b/iu','/\b(?:si\s+(?:sporge))\b/iu','/\b(?:si\s+(?:appoggia))\b/iu','/\b(?:si\s+(?:accosta))\b/iu','/\b(?:si\s+(?:inginocchia))\b/iu','/\b(?:si\s+(?:ritrae))\b/iu','/\b(?:si\s+(?:affaccia))\b/iu','/\b(?:si\s+(?:scrolla))\b/iu','/\b(?:si\s+(?:protende))\b/iu','/\b(?:si\s+(?:liscia))\b/iu','/\b(?:si\s+(?:tocca))\b/iu','/\b(?:si\s+(?:sistema))\b/iu','/\b(?:mi\s+(?:avvicino))\b/iu','/\b(?:mi\s+(?:allontano))\b/iu','/\b(?:mi\s+(?:volto))\b/iu','/\b(?:mi\s+(?:fermo))\b/iu','/\b(?:mi\s+(?:alzo))\b/iu','/\b(?:mi\s+(?:siedo))\b/iu','/\b(?:mi\s+(?:chino))\b/iu','/\b(?:mi\s+(?:difendo))\b/iu','/\b(?:mi\s+(?:libero))\b/iu','/\b(?:mi\s+(?:arrendo))\b/iu','/\b(?:(?:tenta|cerca)\s+di)\b/iu','/\b(?:sorride)\b/iu','/\b(?:sorrido)\b/iu','/\b(?:annuisce)\b/iu','/\b(?:annuisco)\b/iu','/\b(?:scuote)\b/iu','/\b(?:scuoto)\b/iu','/\b(?:inarca)\b/iu','/\b(?:inarco)\b/iu','/\b(?:aggrotta)\b/iu','/\b(?:aggrotto)\b/iu','/\b(?:schiude)\b/iu','/\b(?:schiudo)\b/iu','/\b(?:arriccia)\b/iu','/\b(?:arriccio)\b/iu','/\b(?:inclina)\b/iu','/\b(?:inclino)\b/iu','/\b(?:incrocia)\b/iu','/\b(?:incrocio)\b/iu','/\b(?:accenna)\b/iu','/\b(?:accenno)\b/iu','/\b(?:ride)\b/iu','/\b(?:rido)\b/iu','/\b(?:sospira)\b/iu','/\b(?:sospiro)\b/iu','/\b(?:sbuffa)\b/iu','/\b(?:sbuffo)\b/iu','/\b(?:tace)\b/iu','/\b(?:taccio)\b/iu','/\b(?:arrossisce)\b/iu','/\b(?:arrossisco)\b/iu','/\b(?:deglutisce)\b/iu','/\b(?:deglutisco)\b/iu','/\b(?:indica)\b/iu','/\b(?:indico)\b/iu','/\b(?:accarezza)\b/iu','/\b(?:accarezzo)\b/iu','/\b(?:inspira)\b/iu','/\b(?:inspiro)\b/iu','/\b(?:espira)\b/iu','/\b(?:espiro)\b/iu','/\b(?:osserva)\b/iu','/\b(?:osservo)\b/iu','/\b(?:guarda)\b/iu','/\b(?:guardo)\b/iu','/\b(?:abbassa)\b/iu','/\b(?:abbasso)\b/iu','/\b(?:solleva)\b/iu','/\b(?:sollevo)\b/iu','/\b(?:stringe)\b/iu','/\b(?:stringo)\b/iu','/\b(?:afferra)\b/iu','/\b(?:afferro)\b/iu','/\b(?:afferrando)\b/iu','/\b(?:allunga)\b/iu','/\b(?:allungo)\b/iu','/\b(?:lascia)\b/iu','/\b(?:lascio)\b/iu','/\b(?:arretra)\b/iu','/\b(?:arretro)\b/iu','/\b(?:balza)\b/iu','/\b(?:balzo)\b/iu','/\b(?:avanza)\b/iu','/\b(?:avanzo)\b/iu','/\b(?:cammina)\b/iu','/\b(?:cammino)\b/iu','/\b(?:entra)\b/iu','/\b(?:entro)\b/iu','/\b(?:esce)\b/iu','/\b(?:esco)\b/iu','/\b(?:sferra)\b/iu','/\b(?:sferro)\b/iu','/\b(?:colpisce)\b/iu','/\b(?:colpisco)\b/iu','/\b(?:attacca)\b/iu','/\b(?:attacco)\b/iu','/\b(?:aggredisce)\b/iu','/\b(?:aggredisco)\b/iu','/\b(?:morde)\b/iu','/\b(?:mordo)\b/iu','/\b(?:azzanna)\b/iu','/\b(?:azzanno)\b/iu','/\b(?:pugnala)\b/iu','/\b(?:spara)\b/iu','/\b(?:scocca)\b/iu','/\b(?:sfodera)\b/iu','/\b(?:estrae)\b/iu','/\b(?:ruba)\b/iu','/\b(?:rubo)\b/iu','/\b(?:arresta)\b/iu','/\b(?:arresto)\b/iu','/\b(?:fugge)\b/iu','/\b(?:fuggo)\b/iu','/\b(?:scappa)\b/iu','/\b(?:scappo)\b/iu','/\b(?:dice)\b/iu','/\b(?:dico)\b/iu','/\b(?:chiede)\b/iu','/\b(?:chiedo)\b/iu','/\b(?:domanda)\b/iu','/\b(?:risponde)\b/iu','/\b(?:rispondo)\b/iu','/\b(?:mormora)\b/iu','/\b(?:sussurra)\b/iu','/\b(?:grida)\b/iu','/\b(?:urla)\b/iu','/\b(?:porge)\b/iu','/\b(?:porgo)\b/iu','/\b(?:attende)\b/iu','/\b(?:attendo)\b/iu','/\b(?:ricarica)\b/iu','/\b(?:ricarico)\b/iu','/\b(?:usa)\b/iu','/\b(?:uso)\b/iu','/\b(?:utilizza)\b/iu','/\b(?:utilizzo)\b/iu','/\b(?:prende)\b/iu','/\b(?:prendo)\b/iu','/\b(?:posa)\b/iu','/\b(?:poso)\b/iu','/\b(?:beve)\b/iu','/\b(?:bevo)\b/iu','/\b(?:sviene)\b/iu','/\b(?:crolla)\b/iu','/\b(?:sente)\b/iu','/\b(?:avverte)\b/iu','/\b(?:respira)\b/iu','/\b(?:rivolge)\b/iu','/\b(?:proferisce)\b/iu','/\b(?:proferire)\b/iu'],$text);
}

/** A third-person capability claim may continue narration, but never proves it true. */
function aiNarrativeDescriptiveClaim(string $text):bool {
    return (bool)aiRegexAny(['/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:sua)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:sua)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:sua)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:propria)\s+(?:natura))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:propria)\s+(?:stirpe))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:la\s+(?:propria)\s+(?:razza))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:suo)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:suo)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:suo)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:corpo))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:sangue))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:lo)\s+rende\s+(?:insensibile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:immune)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:resistente)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:invulnerabile)\b/iu','/^(?:il\s+(?:proprio)\s+(?:lignaggio))\b[^.!?]{0,90}\b(?:la)\s+rende\s+(?:insensibile)\b/iu'],trim($text));
}

/** A GDR phrase beginning "a voce alta"/"i piedi" is not an HTML a/i element. */
function aiNarrativeHtmlTag(string $token):?array {
    if(!preg_match('~^<(/?)([a-z][\w:-]*)(\s[^>]*|/?)>$~iu',$token,$m)){ return null; }
    $tag=mb_strtolower($m[2]);
    if(!in_array($tag,['a','b','i','em','strong','span','div','p','br','img','font','small','u','s','section','blockquote','speech','dialogue','parlato','azione','action','act','talk'],true)){ return null; }
    $tail=trim($m[3]);
    if($tail!==''&&$tail!=='/'&&!preg_match('/(?:^|\s)[\w:-]+\s*=/u',$tail)&&!preg_match('/^(?:hidden|disabled|checked|selected|open|reversed|multiple)(?:\s|\/?$)/iu',$tail)){ return null; }
    return ['tag'=>$tag,'closing'=>$m[1]==='/','attributes'=>$tail];
}

/** Plain transcript preserving historical <spoken words> notation, never HTML. */
function aiNarrativePlain(string $raw):string {
    $raw=html_entity_decode(mb_substr($raw,0,12000),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $raw=preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~isu','',$raw)??$raw;
    $raw=preg_replace_callback('~<[^<>\n]{1,500}>~u',static function(array $m):string {
        if(aiNarrativeHtmlTag($m[0])!==null){ return ' '; }
        // A tag with attributes is actual markup; simple GDR brackets are prose.
        if(preg_match('/\b[\w:-]+\s*=|^<\//u',$m[0])){ return ''; }
        return $m[0];
    },$raw)??$raw;
    return trim(preg_replace('/\s+/u',' ',$raw)??$raw);
}

/** Subjunctive wishes, negations, recollections and reported outcomes are not new acts. */
function aiNarrativeClauseIntent(string $text): ?string {
    $v=mb_strtolower(trim($text));$v=preg_replace('/\bs[’\x{27}](?=\p{L})/u','si ',$v)??$v;
    if($v===''||preg_match(AI_NARRATIVE_CONTROL_PREFIX_PATTERN,$v)){ return null; }
    if(aiRegexAny(['/\b(?:non\s+(?:voglio|intendo)\s+(?:più\s+)?(?:combattere|continuare|allenarmi))\b/u','/\b(?:mi fermo)\b/u','/\b(?:si ferma)\b/u','/\b(?:smette)\b/u','/\b(?:smetto)\b/u','/\b(?:mi arrendo)\b/u','/\b(?:si arrende)\b/u','/\b(?:fermiamoci)\b/u','/\b(?:terminiamo)\b/u','/\b(?:interromp[eo]\s+(?:l[’\x{27}]|il\s+)(?:attacco|assalto|combattimento))\b/u','/\b(?:cess[ao]\s+(?:l[’\x{27}]|il\s+)(?:attacco|assalto|combattimento))\b/u','/\b(?:finisce l.allenamento)\b/u','/\b(?:abbassa (?:le armi|la guardia))\b/u','/^basta(?:[,.!]|\s*$)/u'],$v)){ return 'fine'; }
    if(preg_match('/\b(?:allenamento|alleniamoci|esercitazione)\b/u',$v)&&preg_match('/\b(?:voglio|chiedo|facciamo|iniziamo|alleniamoci|invita|propone)\b/u',$v)){ return 'allenamento'; }
    if(aiRegexAny(['/\b(?:ordina)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:chiede)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:invita)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:propone)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:implora)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:minaccia)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:promette)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:dice)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:risponde)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u','/\b(?:proferisce)\b.{0,90}\b(?:di\s+)?(?:attaccare|colpire|mordere|uccidere|arrestare|rubare)\b/u'],$v)){ return 'azione'; }
    if(preg_match('/\b(?:vede|guarda|osserva|sente|ascolta|nota)\b.{0,90}\b(?:che|mentre|come)\b.{0,60}\b(?:attacca|colpisce|morde|azzanna|spara|pugnala|'.aiNarrativeBitePattern().')\b/u',$v)){ return 'azione'; }
    // Keep uncertainty local to this clause: a later "ma colpisce" is assessed independently.
    $hypothesis=preg_replace('/\banche\s+se\b/u','sebbene',$v)??$v;
    if(aiRegexAny(['/\b(?:se)\b/u','/\b(?:qualora)\b/u','/\b(?:vorrei)\b/u','/\b(?:vorrebbe)\b/u','/\b(?:potrei)\b/u','/\b(?:potrebbe)\b/u','/\b(?:avrei)\b/u','/\b(?:avrebbe)\b/u','/\b(?:farei)\b/u','/\b(?:farebbe)\b/u','/\b(?:attaccherei)\b/u','/\b(?:colpirei)\b/u','/\b(?:morderei)\b/u','/\b(?:ucciderei)\b/u','/\b(?:sarebbe)\b/u','/\b(?:racconta)\b/u','/\b(?:ricorda)\b/u','/\b(?:immagina)\b/u','/\b(?:pensa)\b/u','/\b(?:sogna)\b/u','/\b(?:riferisce)\b/u','/\b(?:riporta)\b/u','/\b(?:diceva)\b/u','/\b(?:ha detto)\b/u'],$hypothesis)){ return null; }
    // Negation applies to its verbs, not an earlier independent manoeuvre:
    // "afferra il busto, senza colpirlo né ferirlo" still declares a grapple.
    $verb='(?:attacc\p{L}*|aggred\p{L}*|colpi\p{L}*|mord\p{L}*|azzann\p{L}*|affond\p{L}*|romp\p{L}*|spezz\p{L}*|strangol\p{L}*|sgozz\p{L}*|feri\p{L}*|uccid\p{L}*|ruba\p{L}*|arrest\p{L}*|fugg\p{L}*|scapp\p{L}*|sferra\p{L}*|afferra\p{L}*|us\p{L}*|invoc\p{L}*)';
    $negative='/\b(?:non|senza|evita\s+di|rinuncia\s+a)\s+(?:(?:vuole|voglio|desidera|desidero|intende|intendo|tenta|cerca|di|si|lo|la|li|le|gli|affatto|mai|ancora|più)\s+){0,5}'.$verb.'(?:\s+(?:né|e|o)\s+'.$verb.')*\b/u';
    $v=trim(preg_replace($negative,' ',$v)??$v);
    if(preg_match('/\b'.aiNarrativeBitePattern().'\b/u',$v)){ return 'assalto'; }
    // A noun such as "l'attacco" is not the first-person verb "attacco".
    $v=preg_replace('/\b(?:l[’\x{27}]|(?:un|il|questo|quell[oa])\s+)(?:attacco|morso)\b/u','la manovra',$v)??$v;
    if(aiRegexAny(['/\b(?:lascia)\b.{0,65}\b(?:presa)\b/u','/\b(?:lascia)\b.{0,65}\b(?:braccio)\b/u','/\b(?:lascia)\b.{0,65}\b(?:braccia)\b/u','/\b(?:lascia)\b.{0,65}\b(?:polso)\b/u','/\b(?:lascia)\b.{0,65}\b(?:collo)\b/u','/\b(?:lascia)\b.{0,65}\b(?:mano)\b/u','/\b(?:lascia)\b.{0,65}\b(?:mani)\b/u','/\b(?:lascia)\b.{0,65}\b(?:catturato)\b/u','/\b(?:lascia)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:lascio)\b.{0,65}\b(?:presa)\b/u','/\b(?:lascio)\b.{0,65}\b(?:braccio)\b/u','/\b(?:lascio)\b.{0,65}\b(?:braccia)\b/u','/\b(?:lascio)\b.{0,65}\b(?:polso)\b/u','/\b(?:lascio)\b.{0,65}\b(?:collo)\b/u','/\b(?:lascio)\b.{0,65}\b(?:mano)\b/u','/\b(?:lascio)\b.{0,65}\b(?:mani)\b/u','/\b(?:lascio)\b.{0,65}\b(?:catturato)\b/u','/\b(?:lascio)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:presa)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:braccio)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:braccia)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:polso)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:collo)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:mano)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:mani)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:catturato)\b/u','/\b(?:rilascia)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:presa)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:braccio)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:braccia)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:polso)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:collo)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:mano)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:mani)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:catturato)\b/u','/\b(?:rilascio)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:presa)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:braccio)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:braccia)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:polso)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:collo)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:mano)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:mani)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:catturato)\b/u','/\b(?:scioglie)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:presa)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:braccio)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:braccia)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:polso)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:collo)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:mano)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:mani)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:catturato)\b/u','/\b(?:sciolgo)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:allenta)\b.{0,65}\b(?:presa)\b/u','/\b(?:allenta)\b.{0,65}\b(?:braccio)\b/u','/\b(?:allenta)\b.{0,65}\b(?:braccia)\b/u','/\b(?:allenta)\b.{0,65}\b(?:polso)\b/u','/\b(?:allenta)\b.{0,65}\b(?:collo)\b/u','/\b(?:allenta)\b.{0,65}\b(?:mano)\b/u','/\b(?:allenta)\b.{0,65}\b(?:mani)\b/u','/\b(?:allenta)\b.{0,65}\b(?:catturato)\b/u','/\b(?:allenta)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:allento)\b.{0,65}\b(?:presa)\b/u','/\b(?:allento)\b.{0,65}\b(?:braccio)\b/u','/\b(?:allento)\b.{0,65}\b(?:braccia)\b/u','/\b(?:allento)\b.{0,65}\b(?:polso)\b/u','/\b(?:allento)\b.{0,65}\b(?:collo)\b/u','/\b(?:allento)\b.{0,65}\b(?:mano)\b/u','/\b(?:allento)\b.{0,65}\b(?:mani)\b/u','/\b(?:allento)\b.{0,65}\b(?:catturato)\b/u','/\b(?:allento)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:molla)\b.{0,65}\b(?:presa)\b/u','/\b(?:molla)\b.{0,65}\b(?:braccio)\b/u','/\b(?:molla)\b.{0,65}\b(?:braccia)\b/u','/\b(?:molla)\b.{0,65}\b(?:polso)\b/u','/\b(?:molla)\b.{0,65}\b(?:collo)\b/u','/\b(?:molla)\b.{0,65}\b(?:mano)\b/u','/\b(?:molla)\b.{0,65}\b(?:mani)\b/u','/\b(?:molla)\b.{0,65}\b(?:catturato)\b/u','/\b(?:molla)\b.{0,65}\b(?:prigioniero)\b/u','/\b(?:mollo)\b.{0,65}\b(?:presa)\b/u','/\b(?:mollo)\b.{0,65}\b(?:braccio)\b/u','/\b(?:mollo)\b.{0,65}\b(?:braccia)\b/u','/\b(?:mollo)\b.{0,65}\b(?:polso)\b/u','/\b(?:mollo)\b.{0,65}\b(?:collo)\b/u','/\b(?:mollo)\b.{0,65}\b(?:mano)\b/u','/\b(?:mollo)\b.{0,65}\b(?:mani)\b/u','/\b(?:mollo)\b.{0,65}\b(?:catturato)\b/u','/\b(?:mollo)\b.{0,65}\b(?:prigioniero)\b/u'],$v)){ return 'rilascio'; }
    if(preg_match('/\b(?:ricarica|ricarico)\b/u',$v)){ return 'ricarica'; }
    if(aiRegexAny(['/\b(?:si difende)\b/u','/\b(?:mi difendo)\b/u','/\b(?:tenta di difendersi)\b/u','/\b(?:cerca di difendersi)\b/u','/\b(?:para)\b/u','/\b(?:paro)\b/u','/\b(?:schiva)\b/u','/\b(?:schivo)\b/u','/\b(?:alza (?:lo scudo|la guardia))\b/u'],$v)){ return 'difesa'; }
    if(aiRegexAny(['/\b(?:fugge)\b/u','/\b(?:fuggo)\b/u','/\b(?:scappa)\b/u','/\b(?:scappo)\b/u','/\b(?:fuggire)\b/u','/\b(?:allontanarsi)\b/u','/\b(?:si allontana)\b/u','/\b(?:mi allontano)\b/u','/\b(?:ritirarsi)\b/u','/\b(?:si ritira)\b/u','/\b(?:mi ritiro)\b/u','/\b(?:divincola\p{L}*)\b/u','/\b(?:svincola\p{L}*)\b/u','/\b(?:liberarsi dalla presa)\b/u'],$v)){ return 'fuga'; }
    if(aiRegexAny(['/\b(?:mantiene)\b.{0,65}\b(?:presa|trattenut\p{L}*|busto|pols\p{L}*)\b/u','/\b(?:mantengo)\b.{0,65}\b(?:presa|trattenut\p{L}*|busto|pols\p{L}*)\b/u','/\b(?:continua a mantenere)\b.{0,65}\b(?:presa|trattenut\p{L}*|busto|pols\p{L}*)\b/u','/\b(?:continua a trattenere)\b.{0,65}\b(?:presa|trattenut\p{L}*|busto|pols\p{L}*)\b/u'],$v)){ return preg_match('/\bsping\p{L}*\b/u',$v)?'assalto':'azione'; }
    if(preg_match('/\b(?:spinge|spingo|spingere|spingerlo|spingerla|spingerli|spingerle)\b/u',$v)){ return 'assalto'; }
    if(aiRegexAny(['/\b(?:usa).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u','/\b(?:uso).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u','/\b(?:utilizza).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u','/\b(?:utilizzo).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u','/\b(?:invoca).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u','/\b(?:invoco).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u','/\b(?:tenta).{0,55}(?:ipnosi|dominazione|persuasione|potere|controllo emotivo|sguardo)\b/u'],$v)){ return 'potere'; }
    // Present-tense performance/attempt, not the noun "morso" or past participles.
    foreach(['assalto'=>'/\b(?:attacca|attacco|attaccare|attaccarlo|attaccarla|assale|assalgo|aggredisce|aggredisco|aggredire|colpisce|colpisco|colpire|colpirlo|colpirla|trafigge|ferisce|ferire|rompe|spezza|strangola|sgozza|pugnala|spara|scocca|uccidere|morde|mordo|mordere|morderlo|morderla|mordendolo|mordendola|azzanna|azzanno|azzannare|azzannarlo|azzannarla|immobilizza|immobilizzo|immobilizzare|afferra|afferro|afferrando|afferrare|afferrargli|afferrarle|si scaglia|si scaraventa|si avventa|sferra|sferro|(?:colpir|morder|afferrar|azzannar|immobilizzar|attaccar|romper|spezzar|strangolar|sgozzar|ferir|uccider)(?:lo|la|li|le))\b/u','furto'=>'/\b(?:ruba|rubo|rapina|borseggia|rubare)\b/u','arresto'=>'/\b(?:arresta|arresto|sequestra|incatena|lega|arrestare)\b/u'] as $intent=>$pattern) {
        if(preg_match($pattern,$v)&&!aiRegexAny(['/\b(?:vuole)\b/u','/\b(?:voglio)\b/u','/\b(?:desidera)\b/u','/\b(?:desidero)\b/u','/\b(?:minaccia\s+di)\b/u','/\b(?:promette\s+di)\b/u','/\b(?:chiede\s+di)\b/u','/\b(?:ordina\s+di)\b/u'],$v)){ return $intent; }
    }
    return aiNarrativeActionCue($v)?'azione':null;
}

/** Classify free prose, also splitting unquoted speech before a narrated gesture. */
function aiNarrativeFreeSegments(string $text,string $prior='auto',string $source='prose'): array {
    $text=trim($text);if($text===''){ return []; }
    if(preg_match(AI_NARRATIVE_CONTROL_PREFIX_PATTERN,$text)){ return [['kind'=>'ooc','text'=>$text,'source'=>$source,'confidence'=>1.0]]; }
    $parts=preg_split('/(?<=[.!?;])\s+|\s+(?=(?:detto\s+(?:ciò\s+)?|poi\s+)(?:si\s+(?:scaglia|scaraventa|avventa|avvicina|allontana|volta)|tenta\s+di|cerca\s+di))|,\s+(?=(?:sorride|annuisce|abbassa|solleva|si\s+(?:volta|limita))\b)|\s+(?=ma\s+(?:tenta|cerca|si|attacca|attacco|colpisce|colpisco)\b)/iu',$text,-1,PREG_SPLIT_NO_EMPTY);
    $out=[];$previousKind='';
    foreach($parts?:[$text] as $part) {
        $part=trim($part);if($part===''){ continue; }
        $thought=(bool)aiRegexAny(['/\b(?:pensa(?:\s+tra\s+sé)?)\b/iu','/\b(?:pensando)\b/iu','/\b(?:immagina)\b/iu','/\b(?:ricorda)\b/iu','/\b(?:sogna)\b/iu','/\b(?:tra\s+sé)\b/iu'],$part);
        $cue=aiNarrativeActionCue($part)||(bool)preg_match('/\bsi limita\b/iu',$part);
        // In a narrated sequence, "La sua natura lo rende immune" is the
        // player's descriptive claim, not a new utterance. Keep this narrow:
        // first-person dialogue and isolated unmarked statements stay speech;
        // explicit quotes/talk tags are already protected by the caller.
        if(!$cue&&$previousKind==='action'&&aiNarrativeDescriptiveClaim($part)){ $cue=true; }
        if($prior!=='action'&&aiRegexAny(['/^(?:perché)\b.+\?\s*$/iu','/^(?:come)\b.+\?\s*$/iu','/^(?:che)\b.+\?\s*$/iu','/^(?:chi)\b.+\?\s*$/iu','/^(?:dove)\b.+\?\s*$/iu','/^(?:quando)\b.+\?\s*$/iu','/^(?:quanto)\b.+\?\s*$/iu','/^(?:cosa)\b.+\?\s*$/iu','/^(?:potete)\b.+\?\s*$/iu','/^(?:potreste)\b.+\?\s*$/iu','/^(?:puoi)\b.+\?\s*$/iu','/^(?:vorresti)\b.+\?\s*$/iu','/^(?:volete)\b.+\?\s*$/iu'],$part)){ $cue=false; }
        $kind=$thought?'thought':(($prior==='action'||$cue)?'action':'speech');
        $confidence=.75;
        if($thought||$cue){ $confidence=.88; }
        elseif($prior==='action'){ $confidence=.86; }
        $out[]=['kind'=>$kind,'text'=>$part,'source'=>$source,'confidence'=>$confidence];
        $previousKind=$kind;
    }
    return $out;
}

/**
 * Quotes, emotes and historical GDR angle brackets may be nested in either channel.
 * Only real HTML is removed: <grazie> is dialogue and must survive strip_tags().
 * options.channel can carry the sending channel but never overrides inner evidence.
 */
function aiNarrativeParse(string $raw,array $options=[]): array {
    $raw=mb_substr($raw,0,12000);
    $text=html_entity_decode($raw,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~isu','',$text)??$text;
    $leading=trim($text);$prior=str_starts_with($leading,'+')?'action':(in_array($options['channel']??'',['action','speech'],true)?$options['channel']:'auto');
    if(str_starts_with($leading,'+')){ $text=preg_replace('/^\s*\+\s*/u','',$text)??$text; }
    if(($options['channel']??'')==='ooc'||preg_match(AI_NARRATIVE_CONTROL_PREFIX_PATTERN,trim($text))){ return ['segments'=>[['kind'=>'ooc','text'=>trim($text),'source'=>'prefix','confidence'=>1.0]],'speech'=>'','action'=>'','thought'=>'','intent'=>null,'acts'=>[],'requires_action_clarification'=>false]; }
    $tokens=preg_split('/(<+[^<>\n]{1,500}>+|>+[^<>\n]{1,1800}<+|«|»|“|”|"|(?<![\p{L}\p{N}])[\x{27}‘]|[\x{27}’](?![\p{L}\p{N}])|\*{1,2}|\(|\)|\[|\])/u',$text,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY)?:[];
    $stack=[];$segments=[];$buffer='';
    $flush=static function()use(&$buffer,&$segments,&$stack,$prior):void {
        if(trim($buffer)===''){$buffer='';return;}
        $frame=end($stack);$kind=$frame['kind']??'auto';$source=$frame['source']??'prose';
        if(in_array($kind,['speech','thought','ooc'],true)){ $segments[]=['kind'=>$kind,'text'=>trim($buffer),'source'=>$source,'confidence'=>.98]; }
        else { foreach(aiNarrativeFreeSegments($buffer,$kind==='action'?'action':$prior,$source) as $s){ $segments[]=$s; } }
        $buffer='';
    };
    foreach($tokens as $tokenIndex=>$token) {
        if(str_starts_with($token,'>')&&str_ends_with($token,'<')){
            $protected=$stack[count($stack)-1]['kind']??'';$flush();$body=preg_replace('/^>+|<+$/u','',$token)??$token;
            if(in_array($protected,['thought','ooc'],true)){ $segments[]=['kind'=>$protected,'text'=>$body,'source'=>'gdr-angle-inverse','confidence'=>.98]; }
            else { foreach(aiNarrativeParse($body)['segments'] as $s){$s['source']='gdr-angle-inverse';$segments[]=$s;} }continue;
        }
        if(str_starts_with($token,'<')&&str_ends_with($token,'>')) {
            if(($html=aiNarrativeHtmlTag($token))!==null) {
                $tag=$html['tag'];$flush();
                if($html['closing']){if(($stack[count($stack)-1]['close']??'')==='</'.$tag.'>'){ array_pop($stack); }continue;}
                if(in_array($tag,['br','p','div'],true)){ $buffer.=' '; }
                $class=preg_match('/\b(?:class|data-kind)\s*=\s*["\x{27}]([^"\x{27}]*)/iu',$token,$cm)?mb_strtolower($cm[1]):'';
                $kind=in_array($tag,['speech','dialogue','parlato','talk'],true)||preg_match('/(?:chat-speech|\bparlato\b|\bstring\b)/u',$class)?'speech':(in_array($tag,['azione','action','act'],true)||preg_match('/(?:chat-action|\bazioni?\b)/u',$class)?'action':'');
                if(!in_array($tag,['br','img'],true)&&!str_ends_with($token,'/>')){ $stack[]=['close'=>'</'.$tag.'>','kind'=>$kind!==''?$kind:($stack[count($stack)-1]['kind']??'auto'),'source'=>'html']; }
                continue;
            }
            // <osserva l'uomo> and <grazie> are both used in real logs.
            $protected=$stack[count($stack)-1]['kind']??'';$flush();
            $body=preg_replace('/^<+|>+$/u','',$token)??$token;
            if(in_array($protected,['thought','ooc'],true)){ $segments[]=['kind'=>$protected,'text'=>$body,'source'=>'gdr-angle','confidence'=>.98]; }
            else { foreach(aiNarrativeParse($body)['segments'] as $s){$s['source']='gdr-angle';$segments[]=$s;} }continue;
        }
        $top=end($stack);
        if($top&&$token===$top['close']){$flush();array_pop($stack);continue;}
        // "sull' entrata" is an elision, not an opening quote hiding the next ].
        if(in_array($token,["'",'’'],true)&&preg_match('/[\p{L}\p{N}]$/u',$buffer)){$buffer.=$token;continue;}
        $pairs=['«'=>'»','“'=>'”','"'=>'"',"'"=>"'",'‘'=>'’','*'=>'*','**'=>'**','('=>')','['=>']'];
        if(isset($pairs[$token])) {
            $before=$buffer;$flush();
            $kind=in_array($token,['*','**','(','['],true)?'action':'speech';
            if(in_array($top['kind']??'',['thought','ooc'],true)){ $kind=$top['kind']; }
            if($kind==='speech'&&preg_match('/\b(?:pensa|pensando|immagina|ricorda|sogna|tra\s+sé)\b[^.!?]*$/iu',$before)){ $kind='thought'; }
            if(in_array($token,['(','['],true)&&preg_match('/^\s*(?:off|ooc|fuori\s+gioco)\b/iu',$tokens[$tokenIndex+1]??'')){ $kind='ooc'; }
            $stack[]=['close'=>$pairs[$token],'kind'=>$kind,'source'=>in_array($token,['*','**'],true)?'emote':(in_array($token,['(','['],true)?'aside':'quote')];continue;
        }
        if(in_array($token,['»','”',')',']','’'],true)){$flush();continue;}
        $buffer.=$token;
    }
    $flush();$speech=[];$action=[];$thought=[];$acts=[];
    foreach($segments as $s) {
        if($s['kind']==='speech'){ $speech[]=$s['text']; }elseif($s['kind']==='action'){ $action[]=$s['text']; }elseif($s['kind']==='thought'){ $thought[]=$s['text']; }
        if($s['kind']==='action'){ foreach(preg_split('/\s+(?:ma|poi|quindi|per\s+poi)\s+|[;.!?]+/iu',$s['text'],-1,PREG_SPLIT_NO_EMPTY)?:[] as $clause){$intent=aiNarrativeClauseIntent($clause);if($intent===null&&preg_match('/\bsi limita\b/iu',$clause)){ $intent='azione'; }if($intent!==null){ $acts[]=['intent'=>$intent,'text'=>trim($clause),'confidence'=>$s['confidence']]; }} }
    }
    $intent=null;
    foreach($acts as $act){ if($act['intent']!=='azione'){$intent=$act['intent'];break;} }
    if($intent===null&&$action&&$acts){ $intent='azione'; }
    // Requests to train/stop are meaningful spoken choices, never spoken attacks.
    if($intent===null){ foreach($speech as $s){$v=aiNarrativeClauseIntent($s);if(in_array($v,['allenamento','fine'],true)){$intent=$v;break;}} }
    return ['segments'=>array_slice($segments,0,120),'speech'=>implode(' ',$speech),'action'=>implode(' ',$action),'thought'=>implode(' ',$thought),'intent'=>$intent,'acts'=>$acts,'requires_action_clarification'=>false];
}

function aiNarrativeActorKey(array $actor):string{return ($actor['kind']??'npc').':'.(int)($actor['id']??0);}
function aiNarrativeMentions(string $text,string $name):bool{return $name!==''&&(bool)preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name,'/').'(?![\p{L}\p{N}])/iu',$text);}

/** A named but unavailable object cannot silently become the selected bystander. */
function aiNarrativeUnresolvedObject(string $focus,bool $hasRole):bool {
    $generic=['il','lo','la','gli','lui','lei','loro','voi','tu','te','ti','vi','suo','sua','suoi','sue','un','uno','una','con','contro','verso','sul','sulla','sullo','alla','alle','allo','collo','testa','spalla','spalle','braccio','braccia','polso','polsi','mano','mani','petto','fianco','fianchi','torace','viso','gola','gambe','gamba','denti','artigli','pugno','pugni','calcio','calci','gomito','ginocchio','corpo','presa','scudo','spada','avversario','avversaria','bersaglio','interlocutore','interlocutrice','usa','uso','utilizza','utilizzo','invoca','invoco','tenta','cerca','arco','balestra','dardo','freccia','lancia','pugnale','fucile','pistola','moschetto','carabina','sciabola','guardia','guardie','sergente','capitano','uomo','donna','vampiro','vampira','licantropo','licantropa','lupo','lupa','qualcuno','chiunque','ancora','forte','piano','davanti','dietro','addosso','avanti'];
    preg_match_all('/(?<![\p{L}\p{N}])\p{Lu}[\p{L}\p{M}]{2,}/u',$focus,$proper,PREG_OFFSET_CAPTURE);
    foreach($proper[0] as [$word,$offset]){
        if(in_array(mb_strtolower($word),$generic,true)){ continue; }
        // A known office may be qualified by a place (titolo e territorio).
        if($hasRole&&preg_match('/\b(?:di|del|dello|della|da|dal|dalla)\s*$/iu',substr($focus,0,$offset))){ continue; }
        return true;
    }
    if($hasRole){ return false; }
    // Lowercase character names are common. Check only a simple direct object,
    // leaving methods/body locations, pronouns and ordinary modifiers to context.
    $object=preg_replace('/^\s*(?:(?:contro|verso|su|a|ad|al|allo|alla|il|lo|la|un|uno|una)\s+|[lL][’\x{27}])*/u','',trim($focus))??trim($focus);
    if(aiRegexAny(['/^([\p{L}\p{M}]{3,})(?:\s+(?:con)\b.*)?[.!?]*$/u','/^([\p{L}\p{M}]{3,})(?:\s+(?:al)\b.*)?[.!?]*$/u','/^([\p{L}\p{M}]{3,})(?:\s+(?:alla)\b.*)?[.!?]*$/u','/^([\p{L}\p{M}]{3,})(?:\s+(?:sul)\b.*)?[.!?]*$/u','/^([\p{L}\p{M}]{3,})(?:\s+(?:sulla)\b.*)?[.!?]*$/u','/^([\p{L}\p{M}]{3,})(?:\s+(?:per)\b.*)?[.!?]*$/u'],$object,$m)){
        $word=mb_strtolower($m[1]);
        if(!in_array($word,$generic,true)&&!preg_match('/(?:mente|ando|endo)$/u',$word)){ return true; }
    }
    return false;
}

/** Pure resolver. Every candidate and every remembered reference must still be present. */
function aiNarrativeResolve(array $analysis,array $present,array $context=[]):array {
    $none=static fn(string $reason='nessun_riferimento')=>['status'=>'none','target'=>null,'confidence'=>0.0,'reason'=>$reason,'candidates'=>[]];
    $actors=[];foreach($present as $a){ if((int)($a['id']??0)>0&&($a['active']??true)&&($a['present']??true)&&aiNarrativeActorKey($a)!==($context['actor']??'')){ $actors[aiNarrativeActorKey($a)]=$a; } }
    if(!$actors){ return $none('nessun_personaggio_presente'); }
    $resolved=static fn(array $a,float $confidence,string $reason)=>['status'=>'resolved','target'=>$a,'confidence'=>$confidence,'reason'=>$reason,'candidates'=>[aiNarrativeActorKey($a)]];
    $ambiguous=static fn(array $matches,string $reason)=>['status'=>'ambiguous','target'=>null,'confidence'=>0.0,'reason'=>$reason,'candidates'=>array_keys($matches)];
    $purpose=$context['purpose']??'address';$training=($analysis['intent']??'')==='allenamento';
    $text=$purpose==='action'?($analysis['action']??''):trim(($analysis['action']??'').' '.($analysis['speech']??''));
    if($purpose==='action'&&$training){ $text=trim($text.' '.($analysis['speech']??'')); }
    if(trim($text)===''){ return $none('solo_pensiero_o_fuori_gioco'); }
    $focus=$text;
    if($purpose==='address'&&aiRegexAny(['/\b(?:dice)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:dico)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:chiede)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:chiedo)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:domanda)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:risponde)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:rispondo)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:mormora)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:sussurra)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:grida)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:urla)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:si rivolge)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu','/\b(?:parla)\s+(?:a|ad|con)\s+([^,;.!?]+)/iu'],$analysis['action']??'',$direct)){ $focus=$direct[1]; }
    if($purpose==='action') {
        $acts=array_values(array_filter($analysis['acts']??[],static fn($a)=>in_array($a['intent'],['assalto','arresto','furto','potere','rilascio'],true)));
        if(!$acts){ $acts=array_values(array_filter($analysis['acts']??[],static fn($a)=>$a['intent']==='azione')); }
        if(!$acts&&$training){ $acts=[['intent'=>'allenamento','text'=>$text]]; }
        if(!$acts){ return $none('nessun_atto_diretto'); }
        $focus=$acts[0]['text'];
        if(($acts[0]['intent']??'')==='potere'){ $focus=preg_split('/\b(?:di|per)\s+(?:attacc|uccid|feri)/iu',$focus)[0]; }
        elseif(preg_match('/\b(?:si scaglia|si scaraventa|si avventa|attacc\p{L}*|aggred\p{L}*|colpi\p{L}*|mord\p{L}*|azzann\p{L}*|afferra\p{L}*|sferra\p{L}*|spara|scocca|'.aiNarrativeBitePattern().')\b/iu',$focus,$verb,PREG_OFFSET_CAPTURE)){
            $prefix=substr($focus,0,$verb[0][1]);$focus=substr($focus,$verb[0][1]+strlen($verb[0][0]));
            // "si avvicina a Petru e tenta di afferrargli il busto": the
            // clitic retains the named approach target. A new direct object wins.
            if(preg_match('/(?:gli|le|lo|la|li)$/iu',$verb[0][0])&&aiRegexAny(['/\bsi\s+(?:avvicina)\s+(?:a)\s+(.+?)\s+(?:e|per|quindi)\b/iu','/\bsi\s+(?:avvicina)\s+(?:ad)\s+(.+?)\s+(?:e|per|quindi)\b/iu','/\bsi\s+(?:accosta)\s+(?:a)\s+(.+?)\s+(?:e|per|quindi)\b/iu','/\bsi\s+(?:accosta)\s+(?:ad)\s+(.+?)\s+(?:e|per|quindi)\b/iu'],$prefix,$antecedent)
                &&!preg_match('/(?<![\p{L}\p{N}])\p{Lu}[\p{L}\p{M}]{2,}/u',$focus)){ $focus.=' '.$antecedent[1]; }
        }
    }
    // Full names disambiguate identical first names. A mention in quoted dialogue cannot redirect an attack.
    $matches=[];foreach($actors as $key=>$a){ if(aiNarrativeMentions($focus,(string)($a['name']??''))){ $matches[$key]=$a; } }
    if(count($matches)>1){$longest=max(array_map(static fn($a)=>mb_strlen((string)$a['name']),$matches));$matches=array_filter($matches,static fn($a)=>mb_strlen((string)$a['name'])===$longest);}
    if(!$matches){ foreach($actors as $key=>$a){$first=preg_split('/\s+/u',trim((string)($a['name']??'')))[0]??'';if(mb_strlen($first)>=3&&aiNarrativeMentions($focus,$first)){ $matches[$key]=$a; }} }
    if(count($matches)===1){ return $resolved(reset($matches),.99,'nome_nel_gesto_o_dialogo'); }
    if(count($matches)>1){ return $ambiguous($matches,'più_nomi_presenti'); }
    $roles=[];
    foreach($actors as $key=>$a) {
        $aliases=array_merge((array)($a['aliases']??[]),(array)($a['titles']??[]));
        foreach(['guardia'=>'guardie','sergente'=>'sergenti','sacerdote'=>'sacerdoti','parroco'=>'parroci','suora'=>'suore'] as $singular=>$plural){ if(in_array($singular,$aliases,true)){ $aliases[]=$plural; } }
        $race=(int)($a['race_id']??$a['razza']??0);
        if(in_array($race,[2,5,7],true)){ $aliases=array_merge($aliases,['vampiro','vampira','vampiri']); }
        if(in_array($race,[3,6],true)){ $aliases=array_merge($aliases,['licantropo','licantropa','lupo','lupa']); }
        if($race===1){ $aliases=array_merge($aliases,['umano','umana']); }
        $gender=mb_strtolower((string)($a['gender']??$a['sex']??''));if($gender==='maschio'){ $aliases[]='uomo'; }elseif($gender==='femmina'){ $aliases[]='donna'; }
        foreach($aliases as $alias){ if(is_string($alias)&&mb_strlen($alias)>=3&&aiNarrativeMentions($focus,$alias)){$roles[$key]=$a;break;} }
    }
    if($purpose==='action'&&in_array($analysis['intent']??'',['assalto','furto','arresto','potere'],true)&&aiNarrativeUnresolvedObject($focus,(bool)$roles)){ return $none('oggetto_esplicito_non_presente'); }
    if(count($roles)===1){ return $resolved(reset($roles),.93,'titolo_o_descrizione_univoca'); }
    // "La guardia" can still identify the guard selected or already engaged;
    // the dropdown/history may narrow a shared title, never contradict a name.
    $sharedRole=count($roles)>1;if($sharedRole){ $actors=$roles; }
    $selected=$context['selected']??'';if(is_array($selected)){ $selected=aiNarrativeActorKey($selected); }if(is_int($selected)){ $selected='npc:'.$selected; }
    if(isset($actors[$selected])){ return $resolved($actors[$selected],.98,'selezione_presente'); }
    $pronoun=(bool)aiRegexAny(['/\b(?:lo)\b/iu','/\b(?:la)\b/iu','/\b(?:gli)\b/iu','/\b(?:le)\b/iu','/\b(?:lui)\b/iu','/\b(?:lei)\b/iu','/\b(?:tu)\b/iu','/\b(?:voi)\b/iu','/\b(?:te)\b/iu','/\b(?:vi)\b/iu','/\b(?:ti)\b/iu','/\b(?:avversario)\b/iu','/\b(?:interlocutore)\b/iu','/(?:morder|azzannar|colpir|afferrar)(?:lo|la)\b/iu'],$text);
    if($selected!==''&&$selected!=='npc:0'&&($purpose==='action'||$pronoun)){ return $none('destinatario_selezionato_assente'); }
    $references=$purpose==='action'?['last_target','last_interlocutor']:['last_interlocutor','last_target'];
    foreach($references as $reference){$key=$context[$reference]??'';if(is_array($key)){ $key=aiNarrativeActorKey($key); }if($key&&($pronoun||$purpose==='address'||$reference==='last_target')){if(!isset($actors[$key])){ return $none('riferimento_precedente_assente'); }return $resolved($actors[$key],$reference==='last_target'?.9:.84,$reference==='last_target'?'continuità_bersaglio':'continuità_interlocutore');}}
    $opponents=[];foreach($context['opponents']??[] as $key){if(is_array($key)){ $key=aiNarrativeActorKey($key); }if(isset($actors[$key])){ $opponents[$key]=$actors[$key]; }}
    if($purpose==='action'&&count($opponents)===1){ return $resolved(reset($opponents),.9,'unico_avversario_attivo'); }
    if(count($actors)===1){ return $resolved(reset($actors),$purpose==='action'?.84:.75,'unico_presente'); }
    if($purpose!=='action'&&!$sharedRole){ return $none(); }
    $role=$sharedRole?'descrizione_condivisa':'bersaglio_non_distinguibile';
    return $ambiguous($actors,$role);
}
