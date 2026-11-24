<?php

/**
 * Factory for the BankID session store.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Psr\Container\ContainerInterface;

/**
 * Factory for the BankID session store.
 */
class SessionStoreFactory implements FactoryInterface
{
    /**
     * Create the store.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Service name
     * @param array|null         $options       Options (unused)
     *
     * @return SessionStore
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        return new $requestedName(
            new Container('LOTS_BankID', $container->get(SessionManager::class))
        );
    }
}
