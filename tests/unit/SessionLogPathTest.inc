<?php

/**
 * Test della risoluzione sicura dei percorsi dei log di sessione.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica realpath e contenimento nella directory attesa.
 */
final class SessionLogPathTest extends \PHPUnit\Framework\TestCase
{
    private string $root;

    /**
     * Prepara una radice temporanea con la directory dei log.
     */
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2).'/libs/session-log.inc.php';
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'alphano-log-'.bin2hex(random_bytes(6));
        mkdir($this->root.DIRECTORY_SEPARATOR.'sessione'.DIRECTORY_SEPARATOR.'log', 0777, true);
    }

    /**
     * Rimuove la radice temporanea.
     */
    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($this->root);
    }

    /**
     * Il file .html valido viene restituito nella forma canonica.
     */
    public function testLogHtmlValido(): void
    {
        $file = $this->root.'/sessione/log/123.html';
        file_put_contents($file, '<div>cronaca</div>');
        self::assertSame(realpath($file), alphano_session_log_path($this->root, '123'));
    }

    /**
     * Se manca l'html si usa il vecchio formato .htm.
     */
    public function testFallbackHtm(): void
    {
        $file = $this->root.'/sessione/log/456.htm';
        file_put_contents($file, '<div>cronaca</div>');
        self::assertSame(realpath($file), alphano_session_log_path($this->root, '456'));
    }

    /**
     * Un file vuoto non viene accettato.
     */
    public function testFileVuoto(): void
    {
        file_put_contents($this->root.'/sessione/log/789.html', '');
        self::assertNull(alphano_session_log_path($this->root, '789'));
    }

    /**
     * Identificativi non numerici o con separatori vengono rifiutati.
     */
    public function testIdNonValido(): void
    {
        foreach (['', '../123', '12a', '1/2', '..', '1 2'] as $id) {
            self::assertNull(alphano_session_log_path($this->root, $id));
        }
    }

    /**
     * Anche un file esistente fuori dalla directory attesa viene rifiutato.
     */
    public function testContenimento(): void
    {
        file_put_contents($this->root.'/sessione/999.html', '<div>fuori</div>');
        self::assertNull(alphano_session_log_path($this->root, '../999'));

        mkdir($this->root.'/sessione/log/sub');
        file_put_contents($this->root.'/sessione/log/sub/111.html', '<div>sotto</div>');
        self::assertNull(alphano_session_log_path($this->root, 'sub/111'));
    }

    /**
     * Una radice inesistente non genera eccezioni.
     */
    public function testDirectoryMancante(): void
    {
        self::assertNull(alphano_session_log_path($this->root.'/assente', '1'));
    }
}
