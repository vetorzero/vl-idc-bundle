<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle\OAuth;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Provider do league/oauth2-client para o login na central.
 */
class VlIdcProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    public string $baseUrl {
        set => rtrim($value, '/');
    }


    public function getBaseAuthorizationUrl(): string
    {
        return "{$this->baseUrl}/authorize";
    }


    public function getBaseAccessTokenUrl(array $params): string
    {
        return "{$this->baseUrl}/token";
    }


    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return "{$this->baseUrl}/userinfo";
    }


    protected function getDefaultScopes(): array
    {
        return ['openid', 'email', 'profile'];
    }


    protected function getScopeSeparator(): string
    {
        return ' ';
    }


    protected function checkResponse(
        ResponseInterface $response,
        $data,
    ): void {
        if ($response->getStatusCode() >= 400) {
            throw new IdentityProviderException(
                $data['error_description']
                ?? $data['error']
                ?? 'OAuth provider error',
                $response->getStatusCode(),
                $data,
            );
        }
    }


    protected function createResourceOwner(
        array $response,
        AccessToken $token,
    ): GenericResourceOwner {
        return new GenericResourceOwner($response, 'sub');
    }

}
