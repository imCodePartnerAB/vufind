<?php

// /usr/local/vufind/module/LOTS/src/LOTS/Controller/MyResearchControllerFactory.php

namespace LOTS\Controller;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class MyResearchControllerFactory implements FactoryInterface 
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $session = new \Laminas\Session\Container(
            'cart_followup',
            $container->get(\Laminas\Session\SessionManager::class)
        );
        $configLoader = $container->get(\VuFind\Config\PluginManager::class);
        $export = $container->get(\VuFind\Export::class);
        return new MyResearchController($container, $session, $configLoader, $export);
    }
}
