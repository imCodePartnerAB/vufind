<?php

/**
 * Simulated BankID provider for development.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * Development stand-in for a real provider: the user picks one of the test
 * personnummer listed in LOTS.ini (bankid_simulated_personnummer) on a LOTS
 * page and goes through the same callback and login code as with a real
 * provider. Only listed numbers are accepted, so a forgotten setting in
 * production cannot be used to log in as an arbitrary patron.
 */
class SimulatedProvider implements ProviderInterface
{
    /**
     * Allowed test numbers.
     *
     * @var Personnummer[]
     */
    protected $allowed;

    /**
     * URL of the simulation page.
     *
     * @var string
     */
    protected $simulateUrl;

    /**
     * Constructor
     *
     * @param Personnummer[] $allowed     Allowed test numbers
     * @param string         $simulateUrl URL of the simulation page
     */
    public function __construct(array $allowed, string $simulateUrl)
    {
        $this->allowed = $allowed;
        $this->simulateUrl = $simulateUrl;
    }

    /**
     * URL to send the user to.
     *
     * @param AuthRequest $request Login attempt
     *
     * @return string
     */
    public function getAuthorizationUrl(AuthRequest $request): string
    {
        error_log('LOTS BankID: SIMULATED provider in use - development only');
        return $this->simulateUrl . '?' . http_build_query(['state' => $request->getState()]);
    }

    /**
     * Finish the login; the "code" is the chosen test personnummer.
     *
     * @param string      $code    Chosen test number
     * @param AuthRequest $request Login attempt
     *
     * @throws BankIdException
     * @return Personnummer
     */
    public function fetchPersonnummer(string $code, AuthRequest $request): Personnummer
    {
        try {
            $chosen = Personnummer::fromString($code);
        } catch (\InvalidArgumentException $e) {
            throw new BankIdException(BankIdException::FAILED, 'simulated: invalid number', $e);
        }
        foreach ($this->allowed as $allowed) {
            if ($allowed->equals($chosen)) {
                return $chosen;
            }
        }
        throw new BankIdException(
            BankIdException::FAILED,
            'simulated: ' . $chosen->masked() . ' is not in bankid_simulated_personnummer'
        );
    }

    /**
     * Numbers to offer on the simulation page.
     *
     * @return Personnummer[]
     */
    public function getAllowed(): array
    {
        return $this->allowed;
    }
}
