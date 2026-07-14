<?php
/**
 * Factory for LOTS GetItemStatuses AJAX handler (VuFind 11).
 *
 * Mirrors the core v11 GetItemStatusesFactory (six dependencies + setSorter)
 * and additionally injects LOTS.ini so the handler can read its feature flags.
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
        $configManager = $container->get(\VuFind\Config\ConfigManagerInterface::class);
        $handler = new $requestedName(
            $container->get(\VuFind\Session\Settings::class),
            $configManager->getConfigObject('config'),
            $container->get(\VuFind\ILS\Connection::class),
            $container->get('ViewRenderer'),
            $container->get(\VuFind\ILS\Logic\Holds::class),
            $container->get(\VuFind\ILS\Logic\AvailabilityStatusManager::class)
        );
        $handler->setSorter($container->get(\VuFind\I18n\Sorter::class));
        // Inject LOTS.ini so LOTS-specific feature flags are available.
        if (method_exists($handler, 'setLotsConfig')) {
            $handler->setLotsConfig($configManager->getConfigObject('LOTS'));
        }
        return $handler;
    }
}
