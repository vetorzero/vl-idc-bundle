<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Discovery;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use VetorZero\VlIdcBundle\Discovery\Discovery;

class DiscoveryTest extends TestCase
{
    private const CONFIGURATION = [
        'issuer' => 'https://central.test',
        'jwks_uri' => 'https://central.test/jwks',
        'token_endpoint' => 'https://central.test/token',
        'userinfo_endpoint' => 'https://central.test/userinfo',
    ];

    /** @var MockResponse[] respostas do discovery falso, na ordem */
    private array $responses = [];
    private Discovery $discovery;


    protected function setUp(): void
    {
        $this->discovery = new Discovery(
            new MockHttpClient(fn() => array_shift($this->responses)),
            new ArrayAdapter(),
            'https://central.test/',
        );
    }


    public function testReadsConfigurationFromWellKnown(): void
    {
        $this->responses[] = $response = new JsonMockResponse(self::CONFIGURATION);

        $configuration = $this->discovery->get();

        $this->assertSame('https://central.test/.well-known/openid-configuration', $response->getRequestUrl());
        $this->assertSame('https://central.test', $configuration->issuer);
        $this->assertSame('https://central.test/jwks', $configuration->jwksUri);
        $this->assertSame('https://central.test/token', $configuration->tokenEndpoint);
    }


    public function testCachesConfiguration(): void
    {
        $this->responses[] = new JsonMockResponse(self::CONFIGURATION);

        $this->discovery->get();
        $this->discovery->get();

        $this->assertEmpty($this->responses);
    }


    public function testRejectsConfigurationMissingAnEndpoint(): void
    {
        $this->responses[] = new JsonMockResponse(['jwks_uri' => '', 'token_endpoint' => 'https://central.test/token'] + self::CONFIGURATION);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("no 'jwks_uri'");
        $this->discovery->get();
    }


    public function testFailureIsNotCached(): void
    {
        $this->responses = [
            new MockResponse('', ['http_code' => 503]),
            new JsonMockResponse(self::CONFIGURATION),
        ];

        try {
            $this->discovery->get();
            $this->fail('Expected the 503 to surface');
        } catch (ExceptionInterface) {
        }

        $this->assertSame('https://central.test', $this->discovery->get()->issuer);
    }
}
