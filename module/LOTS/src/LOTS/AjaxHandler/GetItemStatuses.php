<?php
/**
 * LOTS "Get Item Status" AJAX handler.
 *
 * Overrides the core handler so that a record which is unavailable *only*
 * because all of its copies are on order (Koha notforloan = -1, mapped by the
 * LOTS KohaRest driver to the "On Order" status) shows a "Beställd" badge in
 * search results instead of the generic unavailable ("Utlånad" / "Ej
 * tillgänglig") badge.
 *
 * The core handler collapses per-copy status into a single available/unavailable
 * boolean before the badge is rendered, discarding the reason for
 * unavailability. This override inspects the per-copy status the driver already
 * provides and, when every copy is "On Order", substitutes the "Beställd" badge.
 *
 * Guarded by LOTS.ini [Item_Status] onOrderBadge (default false): when the flag
 * is off, behaviour is byte-for-byte identical to the core handler.
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
     * @var \Laminas\Config\Config
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
     * Inject LOTS.ini configuration.
     *
     * @param \Laminas\Config\Config $lotsConfig LOTS.ini
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
     * False if it has no copies, any available copy, or any unavailable copy
     * that is not on order (e.g. checked out) -- we must not mislabel those.
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
            if (!empty($info['availability'])) {
                return false;
            }
            if (($info['status'] ?? null) !== $this->onOrderStatus) {
                return false;
            }
        }
        return true;
    }

    /**
     * Render the "Beställd" badge.
     *
     * @return string
     */
    protected function renderOnOrderBadge()
    {
        return '<span class="label label-warning">'
            . htmlspecialchars($this->translate($this->onOrderLabel))
            . "</span>\n";
    }

    /**
     * Substitute the on-order badge into a summarized entry when enabled and
     * the record qualifies.
     *
     * @param array $current Summarized info (parent return value)
     * @param array $record  Per-copy records that produced $current
     *
     * @return array
     */
    protected function applyOnOrderBadge($current, $record)
    {
        if (!$this->onOrderBadgeEnabled()) {
            return $current;
        }
        if (($current['availability'] ?? null) === 'false'
            && $this->isAllOnOrder($record)
        ) {
            $current['availability_message'] = $this->renderOnOrderBadge();
        }
        return $current;
    }

    /**
     * Non-group location setting.
     *
     * @param array  $record            Items for one bib record
     * @param array  $messages          Custom status HTML
     * @param string $locationSetting   Location mode setting
     * @param string $callnumberSetting Callnumber mode setting
     *
     * @return array
     */
    protected function getItemStatus(
        $record,
        $messages,
        $locationSetting,
        $callnumberSetting
    ) {
        $current = parent::getItemStatus(
            $record,
            $messages,
            $locationSetting,
            $callnumberSetting
        );
        return $this->applyOnOrderBadge($current, $record);
    }

    /**
     * "group" location setting.
     *
     * @param array  $record            Items for one bib record
     * @param array  $messages          Custom status HTML
     * @param string $callnumberSetting Callnumber mode setting
     *
     * @return array
     */
    protected function getItemStatusGroup($record, $messages, $callnumberSetting)
    {
        $current = parent::getItemStatusGroup(
            $record,
            $messages,
            $callnumberSetting
        );
        return $this->applyOnOrderBadge($current, $record);
    }
}
