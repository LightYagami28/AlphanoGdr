<?php $h=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Strumenti – <?=$h(TITOLO_SITO)?></title></head><body>
<section class="ui-list-section" aria-label="Strumenti personali"><section class="ui-table-scroll" aria-label="Strumenti disponibili"><table class="ui-data-table"><thead><tr><th scope="col">Strumento</th><th scope="col">A cosa serve</th><th scope="col">Azioni</th></tr></thead><tbody>
<?php foreach($tools as $tool): ?><tr><td><strong><?=$h($tool['title'])?></strong></td><td><?=$h($tool['description'])?></td><td><a class="action-btn" data-icon="<?=$h($tool['icon'])?>" href="<?=$h($tool['url'])?>">Apri</a></td></tr><?php endforeach; ?>
</tbody></table></section></section></body></html>
