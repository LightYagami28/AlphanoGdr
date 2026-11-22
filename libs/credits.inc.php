<?php
declare(strict_types=1);
/** Visible credits are centralized; their integrity policy is described in LICENSE. */
function alphanoRenderCredit(string $placement): string
{
    static $rendered = [];
    if (isset($rendered[$placement])) {
        return $rendered[$placement];
    }
    if(!in_array($placement,['home','equipment'],true)){ throw new InvalidArgumentException('Posizione dei crediti non valida.'); }
    if(!is_file(dirname(__DIR__).'/config/credits.php')||!is_file(dirname(__DIR__).'/templates/credits/'.$placement.'.php')){ return ''; }
    static $credit = null;
    if ($credit === null) {
        $credit = require_once dirname(__DIR__).'/config/credits.php';
    }
    $h=static fn(string $s):string=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $version=trim(file_get_contents(dirname(__DIR__).'/VERSION'));
    ob_start();require_once dirname(__DIR__).'/templates/credits/'.$placement.'.php';
    $rendered[$placement] = ob_get_clean();
    return $rendered[$placement];
}
