<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Token;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VetorZero\VlIdcBundle\Discovery\Discovery;

/**
 * Pede à central tokens máquina-a-máquina (client_credentials) para chamar a API de outro sistema.
 *
 * O token fica em cache até pouco antes de vencer: a central não emite refresh token nesse grant.
 */
class ServiceTokenProvider
{
    private const CACHE_KEY_PREFIX = 'vl_idc_service_token.';
    /** segundos antes do `expires_in` em que o token deixa de ser reaproveitado */
    private const EXPIRY_MARGIN = 60;


    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly Discovery $discovery,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
    ) {
    }


    /**
     * @param ?string $audience o client id do sistema destino; null para a API da própria central
     * @throws TokenException quando a central recusa o pedido, não responde ou responde sem token
     */
    public function getToken(?string $audience = null): string
    {
        return $this->cache->get($this->cacheKey($audience), function (ItemInterface $item) use ($audience): string {
            $data = $this->requestToken($audience);

            $token = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? null;
            if (!\is_string($token) || $token === '' || !\is_numeric($expiresIn)) {
                throw TokenException::invalidResponse($audience);
            }

            $item->expiresAfter(max(0, (int)$expiresIn - self::EXPIRY_MARGIN));

            return $token;
        });
    }


    /**
     * Descarta o token em cache, para quando o destino o recusou antes do prazo.
     */
    public function forget(?string $audience = null): void
    {
        $this->cache->delete($this->cacheKey($audience));
    }


    private function cacheKey(?string $audience): string
    {
        // o client id pode ter caracteres que a chave de cache não aceita
        return self::CACHE_KEY_PREFIX . hash('xxh128', $audience ?? '');
    }


    /**
     * @return array<string, mixed>
     * @throws TokenException
     */
    private function requestToken(?string $audience): array
    {
        $body = ['grant_type' => 'client_credentials'];
        if ($audience !== null) {
            $body['audience'] = $audience;
        }

        try {
            return $this->httpClient
                ->request('POST', $this->discovery->get()->tokenEndpoint, [
                    'auth_basic' => [$this->clientId, $this->clientSecret],
                    'body' => $body,
                ])
                ->toArray();
        } catch (HttpExceptionInterface $e) {
            // a recusa vem no formato do OAuth: {"error": "...", "error_description": "..."}
            $response = $e->getResponse();
            try {
                $body = $response->toArray(false);
            } catch (ExceptionInterface) {
                $body = [];
            }

            throw TokenException::refused(
                $audience,
                $response->getStatusCode(),
                \is_string($body['error'] ?? null) ? $body['error'] : null,
                \is_string($body['error_description'] ?? null) ? $body['error_description'] : null,
                $e,
            );
        } catch (ExceptionInterface|\UnexpectedValueException $e) {
            throw TokenException::unreachable($audience, $e);
        }
    }
}
