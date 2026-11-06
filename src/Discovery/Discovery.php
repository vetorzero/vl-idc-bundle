<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\Discovery;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lê o discovery da central, com cache.
 */
class Discovery
{
    private const int TTL = 86_400;


    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $issuer,
    ) {
    }


    /**
     * @throws ExceptionInterface quando a central não responde ou responde com erro
     * @throws \UnexpectedValueException quando falta algum endereço na resposta
     */
    public function get(): OpenIdConfiguration
    {
        $key = 'vl_idc.discovery.' . hash('xxh128', $this->issuer);

        return $this->cache->get($key, function (ItemInterface $item): OpenIdConfiguration {
            $item->expiresAfter(self::TTL);

            return OpenIdConfiguration::fromArray(
                $this->httpClient
                    ->request('GET', rtrim($this->issuer, '/') . '/.well-known/openid-configuration')
                    ->toArray(),
            );
        });
    }
}
