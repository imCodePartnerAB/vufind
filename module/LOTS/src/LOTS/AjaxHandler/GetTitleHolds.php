<?php
/**
 * LOTS "Get Title Holds" AJAX handler.
 *
 * Does the title hold check for the records of a result list in one request,
 * so the page itself no longer waits for one Koha request per hit. The patron
 * is always taken from the session, never from the request.
 *
 * @category VuFind
 * @package  AJAX
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace LOTS\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use LOTS\ILS\Logic\TitleHolds;
use VuFind\AjaxHandler\AbstractBase;
use VuFind\Auth\ILSAuthenticator;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;

class GetTitleHolds extends AbstractBase
{
    /**
     * Maximum number of records handled per request.
     *
     * @var int
     */
    protected $maxIds = 100;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * ILS authenticator
     *
     * @var ILSAuthenticator
     */
    protected $ilsAuth;

    /**
     * Title hold logic
     *
     * @var TitleHolds
     */
    protected $titleHolds;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * LOTS.ini configuration
     *
     * @var Config
     */
    protected $lotsConfig;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss         Session settings
     * @param Connection        $ils        ILS connection
     * @param ILSAuthenticator  $ilsAuth    ILS authenticator
     * @param TitleHolds        $titleHolds Title hold logic
     * @param RendererInterface $renderer   View renderer
     * @param Config            $lotsConfig LOTS.ini
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        ILSAuthenticator $ilsAuth,
        TitleHolds $titleHolds,
        RendererInterface $renderer,
        Config $lotsConfig
    ) {
        $this->sessionSettings = $ss;
        $this->ils = $ils;
        $this->ilsAuth = $ilsAuth;
        $this->titleHolds = $titleHolds;
        $this->renderer = $renderer;
        $this->lotsConfig = $lotsConfig;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();
        $none = $this->formatResponse(['holds' => []]);

        if (!($this->lotsConfig->Search->showReservationButton ?? true)
            || 'driver' !== $this->ils->getTitleHoldsMode()
        ) {
            return $none;
        }

        $ids = (array)($params->fromPost('id') ?? $params->fromQuery('id') ?? []);
        $ids = array_values(array_unique(array_filter(
            $ids,
            function ($id) {
                return is_string($id) && preg_match('/^[a-zA-Z0-9]+$/', $id);
            }
        )));
        $ids = array_slice($ids, 0, $this->maxIds);
        if (empty($ids)) {
            return $none;
        }

        try {
            $patron = $this->ilsAuth->storedCatalogLogin();
            if (!$patron) {
                return $none;
            }
            $checkHolds = $this->ils->checkFunction(
                'Holds',
                ['id' => $ids[0], 'patron' => $patron]
            );
            if (empty($checkHolds['HMACKeys'])) {
                return $none;
            }
            $valid = $this->ils->getTitleHoldsBatch($ids, $patron);
        } catch (\Exception $e) {
            return $none;
        }

        $html = [];
        foreach ($valid as $id => $isValid) {
            if ($isValid) {
                $html[$id] = $this->renderer->render(
                    'ajax/title-hold.phtml',
                    [
                        'hold' => $this->titleHolds->getTitleHoldDetails(
                            (string)$id,
                            $checkHolds['HMACKeys']
                        ),
                    ]
                );
            }
        }
        return $this->formatResponse(['holds' => $html]);
    }
}
