<?php
declare(strict_types=1);
/** Original, replaceable artwork shipped with every installation. */
function alphanoDefaultArtCatalog(): array {
    static $catalog = null;
    return $catalog??=array_merge(
        json_decode(file_get_contents(dirname(__DIR__).'/config/default-art.json'),true,32,JSON_THROW_ON_ERROR)['assets'],
        json_decode(file_get_contents(dirname(__DIR__).'/config/header-art.json'),true,32,JSON_THROW_ON_ERROR)['assets']
    );
}
function alphanoDefaultArt(string $key): string {
    foreach(alphanoDefaultArtCatalog() as $asset){ if($asset['key']===$key){ return $asset['path']; } }
    return '/assets/default-nav-scroll.svg';
}
function alphanoIsDefaultArt(string $path): bool {
    return in_array($path,array_column(alphanoDefaultArtCatalog(),'path'),true);
}

/** Pure URL lookup: public artwork never needs to expose game catalogues. */
function alphanoDefaultHeader(string $url): string {
    static $manifest = null;
    $manifest??=json_decode(file_get_contents(dirname(__DIR__).'/config/header-art.json'),true,32,JSON_THROW_ON_ERROR);
    $key=alphanoAppearancePageKey($url);
    $base=alphanoAppearancePageKey(parse_url($url,PHP_URL_PATH)?:'/');
    $art=$manifest['pages'][$key]??$manifest['pages'][$base]??null;
    if($art===null){ foreach($manifest['families'] as $prefix=>$value){ if($base===$prefix||str_starts_with($base,$prefix.'-')){$art=$value;break;} } }
    return alphanoDefaultArt('header-'.($art??'panorama'));
}
