<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Webhook;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Jwt\KeySet;
use VetorZero\VlIdcBundle\Webhook\SecurityEvent;
use VetorZero\VlIdcBundle\Webhook\SecurityEventVerifier;

class SecurityEventVerifierTest extends TestCase
{
    private const FERIAS = 'ferias-test';
    private const USER_UPDATED = 'urn:vl-idc:event:user.updated';

    private static \OpenSSLAsymmetricKey $key;
    private static \OpenSSLAsymmetricKey $otherKey;
    private SecurityEventVerifier $verifier;


    public static function setUpBeforeClass(): void
    {
        self::$key = openssl_pkey_new(['private_key_bits' => 2048]);
        self::$otherKey = openssl_pkey_new(['private_key_bits' => 2048]);
    }


    protected function setUp(): void
    {
        $http = new MockHttpClient(fn(string $method, string $url) => match ($url) {
            'https://central.test/.well-known/openid-configuration' => new JsonMockResponse([
                'issuer' => 'https://central.test',
                'jwks_uri' => 'https://central.test/jwks',
                'token_endpoint' => 'https://central.test/token',
            ]),
            'https://central.test/jwks' => new JsonMockResponse(['keys' => [self::jwk(self::$key)]]),
        });
        $cache = new ArrayAdapter();
        $discovery = new Discovery($http, $cache, 'https://central.test');

        $this->verifier = new SecurityEventVerifier(new KeySet($http, $cache, $discovery), $discovery, self::FERIAS);
    }


    public function testReadsUserEvent(): void
    {
        $event = $this->verifier->verify($this->set());

        $this->assertSame('evt-1', $event->id);
        $this->assertSame(self::USER_UPDATED, $event->type);
        $this->assertSame('user', $event->subject?->type);
        $this->assertSame('vlidc_usr_01', $event->subject?->id);
        $this->assertEquals(new \DateTimeImmutable('@1700000000'), $event->occurredAt);
        $this->assertFalse($event->own);
        $this->assertFalse($event->isVerification());
    }


    public function testReadsOwnClaim(): void
    {
        $this->assertTrue($this->verifier->verify($this->set(['urn:vl-idc:claim:own' => true]))->own);
    }


    public function testReadsVerificationEvent(): void
    {
        $event = $this->verifier->verify($this->set([
            'events' => [SecurityEvent::VERIFICATION => new \stdClass()],
            'toe' => null,
        ]));

        $this->assertTrue($event->isVerification());
        $this->assertNull($event->subject);
        $this->assertNull($event->occurredAt);
    }


    public function testAcceptsOldEventsBecauseSetsHaveNoExpiry(): void
    {
        $event = $this->verifier->verify($this->set(['iat' => time() - 5 * 86_400]));

        $this->assertSame('evt-1', $event->id);
    }


    public function testRejectsTokenThatIsNotASet(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('not a security event token');
        $this->verifier->verify($this->set(typ: 'JWT'));
    }


    public function testRejectsOtherIssuer(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->set(['iss' => 'https://outra.test']));
    }


    public function testRejectsOtherAudience(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->set(['aud' => 'outro']));
    }


    public function testRejectsSignatureFromOtherKey(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->set(key: self::$otherKey));
    }


    public function testRejectsTokenWithoutJti(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->set(['jti' => null]));
    }


    public function testRejectsTokenWithoutEvents(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->set(['events' => new \stdClass()]));
    }


    public function testRejectsTokenWithSeveralEvents(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->set(['events' => [
            self::USER_UPDATED => ['subject' => ['type' => 'user', 'id' => 'vlidc_usr_01']],
            'urn:vl-idc:event:user.email_verified' => ['subject' => ['type' => 'user', 'id' => 'vlidc_usr_01']],
        ]]));
    }


    public function testRejectsGarbage(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify('nao-e-um-jwt');
    }


    /** @param array<string, mixed> $claims */
    private function set(array $claims = [], string $typ = 'secevent+jwt', ?\OpenSSLAsymmetricKey $key = null): string
    {
        $claims += [
            'iss' => 'https://central.test',
            'aud' => self::FERIAS,
            'iat' => time(),
            'jti' => 'evt-1',
            'toe' => 1_700_000_000,
            'events' => [self::USER_UPDATED => ['subject' => ['type' => 'user', 'id' => 'vlidc_usr_01']]],
        ];
        openssl_pkey_export($key ?? self::$key, $pem);

        return JWT::encode(array_filter($claims, fn($v) => $v !== null), $pem, 'RS256', head: ['typ' => $typ]);
    }


    /** formato da central: sem `kid`, `alg` nem `use` */
    private static function jwk(\OpenSSLAsymmetricKey $key): array
    {
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn(string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return ['kty' => 'RSA', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])];
    }
}
