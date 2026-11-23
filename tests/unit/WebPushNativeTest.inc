<?php

/**
 * Test dell'implementazione Web Push nativa (RFC 8291).
 *
 * @category Test
 * @package  Alphano
 * @author   Light Yagami <maule2703@gmail.com>
 * @license  AGPL-3.0-only
 */

declare(strict_types=1);

final class WebPushNativeTest extends \PHPUnit\Framework\TestCase
{
	public static function setUpBeforeClass(): void
	{
		require_once dirname(__DIR__, 2).'/libs/webpush-native.inc.php';
	}

	/**
	 * Il vettore ufficiale RFC 8291 deve produrre il corpo cifrato esatto.
	 */
	public function testVettoreRfc8291(): void
	{
		$asPrivate = alphanoWebPushBase64UrlDecode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
		$uaPublic = alphanoWebPushBase64UrlDecode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
		$auth = alphanoWebPushBase64UrlDecode('BTBZMqHH6r4Tts7J_aSIgg');
		$salt = alphanoWebPushBase64UrlDecode('DGv6ra1nlYgDCS1FRnbzlw');
		$expected = alphanoWebPushBase64UrlDecode('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN');
		$result = alphanoWebPushEncrypt('When I grow up, I want to be a watermelon', $uaPublic, $auth, $salt, $asPrivate);
		self::assertSame($expected, $result['body']);
	}

	/**
	 * Il token VAPID deve essere un ES256 verificabile con la chiave pubblica dichiarata.
	 */
	public function testTokenVapid(): void
	{
		$scalar = alphanoWebPushBase64UrlDecode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
		$key = alphanoWebPushPrivateKey($scalar);
		$details = openssl_pkey_get_details($key);
		self::assertIsArray($details);
		$public = "\x04".$details['ec']['x'].$details['ec']['y'];
		$keys = [
			'subject' => 'mailto:admin@example.invalid',
			'privateKey' => alphanoWebPushBase64UrlEncode($scalar),
			'publicKey' => alphanoWebPushBase64UrlEncode($public),
		];
		$headers = alphanoWebPushVapidHeaders($keys, 'https://fcm.googleapis.com');
		self::assertContains('Content-Encoding: aes128gcm', $headers);
		$token = $this->tokenFromHeader($headers);
		$parts = explode('.', $token);
		self::assertCount(3, $parts);
		$jwtHeader = json_decode((string)alphanoWebPushBase64UrlDecode($parts[0]), true, 8, JSON_THROW_ON_ERROR);
		self::assertSame(['typ' => 'JWT', 'alg' => 'ES256'], $jwtHeader);
		$claim = json_decode((string)alphanoWebPushBase64UrlDecode($parts[1]), true, 8, JSON_THROW_ON_ERROR);
		self::assertSame('https://fcm.googleapis.com', $claim['aud']);
		$raw = alphanoWebPushBase64UrlDecode($parts[2]);
		self::assertSame(64, strlen($raw));
		$der = $this->rawToDer($raw);
		self::assertSame(1, openssl_verify($parts[0].'.'.$parts[1], $der, alphanoWebPushPublicKey($public), OPENSSL_ALGO_SHA256));
	}

	private function rawToDer(string $raw): string
	{
		$r = substr($raw, 0, 32);
		$s = substr($raw, 32);
		if (ord($r[0]) & 0x80) {
			$r = "\x00".$r;
		}
		if (ord($s[0]) & 0x80) {
			$s = "\x00".$s;
		}
		$body = "\x02".chr(strlen($r)).$r."\x02".chr(strlen($s)).$s;
		return "\x30".chr(strlen($body)).$body;
	}

	/** @param array<string,string> $headers */
	private function tokenFromHeader(array $headers): string
	{
		foreach ($headers as $header) {
			if (str_starts_with($header, 'Authorization: vapid t=')) {
				$value = substr($header, strlen('Authorization: vapid t='));
				return strstr($value, ',', true) ?: $value;
			}
		}
		return '';
	}
}
