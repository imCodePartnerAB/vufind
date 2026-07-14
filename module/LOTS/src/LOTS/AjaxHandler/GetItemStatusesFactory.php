<?php
/**
 * Factory for LOTS GetItemStatuses AJAX handler.
 *
 * Mirrors the core GetItemStatusesFactory (same five dependencies) and
 * additionally injects LOTS.ini so the handler can read its own feature flags.
 *
 * @category VuFind
 * @package  AJAX
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace LOTS\AjaxHandler;

use Psr\Container\ContainerInterface;

class GetItemStatusesFactory
    implements \Laminas\ServiceManager\Factory\FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $configManager = $container->get(\VuFind\Config\PluginManager::class);
        $handler = new $requestedName(
            $container->get(\VuFind\Session\Settings::class),
            $configManager->get('config'),
            $container->get(\VuFind\ILS\Connection::class),
            $container->get('ViewRenderer'),
            $container->get(\VuFind\ILS\Logic\Holds::class)
        );
        // Inject LOTS.ini so LOTS-specific feature flags are available.
        if (method_exists($handler, 'setLotsConfig')) {
            $handler->setLotsConfig($configManager->get('LOTS'));
        }
        return $handler;
    }
}
