<?php

/**
 * Test del containment dei percorsi canonici.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica che un percorso sia accettato solo se risolve dentro la base.
 */
final class CanonicalInsideTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Carica le funzioni sotto test prima di ogni test.
	 */
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2).'/libs/kernel.inc.php';
	}

	/**
	 * Un file esistente dentro la base viene restituito come percorso canonico.
	 */
	public function testFileDentroLaBase(): void
	{
		$base = dirname(__DIR__, 2);
		self::assertSame(realpath($base.'/composer.json'), alphanoCanonicalInside($base, $base.'/composer.json'));
	}

	/**
	 * Un percorso che esce dalla base viene rifiutato.
	 */
	public function testTraversalVieneRifiutato(): void
	{
		$base = dirname(__DIR__, 2);
		self::assertNull(alphanoCanonicalInside($base, $base.'/../windows/win.ini'));
		self::assertNull(alphanoCanonicalInside($base.'/tests', $base.'/tests/../composer.json'));
	}

	/**
	 * Un file inesistente o una base inesistente restituiscono null.
	 */
	public function testPercorsiInesistenti(): void
	{
		$base = dirname(__DIR__, 2);
		self::assertNull(alphanoCanonicalInside($base, $base.'/file-inesistente.txt'));
		self::assertNull(alphanoCanonicalInside($base.'/cartella-inesistente', $base.'/composer.json'));
	}
}

?>
