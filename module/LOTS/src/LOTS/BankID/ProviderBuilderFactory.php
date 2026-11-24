<?php

/**
 * Factory for the BankID provider builder.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Factory for the BankID provider builder.
 */
class ProviderBuilderFactory implements FactoryInterface
{
    /**
     * Create the builder.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Service name
     * @param array|null         $options       Options (unused)
     *
     * @return ProviderBuilder
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        return new $requestedName($container->get(\VuFindHttp\HttpService::class));
    }
}
