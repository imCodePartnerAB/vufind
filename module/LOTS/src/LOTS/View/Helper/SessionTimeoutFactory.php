<?php
namespace LOTS\View\Helper;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class SessionTimeoutFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $authManager = $container->get(\VuFind\Auth\Manager::class);
        return new SessionTimeout($authManager, null);
    }
}
