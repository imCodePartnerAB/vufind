<?php
/**
 * LOTS "Get Branch Availability" AJAX handler.
 *
 * Returns the per-branch availability block shown in the search result list
 * for a batch of records, so the result page itself no longer waits for one
 * Koha request per hit. The response is identical for every user: it contains
 * no patron data.
 *
 * @category VuFind
 * @package  AJAX
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace LOTS\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\AjaxHandler\AbstractBase;
use VuFind\ILS\Connection;
use VuFind\Session\Settings as SessionSettings;

class GetBranchAvailability extends AbstractBase
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
     * @param RendererInterface $renderer   View renderer
     * @param Config            $lotsConfig LOTS.ini
     */
    public function __construct(
        SessionSettings $ss,
        Connection $ils,
        RendererInterface $renderer,
        Config $lotsConfig
    ) {
        $this->sessionSettings = $ss;
        $this->ils = $ils;
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

        if (!($this->lotsConfig->Record->showAvailabilityInResults ?? false)) {
            return $this->formatResponse(['availability' => []]);
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
            return $this->formatResponse(['availability' => []]);
        }

        try {
            $availability = $this->ils->getAvailabilityBatch($ids);
            $branchNames = [];
            foreach ($this->ils->getAvailableBranches() as $branch) {
                $branchNames[$branch['id']] = $branch['name'];
            }
        } catch (\Exception $e) {
            return $this->formatResponse(['availability' => []]);
        }

        $maxBranches = (int)($this->lotsConfig->Record->maxBranches ?? 100);
        $html = [];
        foreach ($availability as $id => $branches) {
            if ($branches === null) {
                continue;
            }
            $html[$id] = $this->renderBlock($id, $branches, $branchNames, $maxBranches);
        }
        return $this->formatResponse(['availability' => $html]);
    }

    /**
     * Render the availability block for one record.
     *
     * @param string $id          Record id
     * @param array  $branches    Branch id => ['available' => int, 'total' => int]
     * @param array  $branchNames Branch id => name
     * @param int    $maxBranches Maximum number of branches to list
     *
     * @return string
     */
    protected function renderBlock($id, $branches, $branchNames, $maxBranches)
    {
        $valid = [];
        foreach ($branches as $branchId => $data) {
            if (count($valid) >= $maxBranches) {
                break;
            }
            if (!preg_match('/^[a-zA-Z0-9]+$/', (string)$branchId) || $data['total'] <= 0) {
                continue;
            }
            $valid[$branchId] = [
                'name' => $branchNames[$branchId] ?? 'Unnamed Branch',
                'available' => (int)$data['available'],
                'total' => (int)$data['total'],
            ];
        }
        return $this->renderer->render(
            'ajax/branch-availability.phtml',
            [
                'id' => $id,
                'branches' => $valid,
                'totalAvailable' => array_sum(array_column($valid, 'available')),
                'totalCopies' => array_sum(array_column($valid, 'total')),
            ]
        );
    }
}
