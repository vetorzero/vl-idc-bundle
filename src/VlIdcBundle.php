<?php

declare(strict_types=1);

namespace VetorZero\VlIdcBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class VlIdcBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('issuer')
                    ->info('URL da central; o discovery fica em {issuer}/.well-known/openid-configuration')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('client_id')->isRequired()->end()
                ->scalarNode('client_secret')->isRequired()->end()
            ->end();
    }


    /** @param array{issuer: string, client_id: string, client_secret: string} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('vl_idc.issuer', $config['issuer'])
            ->set('vl_idc.client_id', $config['client_id'])
            ->set('vl_idc.client_secret', $config['client_secret']);
    }
}
