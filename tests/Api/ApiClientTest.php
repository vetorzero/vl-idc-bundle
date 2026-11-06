<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use VetorZero\VlIdcBundle\Api\ApiClient;
use VetorZero\VlIdcBundle\Api\Exception\AccessDeniedException;
use VetorZero\VlIdcBundle\Api\Exception\ApiException;
use VetorZero\VlIdcBundle\Api\Exception\ConflictException;
use VetorZero\VlIdcBundle\Api\Exception\NotFoundException;
use VetorZero\VlIdcBundle\Api\Exception\RateLimitedException;
use VetorZero\VlIdcBundle\Api\Exception\ValidationException;
use VetorZero\VlIdcBundle\Token\ServiceTokenProvider;

class ApiClientTest extends TestCase
{
    private const USER = [
        'id' => 'vlidc_usr_01',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'emails' => [],
        'active' => true,
    ];

    /** @var MockResponse[] respostas da API falsa, na ordem */
    private array $responses = [];
    private ApiClient $client;


    protected function setUp(): void
    {
        $tokens = $this->createStub(ServiceTokenProvider::class);
        $tokens->method('getToken')->willReturn('tok-api');

        $this->client = new ApiClient(
            new MockHttpClient(fn() => array_shift($this->responses)),
            $tokens,
            'https://central.test/',
        );
    }


    public function testGetsUserWithBearer(): void
    {
        $this->responses[] = $response = new JsonMockResponse(self::USER);

        $user = $this->client->getUser('vlidc_usr_01');

        $this->assertSame('GET', $response->getRequestMethod());
        $this->assertSame('https://central.test/api/v1/users/vlidc_usr_01', $response->getRequestUrl());
        $this->assertContains('Authorization: Bearer tok-api', $response->getRequestOptions()['headers']);
        $this->assertSame('vlidc_usr_01', $user->id);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
    }


    public function testEncodesTheId(): void
    {
        $this->responses[] = $response = new JsonMockResponse(self::USER);

        $this->client->getUser('a/b');

        $this->assertSame('https://central.test/api/v1/users/a%2Fb', $response->getRequestUrl());
    }


    public static function problems(): iterable
    {
        yield '401' => [401, [], AccessDeniedException::class];
        yield '403' => [403, [], AccessDeniedException::class];
        yield '404' => [404, [], NotFoundException::class];
        yield '409' => [409, [], ConflictException::class];
        yield '422' => [422, [], ValidationException::class];
        yield '429' => [429, [], RateLimitedException::class];
        yield '500' => [500, [], ApiException::class];
    }


    /** @param class-string<ApiException> $expected */
    #[DataProvider('problems')]
    public function testMapsProblemToException(int $status, array $problem, string $expected): void
    {
        $this->responses[] = self::problem($status, $problem);

        try {
            $this->client->getUser('vlidc_usr_01');
            $this->fail("Expected {$expected}");
        } catch (ApiException $e) {
            $this->assertSame($expected, $e::class);
            $this->assertSame($status, $e->status);
        }
    }


    public function testMessageCarriesTitleAndDetail(): void
    {
        $this->responses[] = self::problem(404, ['title' => 'Not Found', 'detail' => 'No user with this id.']);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('HTTP 404: Not Found (No user with this id.)');
        $this->client->getUser('vlidc_usr_01');
    }


    public function testConflictCarriesUserId(): void
    {
        $this->responses[] = self::problem(409, ['title' => 'Conflict', 'user_id' => 'vlidc_usr_02']);

        try {
            $this->client->getUser('vlidc_usr_01');
            $this->fail('Expected a ConflictException');
        } catch (ConflictException $e) {
            $this->assertSame('vlidc_usr_02', $e->userId);
        }
    }


    public function testValidationCarriesViolations(): void
    {
        $violations = [['propertyPath' => 'email', 'title' => 'This value is not valid.']];
        $this->responses[] = self::problem(422, ['title' => 'Validation Failed', 'violations' => $violations]);

        try {
            $this->client->getUser('vlidc_usr_01');
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame($violations, $e->violations);
        }
    }


    public function testRateLimitCarriesRetryAfter(): void
    {
        $this->responses[] = self::problem(429, ['title' => 'Too Many Requests'], ['Retry-After' => '17']);

        try {
            $this->client->getUser('vlidc_usr_01');
            $this->fail('Expected a RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(17, $e->retryAfter);
        }
    }


    public function testErrorWithoutProblemBody(): void
    {
        $this->responses[] = new MockResponse('<html>Bad Gateway</html>', ['http_code' => 502]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('with HTTP 502');
        $this->client->getUser('vlidc_usr_01');
    }


    public function testUnreachableCentral(): void
    {
        $this->responses[] = new MockResponse(info: ['error' => 'connection refused']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('did not answer GET');
        $this->client->getUser('vlidc_usr_01');
    }


    public function testUnexpectedUserShape(): void
    {
        $this->responses[] = new JsonMockResponse(['id' => 'vlidc_usr_01', 'name' => 'Ada']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("no 'email'");
        $this->client->getUser('vlidc_usr_01');
    }


    /**
     * @param array<string, mixed> $problem
     * @param array<string, string> $headers
     */
    private static function problem(int $status, array $problem, array $headers = []): MockResponse
    {
        return new JsonMockResponse($problem, [
            'http_code' => $status,
            'response_headers' => ['Content-Type' => 'application/problem+json'] + $headers,
        ]);
    }
}
