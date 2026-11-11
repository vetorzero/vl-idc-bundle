<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use VetorZero\VlIdcBundle\Logout\BackchannelLogoutController;
use VetorZero\VlIdcBundle\Webhook\WebhookController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('vl_idc_webhook', '/')
        ->controller(WebhookController::class)
        ->methods(['POST']);

    // a URL a cadastrar como `backchannel_logout_uri` no client da central
    $routes->add('vl_idc_backchannel_logout', '/backchannel-logout')
        ->controller(BackchannelLogoutController::class)
        ->methods(['POST']);
};
