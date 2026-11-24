<?php

/**
 * Koha patron lookup without password, for BankID login.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  ILS_Drivers
 */

namespace LOTS\ILS\Driver;

use VuFind\Exception\ILS as ILSException;

/**
 * Koha patron lookup without password, for BankID login (LOTS-90).
 *
 * Used by LOTS\ILS\Driver\KohaRest. Only called from server-side code after a
 * verified BankID login (LOTS\Auth\BankID, LOTS\Auth\ILSAuthenticator); there
 * is no route through which a user could call these with their own input.
 * Uses the driver's OAuth2 client credentials (makeRequest).
 */
trait BankIdPatronTrait
{
    /**
     * Find patrons by personnummer.
     *
     * @param string $value Personnummer, already in the stored format
     * @param string $field cardnumber | userid | attribute:<code>
     *
     * @throws ILSException
     * @return array List of patron arrays (patronLogin() format), max 2 entries
     */
    public function getPatronsByPersonnummer(string $value, string $field): array
    {
        if ('' === $value) {
            return [];
        }
        if (0 === strpos($field, 'attribute:')) {
            $request = [
                'query' => [
                    'q' => json_encode(
                        [
                            'extended_attributes.code' => substr($field, strlen('attribute:')),
                            'extended_attributes.attribute' => $value,
                        ]
                    ),
                ],
                'headers' => ['x-koha-embed' => 'extended_attributes'],
            ];
        } elseif (in_array($field, ['cardnumber', 'userid'], true)) {
            $request = ['query' => [$field => $value, '_match' => 'exact']];
        } else {
            throw new ILSException("Unsupported BankID patron field '$field'");
        }
        // Two results are enough to detect ambiguity.
        $request['query']['_per_page'] = 2;
        return array_map([$this, 'createBankIdPatron'], $this->findKohaPatrons($request));
    }

    /**
     * Load the patron for a stored userid (exact match, exactly one).
     *
     * @param string $userid Koha userid
     *
     * @throws ILSException
     * @return array|null Patron array (patronLogin() format)
     */
    public function getPatronByUserid(string $userid): ?array
    {
        if ('' === $userid) {
            return null;
        }
        $patrons = $this->findKohaPatrons(
            ['query' => ['userid' => $userid, '_match' => 'exact', '_per_page' => 2]]
        );
        return 1 === count($patrons) ? $this->createBankIdPatron($patrons[0]) : null;
    }

    /**
     * GET /v1/patrons with the given query.
     *
     * @param array $request Partial makeRequest() request (query, headers)
     *
     * @throws ILSException
     * @return array Raw Koha patrons
     */
    protected function findKohaPatrons(array $request): array
    {
        $result = $this->makeRequest(
            $request + ['path' => 'v1/patrons', 'method' => 'GET', 'errors' => true]
        );
        if (200 !== $result['code'] || !is_array($result['data'])) {
            throw new ILSException('Problem with Koha REST API.');
        }
        return array_values($result['data']);
    }

    /**
     * Koha patron -> VuFind patron array, same keys as KohaRest::patronLogin().
     * There is no password: cat_password is null.
     *
     * @param array $patron Koha patron
     *
     * @return array
     */
    protected function createBankIdPatron(array $patron): array
    {
        return [
            'id' => $patron['patron_id'],
            'firstname' => $patron['firstname'] ?? '',
            'lastname' => $patron['surname'] ?? '',
            'cat_username' => $patron['userid'] ?? '',
            'cat_password' => null,
            'email' => $patron['email'] ?? '',
            'major' => null,
            'college' => null,
            'home_library' => $patron['library_id'] ?? '',
        ];
    }
}
