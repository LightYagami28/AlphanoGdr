<?php

/**
 * Test delle funzioni pure di libs/kernel.inc.php.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Verifica normalizzazione degli ID e generazione dei segnaposto SQL.
 */
final class KernelFunctionsTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Carica le funzioni sotto test prima di ogni test.
	 */
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2).'/libs/kernel.inc.php';
	}

	/**
	 * Solo interi positivi e stringhe di cifre senza zeri iniziali sono validi.
	 */
	public function testFiltraIdentificativi(): void
	{
		$valori = [1, '2', '012', 0, -3, '0', 'x12', 12.5, null, true, false, '', '100', 100, 2];
		self::assertSame([1, 2, 100], alphano_sql_ids($valori));
	}

	/**
	 * Un input non array restituisce sempre una lista vuota.
	 */
	public function testValoriNonArray(): void
	{
		self::assertSame([], alphano_sql_ids('1,2,3'));
		self::assertSame([], alphano_sql_ids(null));
		self::assertSame([], alphano_sql_ids(7));
	}

	/**
	 * I segnaposto seguono il conteggio richiesto.
	 */
	public function testSegnapostoSql(): void
	{
		self::assertSame('?,?,?', alphano_sql_placeholders(3));
		self::assertSame('?', alphano_sql_placeholders(1));
		self::assertSame('', alphano_sql_placeholders(0));
		self::assertSame('', alphano_sql_placeholders(-2));
	}
}

?>
