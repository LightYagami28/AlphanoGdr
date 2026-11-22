<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use Alphano\Legacy\GML;

/* ------------------------------------------------------------------
 *  libtext.inc.php
 *  • Parsing del “GML” proprietario → HTML sicuro
 *  • Utility di formattazione testo / date / MIME
 * ----------------------------------------------------------------*/

/* ==================================================================
 *  1) CLASSE GML → HTML (libs/legacy/Gml.php)
 * =================================================================*/

/* ==================================================================
 *  2) FUNZIONI DI COMODO
 * =================================================================*/

/** ritorna il testo GML convertito in HTML */
function processText(string $testo): string
{
    if (preg_match('/<\/?[a-z][^>]*>/i', $testo)) {
        return alphanoSanitizeHtml($testo);
    }
    $gml = new GML($testo);
    $gml->processText();
    return alphanoSanitizeHtml($gml->getText());
}

/** HTML di editor e GML: conserva la formattazione, elimina codice e URL eseguibili. */
function alphanoSanitizeHtml(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $allowed = ['p','div','span','br','strong','b','em','i','u','s','ol','ul','li','a','img','table','thead','tbody','tfoot','tr','th','td','h1','h2','h3','h4','h5','h6','blockquote','pre','code','center','fieldset','legend','hr','font','sub','sup'];
    $drop = ['script','style','iframe','object','embed','form','input','button','textarea','select','link','meta','base','svg','math'];
    $safeUrl = static function (string $value, bool $image = false): bool {
        $value = preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') { return false; }
        if (preg_match('~^(?:https?://|/|\#|\?|\./|\.\./)~i', $value)) { return true; }
        if (!$image && str_starts_with(strtolower($value), 'mailto:')) { return true; }
        return !preg_match('~^[a-z][a-z0-9+.-]*:~i', $value) && !str_contains($value, '\\');
    };
    $clean = function (DOMNode $node) use (&$clean, $allowed, $drop, $safeUrl): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) { $node->removeChild($child); }
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $drop, true)) { $node->removeChild($child); continue; }
            $clean($child);
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild) { $node->insertBefore($child->firstChild, $child); }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $keep = false;
                if (in_array($name, ['title','alt'], true)) { $keep = true; }
                elseif ($name === 'class') { $keep = (bool)preg_match('/^[a-zA-Z0-9 _-]{0,150}$/D', $value); }
                elseif (in_array($name, ['width','height','colspan','rowspan','border','cellpadding','cellspacing'], true)) { $keep = (bool)preg_match('/^\d{1,4}%?$/D', $value); }
                elseif ($name === 'align') { $keep = in_array(strtolower($value), ['left','right','center','justify'], true); }
                elseif ($name === 'color' || $name === 'bgcolor') { $keep = (bool)preg_match('/^(#[a-f0-9]{3,8}|[a-z]{1,25})$/iD', $value); }
                elseif ($name === 'href' && $tag === 'a') { $keep = $safeUrl($value); }
                elseif ($name === 'src' && $tag === 'img') { $keep = $safeUrl($value, true); }
                elseif ($name === 'name' || $name === 'id') { $keep = (bool)preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,100}$/D', $value); }
                elseif ($name === 'style') {
                    $styles = [];
                    foreach (explode(';', $value) as $rule) {
                        $parts = explode(':', $rule, 2);
                        if (count($parts) !== 2) { continue; }
                        [$property, $setting] = array_map('trim', $parts);
                        $property = strtolower($property);
                        if (in_array($property, ['color','background-color'], true)
                            && preg_match('/^(#[a-f0-9]{3,8}|[a-z]{1,25}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%]+\))$/iD', $setting)) { $styles[] = "$property:$setting"; }
                        elseif ($property === 'text-align' && in_array($setting, ['left','right','center','justify'], true)) { $styles[] = "$property:$setting"; }
                        elseif (in_array($property, ['font-weight','font-style','text-decoration','white-space'], true)
                            && preg_match('/^[a-z0-9 -]{1,30}$/iD', $setting)) { $styles[] = "$property:$setting"; }
                        elseif (in_array($property, ['font-size','width','height','max-width','line-height'], true)
                            && preg_match('/^\d{1,4}(\.\d{1,2})?(px|em|rem|%|pt)?$/iD', $setting)) { $styles[] = "$property:$setting"; }
                        elseif ($property === 'background-image' && preg_match('~^url\(["\x27]?([^"\x27()]+)["\x27]?\)$~iD', $setting, $url) && $safeUrl($url[1], true)) { $styles[] = "background-image:url('" . str_replace("'", '%27', $url[1]) . "')"; }
                    }
                    if ($styles) { $child->setAttribute('style', implode(';', $styles)); $keep = true; }
                }
                if (!$keep) { $child->removeAttribute($attribute->name); }
            }
            if ($tag === 'a') { $child->setAttribute('target', '_blank'); $child->setAttribute('rel', 'noopener noreferrer'); }
        }
    };
    $body = $document->getElementsByTagName('body')->item(0);
    if (!$body) { return ''; }
    $clean($body);
    $result = '';
    foreach ($body->childNodes as $child) { $result .= $document->saveHTML($child); }
    return $result;
}

/**
 * Estrae il contenuto del tag [t]…[/t] (titolo).
 */
function getTitolo(string $testo): string
{
    $gml = new GML($testo);
    return $gml->getTag('t');
}

