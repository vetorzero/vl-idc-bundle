<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Token;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Token\ServiceTokenProvider;
use VetorZero\VlIdcBundle\Token\TokenException;

class ServiceTokenProviderTest extends TestCase
{
    /** @var MockResponse[] respostas do /token falso, na ordem */
    private array $responses = [];
    private ArrayAdapter $cache;
    private ServiceTokenProvider $provider;


    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $discovery = new MockHttpClient(new JsonMockResponse([
            'issuer' => 'https://central.test',
            'jwks_uri' => 'https://central.test/jwks',
            'token_endpoint' => 'https://central.test/token',
        ]));
        $this->provider = new ServiceTokenProvider(
            new MockHttpClient(fn() => array_shift($this->responses)),
            $this->cache,
            new Discovery($discovery, $this->cache, 'https://central.test'),
            'ferias-test',
            'segredo',
        );
    }


    public function testRequestsClientCredentialsTokenForAudience(): void
    {
        $this->responses[] = $response = self::tokenResponse('tok-1');

        $this->assertSame('tok-1', $this->provider->getToken('pauta'));

        $this->assertSame('POST', $response->getRequestMethod());
        $this->assertSame('https://central.test/token', $response->getRequestUrl());
        $this->assertContains('Authorization: Basic ' . base64_encode('ferias-test:segredo'), $response->getRequestOptions()['headers']);
        $this->assertSame('grant_type=client_credentials&audience=pauta', $response->getRequestOptions()['body']);
    }


    public function testReusesCachedTokenPerAudience(): void
    {
        $this->responses = [self::tokenResponse('tok-pauta'), self::tokenResponse('tok-outro')];

        $this->assertSame('tok-pauta', $this->provider->getToken('pauta'));
        $this->assertSame('tok-pauta', $this->provider->getToken('pauta'));
        $this->assertSame('tok-outro', $this->provider->getToken('outro'));
        $this->assertEmpty($this->responses);
    }


    public function testRequestsNewTokenWhenNearExpiry(): void
    {
        // vence em 30s, dentro da margem: não deve ser reaproveitado
        $this->responses = [self::tokenResponse('tok-1', expiresIn: 30), self::tokenResponse('tok-2')];

        $this->assertSame('tok-1', $this->provider->getToken('pauta'));
        $this->assertSame('tok-2', $this->provider->getToken('pauta'));
    }


    public function testRefusalCarriesCentralError(): void
    {
        $this->responses[] = new JsonMockResponse(
            ['error' => 'invalid_target', 'error_description' => 'Unknown audience'],
            ['http_code' => 400],
        );

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage("audience 'inexistente' (HTTP 400, invalid_target: Unknown audience)");
        $this->provider->getToken('inexistente');
    }


    public function testRefusalWithoutJsonBody(): void
    {
        $this->responses[] = new MockResponse('<html>Bad Gateway</html>', ['http_code' => 502]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('(HTTP 502, no error given)');
        $this->provider->getToken('pauta');
    }


    public function testUnreachableCentral(): void
    {
        $this->responses[] = new MockResponse(info: ['error' => 'connection refused']);

        try {
            $this->provider->getToken('pauta');
            $this->fail('Expected a TokenException');
        } catch (TokenException $e) {
            $this->assertInstanceOf(TransportException::class, $e->getPrevious());
        }
    }


    public function testResponseWithoutToken(): void
    {
        $this->responses[] = new JsonMockResponse(['token_type' => 'Bearer', 'expires_in' => 3600]);

        $this->expectException(TokenException::class);
        $this->expectExceptionMessage('without a usable token');
        $this->provider->getToken('pauta');
    }


    public function testFailureIsNotCached(): void
    {
        $this->responses = [
            new JsonMockResponse(['error' => 'invalid_client'], ['http_code' => 401]),
            self::tokenResponse('tok-1'),
        ];

        try {
            $this->provider->getToken('pauta');
        } catch (TokenException) {
        }

        $this->assertSame('tok-1', $this->provider->getToken('pauta'));
    }


    private static function tokenResponse(string $token, int $expiresIn = 3600): JsonMockResponse
    {
        return new JsonMockResponse(['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => $expiresIn]);
    }
}
