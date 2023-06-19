<?php

namespace LOTS\ILS\Driver;

class KohaRest extends \VuFind\ILS\Driver\KohaRest
{
    protected $lotsConfig;

    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        $sessionFactory,
        ?SafeMoneyFormat $safeMoneyFormat
    ) {
        parent::__construct($dateConverter,$sessionFactory,$safeMoneyFormat);
    }

public function setLotsConfig($lotsConfig)
{
$this->lotsConfig = $lotsConfig;
}

    /** Added for LOTS to set history. LOBININTEG-19
      * Update Patron Transaction History State
      *
      * Enable or disable patron's transaction history
      *
      * @param array $patron The patron array from patronLogin
      * @param mixed $state  Any of the configured values
      *
      * @return array Associative array of the results
      */
    public function updateTransactionHistoryState($patron, $state)
    {
        return $this->updatePatron($patron, ['privacy' => (int)$state]);
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $result = $this->makeRequest(['v1', 'patrons', $patron['id']]);

        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        $result = $result['data'];
        return [
            'firstname' => $result['firstname'],
            'lastname' => $result['surname'],
            'phone' => $result['phone'],
            'mobile_phone' => $result['mobile'],
            'email' => $result['email'],
            'address1' => $result['address'],
            'address2' => $result['address2'],
            'zip' => $result['postal_code'],
            'city' => $result['city'],
            'country' => $result['country'],
            'loan_history' => $result['privacy'],
            'expiration_date' => $this->convertDate($result['expiry_date'] ?? null)
        ];
    }

    /**
     * Update a patron in Koha with the data in $fields
     *
     * @param array $patron The patron array from patronLogin
     * @param array $fields Patron fields to update
     *
     * @return array ILS driver response
     */
    protected function updatePatron($patron, $fields)
    {
        $result = $this->makeRequest(['v1', 'patrons', $patron['id']]);

        $request = $result['data'];
        // Unset read-only fields
        unset($request['anonymized']);
        unset($request['restricted']);

        $request = array_merge($request, $fields);

        $result = $this->makeRequest(
            [
                'path' => ['v1', 'patrons', $patron['id']],
                'json' => $request,
                'method' => 'PUT',
                'errors' => true
            ]
        );
        if ($result['code'] >= 300) {
            return [
                'success' => false,
                'status' => 'Updating of patron information failed',
                'sys_message' => $result['data']['error'] ?? $result['code']
            ];
        }

        return [
            'success' => true,
            'status' => 202 === $result['code']
                ? 'request_change_done' : 'request_change_accepted',
            'sys_message' => ''
        ];
    }
    /**
     * Helper function for formatting currency
     *
     * @param $amount Number to format
     *
     * @return string
     */
    protected function formatMoney($amount)
    {
        $byPass = $this->lotsConfig->General->byPassSafeMoneyFormat;
        # LOTS  SafeMoney does not work
        if (isset($byPass) && $byPass == "1") {
            return $amount;
        } elseif (null === $this->safeMoneyFormat) {
            throw new \Exception('SafeMoneyFormat helper not available');
        }
        return ($this->safeMoneyFormat)($amount);
    }

    /**
     * Return summary of holdings items.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array summary
     */
    protected function getHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = 0;
        $requests = 0;
        $locations = [];

        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (strncmp($item['item_id'], 'HLD_', 4) !== 0) {
                $itemsTotal++;
            }
            $locations[$item['location']] = true;
            if ($item['requests_placed'] > $requests) {
                $requests = $item['requests_placed'];
            }
        }

        // Since summary data is appended to the holdings array as a fake item,
        // we need to add a few dummy-fields that VuFind expects to be
        // defined for all elements.

        // Use a stupid location name to make sure this doesn't get mixed with
        // real items that don't have a proper location.
        $result = [
           'available' => $availableTotal,
           'total' => $itemsTotal,
           'locations' => count($locations),
           'availability' => null,
           'callnumber' => null,
           'location' => '__HOLDINGSSUMMARYLOCATION__'
        ];
        if (!empty($this->config['Holdings']['display_total_hold_count'])) {
            $result['reservations'] = $requests;
        }
        return $result;
    }

}

