<?php
declare(strict_types=1);
/**
 * Compatibility surface for the engine's historical PDF calls, rendered by Dompdf.
 * Dependency: dompdf/dompdf, installed by Composer with its upstream license.
 */
require_once dirname(__DIR__,2).'/vendor/autoload.php';
if (!defined('FPDF_VERSION')) {define('FPDF_VERSION','dompdf-compat');}

/** Dedicated exception for PDF write failures. */
final class PdfWriteException extends RuntimeException {}
/** Dedicated exception for invalid PDF output destinations. */
final class PdfDestinationException extends InvalidArgumentException {}

class FPDF
{
    private const DEFAULT_FONT = 'DejaVu Sans';

    protected string $html='';
    protected string $orientation='portrait';
    protected string|array $paper='A4';
    protected int $pages=0;
    protected string $font=self::DEFAULT_FONT;
    protected string $style='';
    protected float $fontSize=11;
    protected string $color='#000000';
    protected string $unit='mm';
    protected array $metadata=[];

    public function __construct($orientation='P',$unit='mm',$format='A4') {
      $this->orientation=strtoupper((string)$orientation)==='L'?'landscape':'portrait';
      $this->unit=strtolower((string)$unit);
      $this->paper=$format;
    }

    public function addPage($orientation=''): void {
      if ($orientation !== '') {
        $this->orientation=strtoupper((string)$orientation)==='L'?'landscape':'portrait';
      }
      if ($this->pages++) {
        $this->html.='<div style="page-break-before:always"></div>';
      }
    }

    public function pageNo(): int { return $this->pages; }

    public function setTitle($value): void { $this->metadata['Title']=(string)$value; }
    public function setAuthor($value): void { $this->metadata['Author']=(string)$value; }
    public function setSubject($value): void { $this->metadata['Subject']=(string)$value; }
    public function setKeywords($value): void { $this->metadata['Keywords']=(string)$value; }
    public function setCreator($value): void { $this->metadata['Creator']=(string)$value; }

    public function setFont($family,$style='',$size=0): void {
      $this->font=(string)$family !== '' ? (string)$family : self::DEFAULT_FONT;
      $this->style=(string)$style;
      if ($size>0) {$this->fontSize=(float)$size;}
    }

    public function setFontSize($size): void { $this->fontSize=max(1,(float)$size); }

    public function setTextColor($red,$green=null,$blue=null): void {
      $this->color=sprintf('#%02x%02x%02x',(int)$red,(int)($green??$red),(int)($blue??$red));
    }

    public function cell(...$arguments): void {
      [$width,$height,$text,$border,$line,$align,$fill,$link,$currentx]=array_pad($arguments,9,'');
      $styles=['color:'.$this->color,'font-size:'.$this->fontSize.'pt'];
      if ($this->style !== '') {$styles[]='font-style:'.$this->style;}
      if ((float)$width>0) {$styles[]='min-width:'.(float)$width.$this->unit;}
      if ((float)$height>0) {$styles[]='min-height:'.(float)$height.$this->unit;}
      if ((int)$border>0) {$styles[]='border:1px solid '.$this->color;}
      if ((string)$align !== '') {$styles[]='text-align:'.$this->alignment($align);}
      if ((int)$fill>0) {$styles[]='background:'.$this->color;}
      if ((float)$currentx>0) {$styles[]='margin-left:'.(float)$currentx.$this->unit;}
      $attribute=$link !== '' && $link !== null ? ' data-link="'.htmlspecialchars((string)$link,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"' : '';
      $this->html.='<span style="'.implode(';',$styles).'"'.$attribute.'>'.htmlspecialchars((string)$text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</span>'.($line?'<br>':'');
    }

    private function alignment($align): string {
      if ($align === 'C') {return 'center';}
      if ($align === 'R') {return 'right';}
      return 'left';
    }

    public function multiCell($width,$height,$text,$border=0,$align='J',$fill=0,$link=''): void {
      $this->cell($width,$height,$text,$border,1,$align,$fill,$link);
    }

    public function write($height,$text,$currentx=0,$link=''): void {
      $this->cell(0,$height,$text,0,0,'',0,$link,$currentx);
    }

    public function ln($height=null): void {
      $count=$height === null ? 1 : max(1,(int)((float)$height/5));
      $this->html.=str_repeat('<br>',$count);
    }

    public function output($name='',$destination='') {
      if (!$destination) {
        $destination=$name === '' ? 'I' : 'F';
      }
      $destination=strtoupper((string)$destination);
      $options=new \Dompdf\Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'chroot'=>dirname(__DIR__,2),'defaultFont'=>self::DEFAULT_FONT]);
      $pdf=new \Dompdf\Dompdf($options);
      $pdf->setPaper($this->paper,$this->orientation);
      $pdf->loadHtml('<!doctype html><html><meta charset="utf-8"><style>body{font-family:'.self::DEFAULT_FONT.';font-size:11pt}</style><body>'.$this->html.'</body></html>','UTF-8');
      $pdf->render();
      foreach ($this->metadata as $key=>$value) {
        $pdf->getCanvas()->get_cpdf()->addInfo($key,$value);
      }
      $bytes=$pdf->output();
      if ($destination === 'S') {return $bytes;}
      if ($destination === 'F') {
        if (file_put_contents((string)$name,$bytes) === false) {
          throw new PdfWriteException('Salvataggio PDF non riuscito');
        }
        return '';
      }
      if (!in_array($destination,['I','D'],true)) {
        throw new PdfDestinationException('Destinazione PDF non valida');
      }
      header('Content-Type: application/pdf');
      $filename=preg_replace('/[^a-zA-Z0-9_.-]/','_',basename((string)$name)) ?: 'documento.pdf';
      header('Content-Disposition: '.($destination === 'D' ? 'attachment' : 'inline').'; filename="'.$filename.'"');
      echo $bytes;
      return '';
    }

    /** Compatibilita' con la API storica in PascalCase. */
    public function __call(string $name,array $arguments): mixed {
      if ($name === 'FPDF') {
        return $this->__construct(...$arguments);
      }
      $target=lcfirst($name);
      if (method_exists($this,$target)) {
        return $this->$target(...$arguments);
      }
      throw new BadMethodCallException('Metodo sconosciuto: '.$name);
    }
}
