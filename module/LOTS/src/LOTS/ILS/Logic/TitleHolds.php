<?php
/**
 * LOTS title hold logic.
 *
 * Exposes the hold link generation of the core class, so a link can be built
 * for a record whose hold check was already done in a batch.
 *
 * @category VuFind
 * @package  ILS_Logic
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace LOTS\ILS\Logic;

class TitleHolds extends \VuFind\ILS\Logic\TitleHolds
{
    /**
     * Get the details needed to build the title hold URL for a record.
     *
     * @param string $id       Record id
     * @param array  $HMACKeys Keys to hash, from the "Holds" ILS configuration
     *
     * @return array Details for RecordLinker::getRequestUrl()
     */
    public function getTitleHoldDetails($id, array $HMACKeys)
    {
        return $this->getHoldDetails(['id' => $id, 'level' => 'title'], $HMACKeys);
    }
}
