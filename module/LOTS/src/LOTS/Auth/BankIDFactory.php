<?php

/**
 * Factory for BankID authentication.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  Authentication
 */

namespace LOTS\Auth;

use Psr\Container\ContainerInterface;

/**
 * Factory for BankID authentication: the ILS auth dependencies plus BankID's.
 */
class BankIDFactory extends \VuFind\Auth\ILSFactory
{
    /**
     * Create the auth object.
     *
     * @param ContainerInterface $container     Service container
     * @param string             $requestedName Service name
     * @param array|null         $options       Options
     *
     * @return BankID
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $auth = parent::__invoke($container, $requestedName, $options);
        $auth->setBankIdDependencies(
            $container->get(\LOTS\BankID\SessionStore::class),
            $container->get(\VuFind\Config\PluginManager::class)->get('LOTS')
        );
        return $auth;
    }
}
