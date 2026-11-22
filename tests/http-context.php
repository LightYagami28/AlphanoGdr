<?php
declare(strict_types=1);
require dirname(__DIR__).'/libs/http-context.inc.php';
$cases=[
 ['canonical HTTPS behind proxy',alphanoHttpsContext('https://game.example',[])],
 ['direct HTTPS',alphanoHttpsContext('',['HTTPS'=>'on'])],
 ['AlterVista TLS proxy',alphanoHttpsContext('',['HTTP_HOST'=>'example.altervista.org','HTTP_X_FORWARDED_PROTO'=>'https'])],
 ['untrusted generic proxy ignored',!alphanoHttpsContext('',['HTTP_HOST'=>'game.example','HTTP_X_FORWARDED_PROTO'=>'https'])],
 ['plain local HTTP',!alphanoHttpsContext('http://localhost',[])],
 ['default port normalized',alphanoCanonicalOrigin('https://GAME.example:443/')==='https://game.example'],
 ['non-default port preserved',alphanoCanonicalOrigin('http://localhost:8000/')==='http://localhost:8000'],
];
foreach($cases as [$label,$ok]){if(!$ok){fwrite(STDERR,'FAIL '.$label.PHP_EOL);exit(1);}echo 'PASS '.$label.PHP_EOL;}
