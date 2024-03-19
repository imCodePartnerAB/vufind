<?php

namespace LOTS\ILS\Driver;
use VuFind\Exception\ILS as ILSException;
use VuFind\View\Helper\Root\SafeMoneyFormat;

class KohaRest extends \VuFind\ILS\Driver\KohaRest
{
    protected $lotsConfig;

    public function __construct(
        \VuFind\Date\Converter $dateConverter,
        $sessionFactory,
        ?SafeMoneyFormat $safeMoneyFormat
    ) {
        parent::__construct($dateConverter, $sessionFactory, $safeMoneyFormat);
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
     * Update patron's Mobile alert number
     *
     * @param array  $patron Patron array
     * @param string $number Mobile alert number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateMobileNumber($patron, $number)
    {
        $fields = !empty($this->config['updateMobileNumber']['fields'])
            ? explode(',', $this->config['updateMobileNumber']['fields'])
            : ['mobile_number'];

        $update = [];
        foreach ($fields as $field) {
            $update[$field] = $number;
        }

        return $this->updatePatron($patron, $update);
    }

    /**
     * Update patron's phone number
     *
     * @param array  $patron Patron array
     * @param string $phone  Phone number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updatePhone($patron, $phone)
    {
        return $this->updatePatron($patron, ['phone' => $phone]);
    }

    /**
     * Update patron's SMS alert number
     *
     * @param array  $patron Patron array
     * @param string $number SMS alert number
     *
     * @throws ILSException
     *
     * @return array Associative array of the results
     */
    public function updateSmsNumber($patron, $number)
    {
        $fields = !empty($this->config['updateSmsNumber']['fields'])
            ? explode(',', $this->config['updateSmsNumber']['fields'])
            : ['sms_number'];

        $update = [];
        foreach ($fields as $field) {
            $update[$field] = $number;
        }

        return $this->updatePatron($patron, $update);
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
            'sms_number' => $result['sms_number'],
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


    public function updateHolds(
        array $holdsDetails,
        array $fields,
        array $patron
    ): array {
        $results = [];
        foreach ($holdsDetails as $requestId) {
            $updateFields = [];
            // Suspension (bool) has its own endpoint, so we need to distinguish
            // between the cases
            if (isset($fields['frozen'])) {
                if ($fields['frozen']) {
                    if (isset($fields['frozenThrough'])) {
                        $updateFields['suspended_until']
                        = date('c', $fields['frozenThroughTS'] . ' 23:59:59'); //(rfc3339) else KOHA give internal error
                        #= date('Y-m-d', $fields['frozenThroughTS']) . ' 23:59:59';
                        $result = false;
                    } else {
                        $result = $this->makeRequest(
                            [
                                'path' => ['v1', 'holds', $requestId, 'suspension'],
                                'method' => 'POST',
                                'errors' => true
                            ],
                            true
                        );
                        $results[$requestId]['success'] = empty($results[$requestId]['status']);
                    }
                } else {
                    $result = $this->makeRequest(
                        [
                            'path' => ['v1', 'holds', $requestId, 'suspension'],
                            'method' => 'DELETE',
                            'errors' => true
                        ]
                    );
                }
                if ($result && $result['code'] >= 300) {
                    $results[$requestId]['status']
                        = $result['data']['error'] ?? 'hold_error_update_failed';
                }
            }
            if (empty($results[$requestId]['errors'])) {
                if (isset($fields['pickUpLocation'])) {
                    $updateFields['pickup_library_id'] = $fields['pickUpLocation'];
                }
                if ($updateFields) {
                    $result = $this->makeRequest(
                        [
                            'path' => ['v1', 'holds', $requestId],
                            'method' => 'PUT',
                            'json' => $updateFields,
                            'errors' => true
                        ]
                    );
                    if ($result['code'] >= 300) {
                        $results[$requestId]['status']
                            = $result['data']['error'] ?? 'hold_error_update_failed';
                    }
                }
            }

            $results[$requestId]['success'] = empty($results[$requestId]['status']);
        }

        return $results;
    }    

    protected function makeRequest($request, $dojson = false)
    {
        // Set up the request
        $apiUrl = $this->config['Catalog']['host'] . '/';

        // Handle the simple case of just a path in $request
        if (is_string($request) || !isset($request['path'])) {
            $request = [
                'path' => $request
            ];
        }

        if (is_array($request['path'])) {
            $apiUrl .= implode('/', array_map('urlencode', $request['path']));
        } else {
            $apiUrl .= $request['path'];
        }

        $client = $this->createHttpClient($apiUrl);
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Authorization', $this->getOAuth2Token());

        if ($dojson) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine(
                    'Content-Type',
                    'application/json'
                );
        }

        // Add params
        if (!empty($request['query'])) {
            $client->setParameterGet($request['query']);
        }
        if (!empty($request['form'])) {
            $client->setParameterPost($request['form']);
        } elseif (!empty($request['json'])) {
            $client->getRequest()->setContent(json_encode($request['json']));
            $client->getRequest()->getHeaders()->addHeaderLine(
                'Content-Type',
                'application/json'
            );
        }

        if (!empty($request['headers'])) {
            $requestHeaders = $client->getRequest()->getHeaders();
            foreach ($request['headers'] as $name => $value) {
                $requestHeaders->addHeaderLine($name, [$value]);
            }
        }

        // Send request and retrieve response
        $method = $request['method'] ?? 'GET';
        $startTime = microtime(true);
        $client->setMethod($method);

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->logError(
                "$method request for '$apiUrl' failed: " . $e->getMessage()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        // If we get a 401, we need to renew the access token and try again
        if ($response->getStatusCode() == 401) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine('Authorization', $this->getOAuth2Token(true));

            try {
                $response = $client->send();
            } catch (\Exception $e) {
                $this->logError(
                    "$method request for '$apiUrl' failed: " . $e->getMessage()
                );
                throw new ILSException('Problem with Koha REST API.');
            }
        }

        $result = $response->getBody();

        $fullUrl = $apiUrl;
        if ($method == 'GET') {
            $fullUrl .= '?' . $client->getRequest()->getQuery()->toString();
        }
        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $method request $fullUrl" . PHP_EOL . 'response: ' . PHP_EOL
            . $result
        );

        // Handle errors as complete failures only if the API call didn't return
        // valid JSON that the caller can handle
        $decodedResult = json_decode($result, true);
        if (empty($request['errors']) && !$response->isSuccess()
            && (null === $decodedResult || !empty($decodedResult['error'])
            || !empty($decodedResult['errors']))
        ) {
            $params = $method == 'GET'
                ? $client->getRequest()->getQuery()->toString()
                : $client->getRequest()->getPost()->toString();
            $this->logError(
                "$method request for '$apiUrl' with params '$params' and contents '"
                . $client->getRequest()->getContent() . "' failed: "
                . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );
            throw new ILSException('Problem with Koha REST API.');
        }

        return [
            'data' => $decodedResult,
            'code' => (int)$response->getStatusCode(),
            'headers' => $response->getHeaders()->toArray(),
        ];
    }

    public function getConfig($function, $params = null)
    {
        $transactionHistorySort = $this->lotsConfig->TransactionHistory->sort;
        $transactionSort = $this->lotsConfig->Transaction->sort;
        if ('getMyTransactionHistory' === $function) {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }
            $limit = $this->config['TransactionHistory']['max_page_size'] ?? 100;
            return [
                'max_results' => $limit,
                'sort' => [
                    '-checkout_date' => 'sort_checkout_date_desc',
                    '+checkout_date' => 'sort_checkout_date_asc',
                    '-checkin_date' => 'sort_return_date_desc',
                    '+checkin_date' => 'sort_return_date_asc',
                    '-due_date' => 'sort_due_date_desc',
                    '+due_date' => 'sort_due_date_asc',
                    '+title' => 'sort_title'
                ],
                'default_sort' => $transactionHistorySort
            ];
        } elseif ('getMyTransactions' === $function) {
            $limit = $this->config['Loans']['max_page_size'] ?? 100;
            return [
                'max_results' => $limit,
                'sort' => [
                    '-checkout_date' => 'sort_checkout_date_desc',
                    '+checkout_date' => 'sort_checkout_date_asc',
                    '-due_date' => 'sort_due_date_desc',
                    '+due_date' => 'sort_due_date_asc',
                    '+title' => 'sort_title'
                ],
                'default_sort' => $transactionSort
            ];
        }

        return $this->config[$function] ?? false;
    }
    
    public function patronLogin($username, $password)
    {

        $parent_info =  parent::patronLogin($username, $password);
        if ($parent_info === null) {
            return null;
        }

        $dbUser = $this->getDbTableManager()->get('User')->getByUsername($username);

        if ($dbUser['email'] != $parent_info['email']) {
            $dbUser->updateEmail($parent_info['email'],true);
            $dbUser->save();
        }

        return $parent_info;
    }

    protected function getTransactions($patron, $params, $checkedIn)
    {
        $pageSize = $params['limit'] ?? 50;
        $sort = $params['sort'] ?? '+due_date';
        if ('+title' === $sort) {
            $sort = '+title|+subtitle';
        } elseif ('-title' === $sort) {
            $sort = '-title|-subtitle';
        }
        $queryParams = [
            '_order_by' => $sort,
            '_page' => $params['page'] ?? 1,
            '_per_page' => $pageSize
        ];
        if ($checkedIn) {
            $queryParams['checked_in'] = '1';
            $arrayKey = 'transactions';
        } else {
            $arrayKey = 'records';
        }
        $result = $this->makeRequest(
            [
                'path' => [
                    'v1', 'contrib', 'kohasuomi', 'patrons', $patron['id'],
                    'checkouts'
                ],
                'query' => $queryParams
            ]
        );

        if (200 !== $result['code']) {
            throw new ILSException('Problem with Koha REST API.');
        }

        if (empty($result['data'])) {
            return [
                'count' => 0,
                $arrayKey => []
            ];
        }
        $transactions = [];
        foreach ($result['data'] as $entry) {
            $dueStatus = false;
            $now = time();
            $dueTimeStamp = strtotime($entry['due_date']);
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = 'overdue';
                } elseif ($now > $dueTimeStamp - (1 * 24 * 60 * 60)) {
                    $dueStatus = 'due';
                }
            }

            $renewable = $entry['renewable'];
            #$renewals = $entry['renewals'];
            $renewals = isset($entry['renewals']) ? $entry['renewals'] : null;
            $renewLimit = $entry['max_renewals'];
            $message = '';
            if (!$renewable && !$checkedIn) {
                $message = $this->mapRenewalBlockReason(
                    $entry['renewability_blocks']
                );
                $permanent = in_array(
                    $entry['renewability_blocks'],
                    $this->permanentRenewalBlocks
                );
                if ($permanent) {
                    $renewals = null;
                    $renewLimit = null;
                }
            }

            $transaction = [
                'id' => $entry['biblio_id'],
                'checkout_id' => $entry['checkout_id'],
                'item_id' => $entry['item_id'],
                'barcode' => $entry['external_id'] ?? null,
                'title' => $this->getBiblioTitle($entry),
                'volume' => $entry['serial_issue_number'] ?? '',
                'publication_year' => $entry['copyright_date']
                    ?? $entry['publication_year'] ?? '',
                'borrowingLocation' => $this->getLibraryName($entry['library_id']),
                'checkoutDate' => $this->convertDate($entry['checkout_date']),
                'duedate' => $this->convertDate($entry['due_date']),#, true),
                'returnDate' => $this->convertDate($entry['checkin_date']),
                'dueStatus' => $dueStatus,
                'renew' => $renewals,
                'renewLimit' => $renewLimit,
                'renewable' => $renewable,
                'renewals_count' => $entry['renewals_count'],
                'message' => $message
            ];

            $transactions[] = $transaction;
        }

        return [
            'count' => $result['headers']['X-Total-Count'] ?? count($transactions),
            $arrayKey => $transactions
        ];
    }    
}
