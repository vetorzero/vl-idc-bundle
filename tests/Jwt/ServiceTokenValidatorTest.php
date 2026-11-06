<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Jwt;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\InvalidTokenException;
use VetorZero\VlIdcBundle\Jwt\KeySet;
use VetorZero\VlIdcBundle\Jwt\ServiceTokenValidator;

class ServiceTokenValidatorTest extends TestCase
{
    private const FERIAS = 'ferias-test';

    private static \OpenSSLAsymmetricKey $key;
    private static \OpenSSLAsymmetricKey $otherKey;

    /** chave servida pelo /jwks falso */
    private \OpenSSLAsymmetricKey $published;
    private int $jwksRequests = 0;
    private ServiceTokenValidator $validator;


    public static function setUpBeforeClass(): void
    {
        self::$key = openssl_pkey_new(['private_key_bits' => 2048]);
        self::$otherKey = openssl_pkey_new(['private_key_bits' => 2048]);
    }


    protected function setUp(): void
    {
        $this->published = self::$key;
        $http = new MockHttpClient(function (string $method, string $url) {
            if ($url === 'https://central.test/.well-known/openid-configuration') {
                return new JsonMockResponse([
                    'issuer' => 'https://central.test',
                    'jwks_uri' => 'https://central.test/jwks',
                    'token_endpoint' => 'https://central.test/token',
                ]);
            }
            $this->jwksRequests++;
            return new JsonMockResponse(['keys' => [self::jwk($this->published)]]);
        });
        $cache = new ArrayAdapter();

        $this->validator = new ServiceTokenValidator(
            new KeySet($http, $cache, new Discovery($http, $cache, 'https://central.test')),
            self::FERIAS,
        );
    }


    public function testValidTokenReturnsCaller(): void
    {
        $this->assertSame('pauta', $this->validator->validate($this->token()));
    }


    public function testAudienceAsArray(): void
    {
        $this->assertSame('pauta', $this->validator->validate($this->token(['aud' => ['outro', self::FERIAS]])));
    }


    public function testCachesKey(): void
    {
        $this->validator->validate($this->token());
        $this->validator->validate($this->token());

        $this->assertSame(1, $this->jwksRequests);
    }


    public function testRefetchesKeyOnceAfterRotation(): void
    {
        $this->validator->validate($this->token());
        $this->published = self::$otherKey;

        $this->assertSame('pauta', $this->validator->validate($this->token(key: self::$otherKey)));
        $this->assertSame(2, $this->jwksRequests);
    }


    public function testRejectsSignatureFromOtherKey(): void
    {
        $this->expectException(InvalidTokenException::class);
        try {
            $this->validator->validate($this->token(key: self::$otherKey));
        } finally {
            $this->assertSame(2, $this->jwksRequests);
        }
    }


    public function testRejectsExpiredToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->validator->validate($this->token(['exp' => time() - 120]));
    }


    public function testToleratesSmallClockSkew(): void
    {
        $this->assertSame('pauta', $this->validator->validate($this->token(['nbf' => time() + 30])));
    }


    public function testRejectsAudienceWithoutFerias(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->validator->validate($this->token(['aud' => ['outro']]));
    }


    public function testRejectsAudienceStringWithoutFerias(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->validator->validate($this->token(['aud' => 'outro']));
    }


    public function testRejectsTokenIssuedToFeriasItself(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->validator->validate($this->token(['client_id' => self::FERIAS, 'sub' => self::FERIAS]));
    }


    public function testAcceptsAnyOtherClient(): void
    {
        $this->assertSame('outro', $this->validator->validate($this->token(['client_id' => 'outro', 'sub' => 'outro'])));
    }


    public function testRejectsMalformedToken(): void
    {
        $this->expectException(InvalidTokenException::class);
        $this->validator->validate('nao-e-um-jwt');
    }


    private function token(array $claims = [], ?\OpenSSLAsymmetricKey $key = null): string
    {
        $now = time();
        $claims += [
            'aud' => self::FERIAS,
            'client_id' => 'pauta',
            'sub' => 'pauta',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            'jti' => bin2hex(random_bytes(8)),
            'scopes' => [],
        ];
        openssl_pkey_export($key ?? self::$key, $pem);

        return JWT::encode($claims, $pem, 'RS256');
    }


    /** formato da central: sem `kid`, `alg` nem `use` */
    private static function jwk(\OpenSSLAsymmetricKey $key): array
    {
        $rsa = openssl_pkey_get_details($key)['rsa'];
        $b64 = fn(string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

        return ['kty' => 'RSA', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])];
    }
}
