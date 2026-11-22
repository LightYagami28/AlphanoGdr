<?php
declare(strict_types=1);
namespace Alphano\Legacy;

class GML
{
    private string $testo;

    /** tabella tag  */
    private array $tags = [
        "ce"  => ["stag"=>["<CENTER>","</CENTER>"],                            "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "tc"  => ["stag"=>['<span style="color:%s">','</span>'],               "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "lnk" => ["stag"=>['<a href="%s" target="_blank">','</a>'],            "stripspaces"=>true, "borderspaces"=>false,"depend"=>""],
        "bgc" => ["stag"=>['<div style="background-color:%s">','</div>'],      "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "bgi" => ["stag"=>["<div style=\"background-image:url('%s')\">","</div>"],"stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "cl"  => ["stag"=>['<td class="Valore" colspan="%s">','</td>'],        "stripspaces"=>true, "borderspaces"=>true, "depend"=>"rg"],
        "rg"  => ["stag"=>["<tr>","</tr>"],                                    "stripspaces"=>true, "borderspaces"=>true, "depend"=>"tbl"],
        "tbl" => ["stag"=>['<table width="%s" height="%s">','</table>'],       "stripspaces"=>true, "borderspaces"=>false,"depend"=>""],
        "b"   => ["stag"=>["<strong>","</strong>"],                            "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "c"   => ["stag"=>["<em>","</em>"],                                    "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "i"   => ["stag"=>['<img width="%s" height="%s" src="','" alt="">'],   "stripspaces"=>true, "borderspaces"=>false,"depend"=>""],
        "p"   => ["stag"=>['<span class="Descrizione">','</span>'],            "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "q"   => ["stag"=>['<fieldset><legend class="Descrizione">%s ha scritto:</legend>','</fieldset>'],"stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
        "t"   => ["stag"=>["<h1>","</h1>"],                                    "stripspaces"=>false,"borderspaces"=>false,"depend"=>""],
    ];

    public function __construct(string $rawText)
    {
        /* protezione caratteri “smart-quote” & affini */
        $this->testo = str_replace('&amp;#','&#', htmlentities($rawText, ENT_QUOTES, 'UTF-8'));
    }

    /** converte LF → <br> */
    private function nl2br(string $str): string
    {
        return str_replace("\n", '<br />', str_replace(chr(13), '', $str));
    }

    /* ───────────────────────── tag-driver generale ─────────────────────── */
    public function processText(): void
    {
        foreach ($this->tags as $key => $tag) {
            $this->processTag($key, $tag['borderspaces'], $tag['depend']);
        }
        /* escape delle parentesi quadre */
        $this->testo = str_replace(['\[','\]'], ['[',']'], $this->testo);
    }

    public function getText(): string
    {
        return $this->nl2br($this->testo);
    }

    /** Estrae solo il contenuto di un tag specifico, già con <br>. */
    public function getTag(string $tag): string
    {
        $expr = "\\[{$tag}.*?\\](.*?)\\[\\/{$tag}\\]";
        if (preg_match("/{$expr}/ism", $this->testo, $m)) {
            return $this->nl2br($m[1]);
        }
        return '';
    }

    /* ======================== PRIVATE ================================== */

    private function processTag(string $tag, bool $borderSpaces = false, string $depend = ''): void
    {
        $expr = "\\[{$tag}(\\]|\\040.*?\\])(.*?)\\[\\/{$tag}\\]";
        if ($borderSpaces)       { $expr = "[\\040\\n\\r\\t]*?{$expr}[\\040\\n\\r\\t]*?"; }
        if (strlen($depend))     { $expr = "(\\[{$depend}.*?\\].*?){$expr}(.*?\\[\\/{$depend}\\])"; }

        while (preg_match("/{$expr}/ism", $this->testo, $m)) {

            // pulizia della ] conclusiva
            if ($depend) { $m[2] = substr($m[2], 0, -1); }
            else         { $m[1] = substr($m[1], 0, -1); }

            // metodo specifico?
            $meth = "tag_{$tag}";
            if (method_exists($this, $meth)) {
                $this->$meth($m);
            } else {
                $this->general($tag, $m);
            }
        }
    }

    /** sostituzione standard */
    private function general(string $tag, array $m): void
    {
        $cfg = $this->tags[$tag];
        if ($cfg['depend']) {
            if ($cfg['stripspaces']) { $m[3] = trim($m[3]); }
            $rep = $m[1] . $cfg['stag'][0] . $m[3] . $cfg['stag'][1] . $m[4];
        } else {
            if ($cfg['stripspaces']) { $m[2] = trim($m[2]); }
            $rep = $cfg['stag'][0] . $m[2] . $cfg['stag'][1];
        }
        $this->testo = str_replace($m[0], $rep, $this->testo);
    }

    /* ---------- tag personalizzati ---------- */
    public function tagTc(array $m): void
    {
        $this->testo = str_replace(
            $m[0],
            sprintf($this->tags['tc']['stag'][0], trim($m[1])) . $m[2] . $this->tags['tc']['stag'][1],
            $this->testo
        );
    }

    public function tagI(array $m): void
    {
        // estrai in sicurezza le dimensioni "LxA"
        $parts = explode('x', trim($m[1]));
        $w     = $parts[0] ?? '';
        $h     = $parts[1] ?? '';
        $src   = trim($m[2]);

        // ricostruisci il tag <img> con valori di fallback
        $this->testo = str_replace(
            $m[0],
            sprintf(
                $this->tags['i']['stag'][0],
                $w,
                $h
            )
            . $src
            . $this->tags['i']['stag'][1],
            $this->testo
        );
    }


    public function tagLnk(array $m): void
    {
        if (!trim($m[1])) { $m[1] = $m[2]; }
        $this->testo = str_replace(
            $m[0],
            sprintf($this->tags['lnk']['stag'][0], trim($m[1])) . $m[2] . $this->tags['lnk']['stag'][1],
            $this->testo
        );
    }

    public function tagBgc(array $m): void
    {
        $this->testo = str_replace(
            $m[0],
            sprintf($this->tags['bgc']['stag'][0], trim($m[1])) . $m[2] . $this->tags['bgc']['stag'][1],
            $this->testo
        );
    }

    public function tagBgi(array $m): void
    {
        $this->testo = str_replace(
            $m[0],
            sprintf($this->tags['bgi']['stag'][0], trim($m[1])) . $m[2] . $this->tags['bgi']['stag'][1],
            $this->testo
        );
    }

    public function tagCl(array $m): void
    {
        $this->testo = str_replace(
            $m[0],
            $m[1] .
            sprintf($this->tags['cl']['stag'][0], trim($m[2])) .
            $m[3] .
            $this->tags['cl']['stag'][1] .
            $m[4],
            $this->testo
        );
    }

    public function tagTbl(array $m): void
    {
        [$w,$h] = explode('x', trim($m[1]));
        $this->testo = str_replace(
            $m[0],
            sprintf($this->tags['tbl']['stag'][0], $w, $h) . $m[2] . $this->tags['tbl']['stag'][1],
            $this->testo
        );
    }

    public function tagQ(array $m): void
    {
        $this->testo = str_replace(
            $m[0],
            sprintf($this->tags['q']['stag'][0], trim($m[1])) . $m[2] . $this->tags['q']['stag'][1],
            $this->testo
        );
    }
}
