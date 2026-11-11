<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Jwt;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\IdTokenVerifier;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Jwt\KeySet;

class IdTokenVerifierTest extends TestCase
{
    private const FERIAS = 'ferias-test';

    private static \OpenSSLAsymmetricKey $key;
    private static \OpenSSLAsymmetricKey $otherKey;
    private IdTokenVerifier $verifier;


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

        $this->verifier = new IdTokenVerifier(new KeySet($http, $cache, $discovery), $discovery, self::FERIAS);
    }


    public function testReadsSubAndSid(): void
    {
        $token = $this->verifier->verify($this->idToken());

        $this->assertSame('vlidc_usr_01', $token->sub);
        $this->assertSame('sess-1', $token->sid);
    }


    public function testSidIsOptional(): void
    {
        $this->assertNull($this->verifier->verify($this->idToken(['sid' => null]))->sid);
    }


    public function testAcceptsAudienceList(): void
    {
        $token = $this->verifier->verify($this->idToken(['aud' => ['outro', self::FERIAS]]));

        $this->assertSame('vlidc_usr_01', $token->sub);
    }


    public function testRejectsOtherIssuer(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->idToken(['iss' => 'https://outra.test']));
    }


    public function testRejectsOtherAudience(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->idToken(['aud' => 'outro']));
    }


    public function testRejectsSignatureFromOtherKey(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->idToken(key: self::$otherKey));
    }


    public function testRejectsExpiredToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->idToken(['iat' => time() - 3600, 'exp' => time() - 600]));
    }


    public function testRejectsTokenWithoutSub(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('no sub');
        $this->verifier->verify($this->idToken(['sub' => null]));
    }


    /** @param array<string, mixed> $claims */
    private function idToken(array $claims = [], ?\OpenSSLAsymmetricKey $key = null): string
    {
        $claims += [
            'iss' => 'https://central.test',
            'aud' => self::FERIAS,
            'iat' => time(),
            'exp' => time() + 300,
            'sub' => 'vlidc_usr_01',
            'sid' => 'sess-1',
        ];
        openssl_pkey_export($key ?? self::$key, $pem);

        return JWT::encode(array_filter($claims, fn($v) => $v !== null), $pem, 'RS256');
    }


    /** formato da central: sem `kid`, `alg` nem `use` */
    private static function jwk(\OpenSSLAsymmetricKey $key): array
    {
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn(string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return ['kty' => 'RSA', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])];
    }
}
