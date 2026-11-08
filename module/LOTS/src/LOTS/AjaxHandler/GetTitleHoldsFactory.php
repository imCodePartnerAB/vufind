<?php
/**
 * Factory for the LOTS GetTitleHolds AJAX handler.
 *
 * @category VuFind
 * @package  AJAX
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace LOTS\AjaxHandler;

use Psr\Container\ContainerInterface;

class GetTitleHoldsFactory
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
        return new $requestedName(
            $container->get(\VuFind\Session\Settings::class),
            $container->get(\VuFind\ILS\Connection::class),
            $container->get(\VuFind\Auth\ILSAuthenticator::class),
            $container->get(\LOTS\ILS\Logic\TitleHolds::class),
            $container->get('ViewRenderer'),
            $container->get(\VuFind\Config\PluginManager::class)->get('LOTS')
        );
    }
}
