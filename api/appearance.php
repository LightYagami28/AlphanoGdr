<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/libs/kernel.inc.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$page=$_GET['page']??'/';
if(!is_string($page)||strlen($page)>1500||!str_starts_with($page,'/')||str_starts_with($page,'//')){ alphanoFail('Pagina non valida.',400,true); }
echo json_encode(['theme'=>alphanoConfig('theme'),'sidebar'=>alphanoConfig('sidebar_position'),'header'=>alphanoConfig('header_position'),'page'=>alphanoAppearancePage($page),'stylesheet'=>'/assets/theme.php?v='.alphanoThemeVersion()],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
