<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Api;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use VetorZero\VlIdcBundle\Api\Exception\ApiException;
use VetorZero\VlIdcBundle\Token\ServiceTokenProvider;
use VetorZero\VlIdcBundle\Token\TokenException;

/**
 * Cliente da API `/api/v1` da central, agindo como o próprio client (client_credentials).
 */
class ApiClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ServiceTokenProvider $tokenProvider,
        private readonly string $issuer,
    ) {
    }


    /**
     * @throws ApiException
     * @throws TokenException
     */
    public function getUser(string $id): User
    {
        try {
            return User::fromArray($this->request('GET', '/users/' . rawurlencode($id)));
        } catch (\UnexpectedValueException $e) {
            throw new ApiException("VL-IdC API answered with an unexpected user: {$e->getMessage()}", previous: $e);
        }
    }


    /**
     * Com 401, tenta de novo uma vez com um token novo: o do cache pode ter sido revogado.
     *
     * @return array<string, mixed>
     * @throws ApiException
     * @throws TokenException
     */
    private function request(string $method, string $path): array
    {
        $url = rtrim($this->issuer, '/') . '/api/v1' . $path;

        try {
            $response = $this->send($method, $url);
            if ($response->getStatusCode() === 401) {
                $this->tokenProvider->forget();
                $response = $this->send($method, $url);
            }

            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw ApiException::fromProblem(
                    $method,
                    $url,
                    $status,
                    self::problem($response),
                    $response->getHeaders(false)['retry-after'][0] ?? null,
                );
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw ApiException::unreachable($method, $url, $e);
        }
    }


    /** @throws TokenException */
    private function send(string $method, string $url): ResponseInterface
    {
        return $this->httpClient->request($method, $url, [
            'auth_bearer' => $this->tokenProvider->getToken(),
            'headers' => ['Accept' => 'application/json'],
        ]);
    }


    /** @return array<string, mixed> */
    private static function problem(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (ExceptionInterface) {
            return [];
        }
    }
}
