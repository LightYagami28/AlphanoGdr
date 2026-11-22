<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/player-runtime.inc.php';
require_once __DIR__ . '/../libs/libgrafica.inc.php';






playerApi(function (): array {
alphanoRequireLogin(true);
// ────────────────────────────────────────────────────────────────────
// 3) Connessione DB e parametri GET
// ────────────────────────────────────────────────────────────────────
$db = connectDB();

[$sql,$sqlParams] = playerSearchQuery($_GET);
$rows = $db->query($sql,$sqlParams);

// ────────────────────────────────────────────────────────────────────
// 6) Raccolta dati di supporto per cariche e araldiche
// ────────────────────────────────────────────────────────────────────
[$org,$corp,$arald] = apiSearchSupport($db,$rows);

// map delle razze ed etnie
$mapRaz = array_column(ListaRazze(true), 'nome','id');
$mapEtn = array_column(ListaEtnie(),     'nome','id');

// ────────────────────────────────────────────────────────────────────
// 7) Costruzione array JSON “ricerca”
//
// usa esattamente gli stessi campi del JS originale:
// idpg, nome, imgrazza, razza, allineamento, posizione,
// imgaraldica, araldica, descaral, imgorg, org, descorg, imgcorp, corp, desccorp
// ────────────────────────────────────────────────────────────────────
$ricerca = [];
foreach ($rows as $d) { $ricerca[]=apiSearchItem($d,$mapRaz,$mapEtn,$org,$corp,$arald); }

// ────────────────────────────────────────────────────────────────────
// 8) Output JSON
// ────────────────────────────────────────────────────────────────────
return $ricerca;
});
function apiSearchSupport($db,array $rows):array {
 $orgIDs=$corpIDs=$araldIDs=[];
 foreach ($rows as $r) {
  if ($r['caricaorganizzazione']) { $orgIDs[$r['caricaorganizzazione']] = true; }
  if ($r['caricacorporazione'])   { $corpIDs[$r['caricacorporazione']]   = true; }
  $araldIDs[$r['araldica']] = true;
 }
 $org=$corp=$arald=[];
 foreach([['caricheorganizzazioni',$orgIDs,&$org],['carichecorporazioni',$corpIDs,&$corp],['araldiche',$araldIDs,&$arald]] as [$table,$ids,&$target]){
  if(!$ids){ continue; }
  $in=implode(',',array_fill(0,count($ids),'?'));
  foreach($db->query("SELECT id,nome,descrizione FROM $table WHERE id IN ($in)",array_keys($ids)) as $t){ $target[$t['id']]=$t; }
 }
 return [$org,$corp,$arald];
}
function apiSearchItem(array $d,array $mapRaz,array $mapEtn,array $org,array $corp,array $arald):array {
    $portrait=alphanoProfilePortrait($d);
    $visibleRace=alphanoVisibleRace($d);
    if($visibleRace===ALPHANO_RACE_LEGACY_SPIRIT){ $visibleRace=ALPHANO_RACE_SPIRIT; }
    $item = [
        'portrait'=>$portrait['url'],'portrait_default'=>$portrait['default'],
        'idpg'        => (int)$d['id'],
        'nome'        => $d['nome'],
        'imgrazza'    => $visibleRace . ($d['sesso'] === 'Maschio' ? 'm' : 'f'),
        'razza'       => ($mapRaz[$visibleRace] ?? '') . ' ' . ($mapEtn[$d['etnia']] ?? ''),
        'allineamento'=> processAl($d['allineamento']),
        'posizione'   => GetPosizione($d['posizione']),
        'imgaraldica' => $d['araldica'],
        'araldica'    => $arald[$d['araldica']]['nome']        ?? '',
        'descaral'    => $arald[$d['araldica']]['descrizione'] ?? '',
        'imgorg'      => '',
        'org'         => '',
        'descorg'     => '',
        'imgcorp'     => '',
        'corp'        => '',
        'desccorp'    => '',
    ];
    if ($d['caricaorganizzazione']) {
        $c = $org[$d['caricaorganizzazione']] ?? [];
        $item['imgorg']  = $d['organizzazione'].'/'.$d['caricaorganizzazione'];
        $item['org']     = $c['nome']        ?? '';
        $item['descorg'] = $c['descrizione'] ?? '';
    }
    if ($d['caricacorporazione']) {
        $c = $corp[$d['caricacorporazione']] ?? [];
        $item['imgcorp']  = $d['corporazione'].'/'.$d['caricacorporazione'];
        $item['corp']     = $c['nome']        ?? '';
        $item['desccorp'] = $c['descrizione'] ?? '';
    }
    return $item;
}
