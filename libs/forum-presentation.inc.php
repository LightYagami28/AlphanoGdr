<?php
declare(strict_types=1);
require_once __DIR__.'/default-art.inc.php';
/** Shared board artwork and section ordering, for public lists and the editor. */
function alphanoBoardImage(array $board):string {
    if(!empty($board['header_image'])){try{return alphanoBoardValidateImage($board['header_image']);}catch(DomainException $e){/* Recover an obsolete asset through the shared fallback. */}}
    return alphanoDefaultArt('board');
}
function alphanoBoardValidateImage(string $path):string {
    $special=trim($path)===''||alphanoIsDefaultArt($path)||$path==='/assets/placeholder.svg';
    if($special){ return trim($path)==='' ? alphanoDefaultArt('board') : $path; }
    if(!preg_match('~^/uploads/forum/boards/[a-z0-9-]+\.webp$~D',$path)||!is_file(dirname(__DIR__).$path)){ throw new DomainException('Scegli un’immagine delle bacheche disponibili.',422); }
    return $path;
}
function alphanoBoardImages():array {
    $out=[];foreach(alphanoDefaultArtCatalog() as $asset){ if($asset['kind']==='cover'){ $out[]=['path'=>$asset['path'],'name'=>$asset['title']]; } }
    foreach(glob(dirname(__DIR__).'/uploads/forum/boards/*.webp')?:[] as $file){ $out[]=['path'=>'/uploads/forum/boards/'.basename($file),'name'=>ucwords(str_replace('-',' ',pathinfo($file,PATHINFO_FILENAME)))]; }
    return $out;
}
function alphanoBoardSections(array $boards):array {
    $sections=[];foreach($boards as $b){$key=$b['location'];$sections[$key]??=['location'=>$key,'boards'=>[]];$sections[$key]['boards'][]=$b;}
    ksort($sections,SORT_NATURAL|SORT_FLAG_CASE);return array_values($sections);
}
