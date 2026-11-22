<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Presenti</title><script type="module" src="/js/presence.js?v=<?=filemtime(dirname(__DIR__,2).'/js/presence.js')?>"></script></head><body>
<main class="presence-directory" data-presence-owner="<?= (int)alphanoUser()['id'] ?>">
 <header class="ui-page-heading presence-heading" data-page-heading>
  <span data-icon="users" aria-hidden="true"></span><div><div class="ui-eyebrow">Alphano · Presenti</div><h1>Presenti</h1><p>Giocatori e personaggi non giocanti, riuniti per luogo.</p></div>
  <form class="presence-filters" role="search" aria-label="Filtri dei presenti" onsubmit="return false">
   <label>Cerca personaggio o luogo<input id="presence-search" type="search" placeholder="Nome, luogo o razza…" autocomplete="off"></label>
   <label>Mostra<select id="presence-kind"><option value="all">Tutti i personaggi</option><option value="player">Solo giocatori</option><option value="npc">Solo PNG</option></select></label>
   <div class="presence-filter-actions"><label class="presence-npc-toggle"><span>Includi PNG</span><input id="presence-show-npcs" type="checkbox" aria-label="Mostra i PNG" aria-describedby="presence-npc-help" checked><span class="presence-switch-track" aria-hidden="true"></span></label><button id="presence-refresh" type="button" data-icon="refresh">Aggiorna</button></div>
   <span id="presence-refresh-status" aria-live="polite"></span>
  </form>
 </header>
 <div class="presence-summary"><div><strong><?=$presence['players']?></strong> <?=$presence['players']===1?'giocatore':'giocatori'?></div><div><strong><?=$presence['npcs']?></strong> PNG nei luoghi</div><p id="presence-npc-help">I PNG sono personaggi della scena; la loro presenza non indica giocatori connessi.</p></div>
 <p id="presence-results" class="presence-results" aria-live="polite"></p>
 <div class="presence-places">
 <?php foreach ($presence['places'] as $place): ?><section class="presence-place" data-place="<?=presenceH($place['title'])?>" data-session-active="<?=$place['session_active']?'1':'0'?>">
  <header><div><div class="ui-eyebrow">Luogo di gioco</div><h2><?=alphanoPresenceHeadingHtml($place)?></h2></div><span class="presence-place-count"><?=$place['players']?> PG · <?=$place['npcs']?> PNG</span></header>
  <div class="ui-table-scroll"><table class="ui-data-table presence-table"><thead><tr><th scope="col">Personaggio</th><th scope="col">Razza e sesso</th><th scope="col">Tipo</th><th scope="col">Azioni</th></tr></thead><tbody>
  <?php foreach($place['members'] as $member): ?><tr class="presence-person" data-kind="<?=$member['kind']?>" data-search="<?=presenceH($member['name'].' '.$member['race'].' '.$place['title'])?>">
   <td><a href="<?=presenceH($member['profile_url'])?>"><?=presenceH($member['name'])?></a></td>
   <td><span class="ui-person"><img class="race-emblem" src="<?=presenceH($member['icon'])?>" width="28" height="28" alt="<?=presenceH($member['race'].' · '.$member['sex'])?>" title="<?=presenceH($member['race'].' · '.$member['sex'])?>"></span></td>
   <td><?=$member['kind']==='npc'?'PNG':'Giocatore'?></td>
   <td><div class="ui-row-actions"><a class="action-btn" href="<?=presenceH($member['profile_url'])?>" data-icon="user">Profilo</a><?=alphanoPresenceGameHtml($member)?><?php if($member['kind']==='player'): ?><a class="action-btn" href="/posta/writeto.php?pgName=<?=rawurlencode($member['name'])?>&amp;idpg=<?=$member['id']?>" data-icon="mail">Scrivi</a><?php endif; ?></div></td>
  </tr><?php endforeach; ?></tbody></table></div>
  <?php if (!$place['members']): ?><p class="presence-empty">Nessun personaggio presente.</p><?php endif; ?>
 </section><?php endforeach; ?>
 </div><p id="presence-no-results" class="presence-empty" hidden>Nessun personaggio corrisponde alla ricerca.</p>
</main></body></html>
