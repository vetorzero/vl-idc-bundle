<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use VetorZero\VlIdcBundle\Api\ApiClient;
use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\IdTokenVerifier;
use VetorZero\VlIdcBundle\Jwt\KeySet;
use VetorZero\VlIdcBundle\Jwt\ServiceTokenValidator;
use VetorZero\VlIdcBundle\Logout\BackchannelLogoutController;
use VetorZero\VlIdcBundle\Logout\LogoutTokenVerifier;
use VetorZero\VlIdcBundle\Token\ServiceTokenProvider;
use VetorZero\VlIdcBundle\Webhook\SecurityEventVerifier;
use VetorZero\VlIdcBundle\Webhook\WebhookController;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->bind('string $issuer', param('vl_idc.issuer'))
            ->bind('string $clientId', param('vl_idc.client_id'))
            ->bind('string $clientSecret', param('vl_idc.client_secret'));

    $services->set(Discovery::class);
    $services->set(KeySet::class);
    $services->set(IdTokenVerifier::class);
    $services->set(ServiceTokenValidator::class);
    $services->set(ServiceTokenProvider::class);
    $services->set(ApiClient::class);
    $services->set(SecurityEventVerifier::class);
    $services->set(LogoutTokenVerifier::class);
    $services->set(WebhookController::class)
        ->public()
        ->tag('controller.service_arguments');
    $services->set(BackchannelLogoutController::class)
        ->public()
        ->tag('controller.service_arguments');
};
