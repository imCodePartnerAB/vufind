<?php

/**
 * Applies LOTS.ini [Authentication] login_method to the effective config.ini.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  Config
 */

namespace LOTS\Config;

use Laminas\Config\Config;
use LOTS\Auth\LoginMethod;
use Psr\Container\ContainerInterface;
use VuFind\Config\PluginManager;

/**
 * Delegator for the "config" service of the VuFind config plugin manager.
 *
 * LOTS.ini is the single source of truth for which login methods a site offers.
 * For "bankid" and "both" this delegator sets [Authentication] method (and
 * [ChoiceAuth] choice_order) in the config object every consumer receives, so
 * the auth manager, ChoiceAuth and templates all see the same value. For
 * "password" the original config object is returned unchanged.
 */
class LoginMethodDelegatorFactory
{
    /**
     * Create the decorated config.
     *
     * @param ContainerInterface $container Service container
     * @param string             $name      Service name ("config")
     * @param callable           $callback  Creates the original config
     * @param array|null         $options   Options (unused)
     *
     * @return Config
     */
    public function __invoke(
        ContainerInterface $container,
        $name,
        callable $callback,
        ?array $options = null
    ) {
        $config = $callback();

        $configManager = $container instanceof PluginManager
            ? $container : $container->get(PluginManager::class);
        $loginMethod = LoginMethod::fromLotsConfigOrDefault(
            $configManager->get('LOTS')
        );

        $authMethod = $loginMethod->getAuthMethod();
        if (null === $authMethod) {
            return $config;
        }

        $values = $config->toArray();
        $values['Authentication']['method'] = $authMethod;
        $choiceOrder = $loginMethod->getChoiceOrder();
        if (null !== $choiceOrder) {
            $values['ChoiceAuth']['choice_order'] = $choiceOrder;
        }

        // Read-only, like every other config object VuFind hands out.
        return new Config($values);
    }
}
