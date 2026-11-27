<?php

/**
 * Test delle funzioni di validazione e registrazione di libs/web-push.inc.php.
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

/**
 * Database finto che registra le istruzioni e restituisce risposte preparate.
 */
final class WebPushFakeDb
{
	/** @var list<array{0:string,1:array<int,mixed>}> */
	public array $executed = [];

	/**
	 * @param array<int,mixed> $rows righe restituite da query()
	 */
	public function __construct(private array $rows = [])
	{
	}

	/**
	 * @param array<int,mixed> $params
	 * @return array<int,mixed>
	 */
	public function query(string $sql, array $params = []): array
	{
		return $this->rows;
	}

	/**
	 * @param array<int,mixed> $params
	 */
	public function execute(string $sql, array $params = []): void
	{
		$this->executed[] = [$sql, $params];
	}
}

/**
 * Verifica decodifica chiavi, filtro anti-SSRF degli endpoint e registrazione degli esiti.
 */
final class WebPushTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Carica la libreria sotto test.
	 */
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2).'/libs/web-push.inc.php';
	}

	private static function b64url(string $bytes): string
	{
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}

	/**
	 * Punto pubblico P-256 non compresso generato con OpenSSL.
	 */
	private static function publicPoint(): string
	{
		$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
		$ec = openssl_pkey_get_details($key)['ec'];
		return "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
	}

	public function testDecodificaBase64UrlValida(): void
	{
		$this->assertSame('ciao', alphanoPushDecode(self::b64url('ciao')));
	}

	/**
	 * @return array<string,array{0:mixed}>
	 */
	public static function chiaviNonValide(): array
	{
		return [
			'non stringa' => [42],
			'caratteri vietati' => ['a+b/c'],
			'troppo lunga' => [str_repeat('a', 257)],
			'vuota' => [''],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('chiaviNonValide')]
	public function testRifiutaChiaviNonValide(mixed $value): void
	{
		$this->expectException(AlphanoPushException::class);
		alphanoPushDecode($value);
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function endpointAmmessi(): array
	{
		return [
			'fcm' => ['https://fcm.googleapis.com/fcm/send/abc'],
			'mozilla' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
			'windows' => ['https://db5.notify.windows.com/w/?token=abc'],
			'apple' => ['https://web.push.apple.com/abc'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('endpointAmmessi')]
	public function testAccettaEndpointDeiBrowser(string $url): void
	{
		$this->assertSame($url, alphanoPushEndpoint($url));
	}

	/**
	 * @return array<string,array{0:mixed}>
	 */
	public static function endpointRifiutati(): array
	{
		return [
			'host arbitrario' => ['https://evil.example/fcm.googleapis.com'],
			'http' => ['http://fcm.googleapis.com/fcm/send/abc'],
			'credenziali' => ['https://u:p@fcm.googleapis.com/fcm/send/abc'],
			'porta' => ['https://fcm.googleapis.com:8443/fcm/send/abc'],
			'frammento' => ['https://fcm.googleapis.com/fcm/send/abc#x'],
			'senza percorso' => ['https://fcm.googleapis.com'],
			'spazi' => ['https://fcm.googleapis.com/a b'],
			'non stringa' => [null],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('endpointRifiutati')]
	public function testRifiutaEndpointNonAmmessi(mixed $url): void
	{
		$this->expectException(AlphanoPushException::class);
		alphanoPushEndpoint($url);
	}

	public function testSottoscrizioneValida(): void
	{
		$input = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => self::b64url(self::publicPoint()), 'auth' => self::b64url(random_bytes(16))]];
		$this->assertSame($input['keys']['auth'], alphanoPushSubscription($input)['auth']);
	}

	public function testSottoscrizioneSenzaChiavi(): void
	{
		$this->expectException(AlphanoPushException::class);
		alphanoPushSubscription(['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']);
	}

	public function testSottoscrizioneConAuthCorta(): void
	{
		$this->expectException(AlphanoPushException::class);
		alphanoPushSubscription(['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => self::b64url(self::publicPoint()), 'auth' => self::b64url('corta')]]);
	}

	public function testSottoscrizioneValidaSoloPerUtentiAttiviEChiaveCorrente(): void
	{
		$keys = ['publicKey' => 'pk'];
		$sub = ['user_id' => 1, 'vapid_hash' => hash('sha256', 'pk')];
		$this->assertTrue(alphanoPushValidSubscription(new WebPushFakeDb([['bloccato' => 0]]), $sub, $keys));
		$this->assertFalse(alphanoPushValidSubscription(new WebPushFakeDb([['bloccato' => 1]]), $sub, $keys));
		$this->assertFalse(alphanoPushValidSubscription(new WebPushFakeDb([]), $sub, $keys));
		$this->assertFalse(alphanoPushValidSubscription(new WebPushFakeDb([['bloccato' => 0]]), ['vapid_hash' => 'x'] + $sub, $keys));
	}

	public function testInvioConEndpointNonValidoRestituisceZero(): void
	{
		$this->assertSame(0, alphanoPushSend(['endpoint' => 'https://evil.example/x', 'user_id' => 1], [], static fn() => 201, null, 1));
	}

	/**
	 * @return array<string,array{0:int,1:string}>
	 */
	public static function esiti(): array
	{
		return ['inviato' => [201, 'sent'], 'scaduto' => [410, 'expired'], 'non trovato' => [404, 'expired'], 'errore' => [500, 'retry'], 'rete' => [0, 'retry']];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('esiti')]
	public function testRegistraEsitoInvio(int $status, string $counter): void
	{
		$db = new WebPushFakeDb();
		$result = ['sent' => 0, 'expired' => 0, 'retry' => 0, 'skipped' => 0];
		alphanoPushRecord($db, ['id' => 7, 'failures' => 2], $status, 9, 1000, $result);
		$this->assertSame(1, $result[$counter]);
		$this->assertCount(1, $db->executed);
		$params = $db->executed[0][1];
		$this->assertSame(7, end($params));
	}

	public function testRitentativoConAttesaEsponenzialeLimitata(): void
	{
		$db = new WebPushFakeDb();
		$result = ['sent' => 0, 'expired' => 0, 'retry' => 0, 'skipped' => 0];
		alphanoPushRecord($db, ['id' => 1, 'failures' => 20], 503, 1, 1000, $result);
		$this->assertSame([12, 1000 + 3600, 1000, 1], $db->executed[0][1]);
	}
}
