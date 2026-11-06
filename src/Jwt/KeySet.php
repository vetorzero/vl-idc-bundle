<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Jwt;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VetorZero\VlIdcBundle\Discovery\Discovery;

/**
 * Decodifica JWTs assinados pela central, com as chaves do JWKS dela em cache.
 *
 * Se a assinatura não bate, busca o JWKS de novo uma vez: a central pode ter rotacionado a chave.
 */
class KeySet
{
    private const string CACHE_KEY = 'vl_idc.jwks';
    private const int LEEWAY = 60;


    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly Discovery $discovery,
    ) {
    }


    /**
     * Confere assinatura, `exp`, `nbf` e `iat`. As outras claims ficam com quem chama.
     *
     * @throws InvalidTokenException
     * @throws ExceptionInterface quando a central não pôde ser consultada
     */
    public function decode(string $jwt, ?\stdClass &$headers = null): \stdClass
    {
        try {
            return $this->decodeWith($jwt, $this->key(), $headers);
        } catch (InvalidTokenException $e) {
            if (!$e->getPrevious() instanceof SignatureInvalidException) {
                throw $e;
            }
        }

        $this->cache->delete(self::CACHE_KEY);

        return $this->decodeWith($jwt, $this->key(), $headers);
    }


    private function decodeWith(string $jwt, Key $key, ?\stdClass &$headers): \stdClass
    {
        JWT::$leeway = self::LEEWAY;

        try {
            return JWT::decode($jwt, $key, $headers);
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $e) {
            throw new InvalidTokenException($e->getMessage(), previous: $e);
        }
    }


    /**
     * A central não manda `kid`: usa a única chave do JWKS, com RS256 fixo.
     */
    private function key(): Key
    {
        // guarda o JWK cru: a `Key` carrega um recurso OpenSSL e não serializa
        $jwk = $this->cache->get(self::CACHE_KEY, fn(): array => $this->httpClient
            ->request('GET', $this->discovery->get()->jwksUri)
            ->toArray()['keys'][0]);

        return JWK::parseKey($jwk, 'RS256');
    }
}
