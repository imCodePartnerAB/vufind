<?php

/**
 * BankID authentication.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  Authentication
 */

namespace LOTS\Auth;

use Laminas\Config\Config;
use LOTS\BankID\BankIdConfig;
use LOTS\BankID\BankIdException;
use LOTS\BankID\SessionStore;
use VuFind\Exception\Auth as AuthException;

/**
 * BankID authentication.
 *
 * BankIDController verifies the user with the provider and stores the
 * personnummer in the session; this class then finds the Koha patron and logs
 * in the VuFind user. It extends the ILS auth so that the VuFind user is
 * created/updated by exactly the same code as a password login
 * (processILSUser: match on Koha patron id), i.e. one account per patron.
 *
 * getSessionInitiator() stays false on purpose: a session initiator would make
 * MyResearch/Home try to authenticate on every visit (Shibboleth model).
 */
class BankID extends \VuFind\Auth\ILS
{
    /**
     * Value written to user.auth_method by the auth manager after login.
     */
    public const AUTH_METHOD = 'bankid';

    /**
     * Session store
     *
     * @var SessionStore
     */
    protected $sessionStore;

    /**
     * LOTS.ini
     *
     * @var Config
     */
    protected $lotsConfig;

    /**
     * Set BankID dependencies (called by the factory).
     *
     * @param SessionStore $sessionStore Session store
     * @param Config       $lotsConfig   LOTS.ini
     *
     * @return void
     */
    public function setBankIdDependencies(SessionStore $sessionStore, Config $lotsConfig)
    {
        $this->sessionStore = $sessionStore;
        $this->lotsConfig = $lotsConfig;
    }

    /**
     * Log in the patron whose personnummer the provider verified.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User
     */
    public function authenticate($request)
    {
        try {
            $config = BankIdConfig::fromLotsConfig($this->lotsConfig);
            $personnummer = $this->sessionStore->takeVerified();
            $patrons = $this->getCatalog()->getPatronsByPersonnummer(
                $personnummer->format($config->getPersonnummerFormat()),
                $config->getPatronField()
            );
        } catch (BankIdException $e) {
            $this->logBankId($e->getDetail() ?: $e->getMessage());
            throw new AuthException($e->getMessage());
        } catch (\Exception $e) {
            $this->logBankId('patron lookup failed: ' . $e->getMessage());
            throw new AuthException('authentication_error_technical');
        }

        if (1 !== count($patrons)) {
            $this->logBankId(
                count($patrons) . ' Koha patrons for ' . $personnummer->masked()
                . ' in ' . $config->getPatronField()
            );
            throw new AuthException(
                $patrons
                    ? BankIdException::AMBIGUOUS_PATRON : BankIdException::NO_PATRON
            );
        }

        $patron = reset($patrons);
        $user = $this->processILSUser($patron);
        // Same as the KohaRest password login: set home library once.
        if (empty($user->home_library) && !empty($patron['home_library'])) {
            $user->changeHomeLibrary($patron['home_library']);
        }
        return $user;
    }

    /**
     * The login request is a GET redirect from BankIDController; it is
     * protected by the OAuth2 state and the one-time, server-side verified
     * personnummer instead of a form CSRF token.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object
     *
     * @return bool
     */
    public function needsCsrfCheck($request)
    {
        return false;
    }

    /**
     * Log a BankID problem (never a full personnummer).
     *
     * @param string $message Message
     *
     * @return void
     */
    protected function logBankId(string $message): void
    {
        error_log('LOTS BankID: ' . $message);
    }
}
