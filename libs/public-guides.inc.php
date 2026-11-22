<?php
declare(strict_types=1);
require_once __DIR__.'/kernel.inc.php';
require_once __DIR__.'/libtext.inc.php';
require_once __DIR__.'/transformations.inc.php';
require_once __DIR__.'/alignments.inc.php';
require_once __DIR__.'/profile-symbols.inc.php';

function alphanoGuideText(string $html): string
{
    $html=processText($html);
    $html=preg_replace('~</(?:p|div|h[1-6]|li)>|<br\s*/?>~i', "\n", $html);
    return trim(preg_replace('/[\t ]+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES|ENT_HTML5, 'UTF-8')));
}

/** The public territory presentation is shared by registration and the handbook. */
function alphanoCityArtwork(): string
{
    return '/assets/placeholder.svg';
}
function alphanoCityGuides(): array
{
    $rows=connectDB()->query('SELECT c.id,c.city,c.statuto,a.nome AS entry_role FROM city c LEFT JOIN araldiche a ON a.id=(SELECT x.id FROM araldiche x WHERE x.city=c.id AND x.prima=1 ORDER BY x.livello,x.id LIMIT 1) ORDER BY c.city');
    foreach($rows as &$city){
        $city['id']=(int)$city['id'];
        $city['image']=alphanoCityArtwork();
        $city['description']=alphanoGuideText($city['statuto']);
        $city['statute_html']=processText($city['statuto']);
        unset($city['statuto']);
    }
    unset($city);
    return $rows;
}
function alphanoRegistrationGuides(): array
{
    $human=alphanoHumanRaceId();
    $race=connectDB()->query('SELECT r.id,r.nome,c.description FROM razze r LEFT JOIN rpg_races c ON c.race_id=r.id WHERE r.id=?',[$human])[0];
    $race=['id'=>(int)$race['id'],'nome'=>$race['nome'],'description'=>alphanoGuideText((string)$race['description'])?:'Una razza di esempio personalizzabile.','image'=>'/assets/avatar.svg'];
    if(!is_file(dirname(__DIR__).$race['image'])){ $race['image']='/assets/avatar.svg'; }
    $ethnicities=array_map(static fn($e)=>['id'=>(int)$e['id'],'nome'=>$e['nome'],'description'=>alphanoGuideText($e['descrizione']),'image'=>alphanoEthnicityArt((string)$e['nome'])],connectDB()->query('SELECT id,nome,descrizione FROM etnie ORDER BY nome'));
    $cities=array_values(array_filter(alphanoCityGuides(),static fn($c)=>$c['entry_role']!==null));
    foreach($cities as &$city){ unset($city['statute_html']); }unset($city);
    $alignments=alphanoAlignmentCatalog();
    return ['races'=>[$race],'ethnicities'=>$ethnicities,'cities'=>$cities,
        'alignments'=>$alignments['alignments'],'alignmentSummary'=>$alignments['summary'],'alignmentMechanics'=>$alignments['mechanics'],
        'defaultRace'=>$human,'defaultCitizenship'=>'Viandante',
        'wanderer'=>['city'=>'Viandante','image'=>'/assets/placeholder.svg','description'=>'Non appartieni ancora a una città e non ricevi una carica cittadina. Puoi viaggiare e decidere durante le giocate dove stabilirti, richiedendo poi la cittadinanza al governo del luogo.']];
}
