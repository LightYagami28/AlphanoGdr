<?php
declare(strict_types=1);
/** Dompdf-backed adapter for the legacy HTML2FPDF call surface. */
require_once __DIR__.'/fpdf.php';
if (!defined('HTML2FPDF_VERSION')) define('HTML2FPDF_VERSION','dompdf-compat');
class HTML2FPDF extends FPDF
{
    public function __construct($orientation='P',$unit='mm',$format='A4') { parent::__construct($orientation,$unit,$format); }
    public function useCss($enabled=true): void { /* Dompdf reads CSS by default. */ }
    public function writeHtml($html): void {
        $this->html.=function_exists('alphanoSanitizeHtml') ? alphanoSanitizeHtml((string)$html) : (string)$html;
    }
}
