<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Logout;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Jwt\KeySet;
use VetorZero\VlIdcBundle\Logout\LogoutTokenVerifier;

class LogoutTokenVerifierTest extends TestCase
{
    private const FERIAS = 'ferias-test';

    private static \OpenSSLAsymmetricKey $key;
    private static \OpenSSLAsymmetricKey $otherKey;
    private LogoutTokenVerifier $verifier;


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

        $this->verifier = new LogoutTokenVerifier(new KeySet($http, $cache, $discovery), $discovery, self::FERIAS);
    }


    public function testReadsSubAndSid(): void
    {
        $token = $this->verifier->verify($this->logoutToken());

        $this->assertSame('vlidc_usr_01', $token->sub);
        $this->assertSame('sess-1', $token->sid);
    }


    public function testReadsSubWithoutSid(): void
    {
        $token = $this->verifier->verify($this->logoutToken(['sid' => null]));

        $this->assertSame('vlidc_usr_01', $token->sub);
        $this->assertNull($token->sid);
    }


    public function testReadsSidWithoutSub(): void
    {
        $token = $this->verifier->verify($this->logoutToken(['sub' => null]));

        $this->assertNull($token->sub);
        $this->assertSame('sess-1', $token->sid);
    }


    public function testAcceptsMediaTypeForm(): void
    {
        $token = $this->verifier->verify($this->logoutToken(typ: 'application/logout+jwt'));

        $this->assertSame('sess-1', $token->sid);
    }


    public function testRejectsSignatureFromOtherKey(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->logoutToken(key: self::$otherKey));
    }


    public function testRejectsOtherAudience(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('audience');
        $this->verifier->verify($this->logoutToken(['aud' => 'outro']));
    }


    public function testRejectsOtherIssuer(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('issued');
        $this->verifier->verify($this->logoutToken(['iss' => 'https://outra.test']));
    }


    public function testRejectsExpiredToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->logoutToken(['iat' => time() - 600, 'exp' => time() - 480]));
    }


    public function testAcceptsSmallClockSkew(): void
    {
        $token = $this->verifier->verify($this->logoutToken(['iat' => time() - 150, 'exp' => time() - 30]));

        $this->assertSame('sess-1', $token->sid);
    }


    public function testRejectsTokenWithoutExp(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('iat or exp');
        $this->verifier->verify($this->logoutToken(['exp' => null]));
    }


    public function testRejectsTokenWithNonce(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('nonce');
        $this->verifier->verify($this->logoutToken(['nonce' => 'abc']));
    }


    public function testRejectsIdToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('not a logout token');
        $this->verifier->verify($this->logoutToken(typ: 'JWT'));
    }


    public function testRejectsTokenWithoutTheEvent(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('back-channel logout event');
        $this->verifier->verify($this->logoutToken(['events' => ['urn:outro' => new \stdClass()]]));
    }


    public function testRejectsEventThatIsNotAnObject(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify($this->logoutToken(['events' => [LogoutTokenVerifier::EVENT => true]]));
    }


    public function testRejectsTokenWithoutSubAndSid(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->expectExceptionMessage('neither sub nor sid');
        $this->verifier->verify($this->logoutToken(['sub' => null, 'sid' => null]));
    }


    public function testRejectsGarbage(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->verifier->verify('nao-e-um-jwt');
    }


    /** @param array<string, mixed> $claims */
    private function logoutToken(
        array $claims = [],
        string $typ = 'logout+jwt',
        ?\OpenSSLAsymmetricKey $key = null,
    ): string {
        $claims += [
            'iss' => 'https://central.test',
            'aud' => self::FERIAS,
            'iat' => time(),
            'exp' => time() + 120,
            'jti' => bin2hex(random_bytes(8)),
            'sub' => 'vlidc_usr_01',
            'sid' => 'sess-1',
            'events' => [LogoutTokenVerifier::EVENT => new \stdClass()],
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
