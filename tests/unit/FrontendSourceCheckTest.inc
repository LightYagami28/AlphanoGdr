<?php

/**
 * Test di regressione per dichiarazioni JS esplicite e valori CSS validi.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica i sorgenti frontend corretti e i rilevatori delle vecchie forme.
 */
final class FrontendSourceCheckTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Carica le funzioni sotto test prima di ogni test.
     */
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/libs/frontend-source-check.inc.php';
    }

    /**
     * I file JS corretti non contengono assegnazioni implicite.
     */
    public function testSorgentiJsCorretti(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (alphanoFrontendJsFiles() as $file) {
            self::assertTrue(
                alphanoJsExplicitDeclarations((string)file_get_contents($root.'/'.$file)),
                $file.' contiene dichiarazioni implicite'
            );
        }
    }

    /**
     * Il rilevatore riconosce le vecchie forme prive di parola chiave.
     */
    public function testRilevamentoGlobaliImpliciti(): void
    {
        self::assertSame(['riga'], alphanoJsImplicitAssignments('riga = righe.pop();'));
        self::assertSame(['tmp'], alphanoJsImplicitAssignments('tmp = obj.scrollTop;'));
        self::assertSame(['sel'], alphanoJsImplicitAssignments('sel = document.selection.createRange();'));
        self::assertSame(['ci'], alphanoJsImplicitAssignments('for (ci in punctuations) {}'));
        self::assertSame(['i'], alphanoJsImplicitAssignments('for (i in membership) {}'));

        self::assertSame([], alphanoJsImplicitAssignments('var riga = righe.pop();'));
        self::assertSame([], alphanoJsImplicitAssignments('var tmp = obj.scrollTop;'));
        self::assertSame([], alphanoJsImplicitAssignments('var sel = document.selection.createRange();'));
        self::assertSame([], alphanoJsImplicitAssignments('for (var ci in punctuations) {}'));
        self::assertSame([], alphanoJsImplicitAssignments('for (var i in membership) {}'));
        self::assertTrue(alphanoJsExplicitDeclarations('var cn = 0;'));
        self::assertFalse(alphanoJsExplicitDeclarations('cn = 0;'));
    }

    /**
     * Il CSS della mappa usa il raggio con unita' di misura.
     */
    public function testUnitaCss(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__, 2).'/css/theme/maps.css');
        self::assertSame('6px', alphanoCssRadiusValue($css));
        self::assertTrue(alphanoCssRadiusHasUnit($css));
        self::assertSame('6', alphanoCssRadiusValue('body .map-dot { r: 6; }'));
        self::assertFalse(alphanoCssRadiusHasUnit('body .map-dot { r: 6; }'));
        self::assertNull(alphanoCssRadiusValue('body .map-dot { fill: red; }'));
    }

    /**
     * L'elenco dei file frontend è esplicito e completo.
     */
    public function testElencoFile(): void
    {
        self::assertSame(['js/chats.js', 'js/gml.js', 'js/password.js'], alphanoFrontendJsFiles());
    }

    /**
     * Il rapporto dei sorgenti non segnala globali impliciti ne' raggi senza unita'.
     */
    public function testReportSorgenti(): void
    {
        $report = alphanoFrontendSourceReport(dirname(__DIR__, 2));
        foreach (alphanoFrontendJsFiles() as $file) {
            self::assertSame([], $report[$file]);
        }
        self::assertSame('6px', $report['css/theme/maps.css']);
    }
}
