<?php
declare(strict_types=1);
const EMAIL_THEME_INC_MAIN_PHP = '/main.php';
/** One palette, layout and asset catalogue for every outgoing email. No tracking pixels. */
function alphanoEmailCategories():array {
 return ['mail'=>['Missive personali','Nuovi messaggi ricevuti dagli altri giocatori. Il testo della conversazione resta nel gioco.','mail'], 'trade'=>['Commercio e inventario',alphanoCurrencyText('Proposte, ordini, consegne e trasferimenti di Corone, oggetti e materiali.'),'trade'], 'alerts'=>['Avvisi personali','Comunicazioni del sistema e aggiornamenti indirizzati al tuo personaggio.','alerts'], 'quests'=>['Quest e Master','Aperture di quest e nuovi esiti o interventi del Master che ti riguardano.','alerts'], 'groups'=>['Organizzazioni e Accademia','Richieste, proposte e decisioni accessibili in base alle tue cariche attuali.','alerts'], 'boards'=>['Bacheche e discussioni','Nuovi interventi nelle bacheche e discussioni che puoi consultare.','mail'], 'games'=>['Scacchi e Dama','Sfide, cambi di condizioni e risultati. Gli inviti scadono comunque dopo dieci minuti.','alerts'], 'civic'=>['Cittadinanza ed elezioni','Richieste civiche, candidature, votazioni e risultati della tua città.','alerts']];
}
function alphanoEmailUrl(string $path=EMAIL_THEME_INC_MAIN_PHP):string {
 $base=rtrim((string)alphanoConfig('base_url'),'/');
 if(!preg_match('#^https?://[^/]+#',$base)){ $base='https://example.invalid'; }
 if(!str_starts_with($path,'/')||str_starts_with($path,'//')||str_contains($path,"\r")||str_contains($path,"\n")){ $path=EMAIL_THEME_INC_MAIN_PHP; }
 return $base.$path;
}
function alphanoEmailRender(string $subject,string $body,string $category='alerts',string $path=EMAIL_THEME_INC_MAIN_PHP,bool $optional=false):string {
 $art=in_array($category,['mail','trade','alerts','welcome','security'],true)?$category:(alphanoEmailCategories()[$category][2]??'alerts');
 $h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 ob_start();require_once dirname(__DIR__).'/templates/email/notification.php';return ob_get_clean();
}
