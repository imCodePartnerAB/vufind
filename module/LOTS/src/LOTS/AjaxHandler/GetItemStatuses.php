<?php
/**
 * LOTS "Get Item Status" AJAX handler (VuFind 11).
 *
 * Shows a "Beställd" badge for records that are unavailable *only* because all
 * copies are on order (driver status "On Order"), instead of the generic
 * unavailable badge. Mirrors the tested lots-8.0 fix, adapted to the VuFind 11
 * AvailabilityStatus architecture: here the badge text is produced by
 * getAvailabilityMessage(), so that is the override point.
 *
 * !!! SUPVARM-277 (VuFind 11): NOT tested on a live v11 instance — no v11
 * runtime exists yet at the time of writing. The logic mirrors the lots-8.0
 * fix that WAS verified end to end. Guarded by LOTS.ini [Item_Status]
 * onOrderBadge (default false); keep it OFF until validated on a real v11
 * instance. When the flag is off, behaviour is identical to core.
 *
 * @category VuFind
 * @package  AJAX
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 */
namespace LOTS\AjaxHandler;

use VuFind\AjaxHandler\GetItemStatuses as CoreGetItemStatuses;

class GetItemStatuses extends CoreGetItemStatuses
{
    /**
     * LOTS.ini configuration.
     *
     * @var \VuFind\Config\Config
     */
    protected $lotsConfig;

    /**
     * Per-copy status (as produced by the ILS driver) identifying on-order.
     *
     * @var string
     */
    protected $onOrderStatus = 'On Order';

    /**
     * Translation key whose value is the "Beställd" label.
     *
     * @var string
     */
    protected $onOrderLabel = 'On Order';

    /**
     * The record currently being summarized, captured so getAvailabilityMessage()
     * can inspect its per-copy statuses. Reset around each summary call.
     *
     * @var array|null
     */
    protected $currentRecord = null;

    /**
     * Inject LOTS.ini configuration.
     *
     * @param \VuFind\Config\Config $lotsConfig LOTS.ini
     *
     * @return void
     */
    public function setLotsConfig($lotsConfig)
    {
        $this->lotsConfig = $lotsConfig;
    }

    /**
     * Is the on-order badge feature enabled in LOTS.ini?
     *
     * @return bool
     */
    protected function onOrderBadgeEnabled()
    {
        return (bool)($this->lotsConfig->Item_Status->onOrderBadge ?? false);
    }

    /**
     * Is the record unavailable purely because every copy is on order?
     *
     * @param array $record Items linked to a single bib record
     *
     * @return bool
     */
    protected function isAllOnOrder($record)
    {
        if (empty($record)) {
            return false;
        }
        foreach ($record as $info) {
            $status = $info['availability'] ?? null;
            // In v11 $info['availability'] is an AvailabilityStatus object.
            if (is_object($status) && method_exists($status, 'isAvailable')
                && $status->isAvailable()
            ) {
                return false;
            }
            if (($info['status'] ?? null) !== $this->onOrderStatus) {
                return false;
            }
        }
        return true;
    }

    /**
     * Override: when the current record is all-on-order and the feature is on,
     * return the "Beställd" badge instead of the generic unavailable message.
     *
     * @param \VuFind\ILS\Logic\AvailabilityStatusInterface $availability Status
     *
     * @return string
     */
    protected function getAvailabilityMessage(
        \VuFind\ILS\Logic\AvailabilityStatusInterface $availability
    ): string {
        if ($this->onOrderBadgeEnabled()
            && !$availability->isAvailable()
            && is_array($this->currentRecord)
            && $this->isAllOnOrder($this->currentRecord)
        ) {
            return '<span class="label label-warning">'
                . htmlspecialchars($this->translate($this->onOrderLabel))
                . "</span>\n";
        }
        return parent::getAvailabilityMessage($availability);
    }

    /**
     * Non-group location setting. Captures $record for getAvailabilityMessage().
     *
     * @param array  $record            Items for one bib record
     * @param string $locationSetting   Location mode setting
     * @param string $callnumberSetting Callnumber mode setting
     *
     * @return array
     */
    protected function getItemStatus($record, $locationSetting, $callnumberSetting)
    {
        $this->currentRecord = $record;
        try {
            return parent::getItemStatus(
                $record,
                $locationSetting,
                $callnumberSetting
            );
        } finally {
            $this->currentRecord = null;
        }
    }

    /**
     * "group" location setting. Captures $record for getAvailabilityMessage().
     *
     * @param array  $record            Items for one bib record
     * @param string $callnumberSetting Callnumber mode setting
     *
     * @return array
     */
    protected function getItemStatusGroup($record, $callnumberSetting)
    {
        $this->currentRecord = $record;
        try {
            return parent::getItemStatusGroup($record, $callnumberSetting);
        } finally {
            $this->currentRecord = null;
        }
    }
}
