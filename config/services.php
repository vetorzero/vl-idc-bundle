<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use VetorZero\VlIdcBundle\Discovery\Discovery;
use VetorZero\VlIdcBundle\Jwt\KeySet;
use VetorZero\VlIdcBundle\Jwt\ServiceTokenValidator;
use VetorZero\VlIdcBundle\Token\ServiceTokenProvider;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->bind('string $issuer', param('vl_idc.issuer'))
            ->bind('string $clientId', param('vl_idc.client_id'))
            ->bind('string $clientSecret', param('vl_idc.client_secret'));

    $services->set(Discovery::class);
    $services->set(KeySet::class);
    $services->set(ServiceTokenValidator::class);
    $services->set(ServiceTokenProvider::class);
};
