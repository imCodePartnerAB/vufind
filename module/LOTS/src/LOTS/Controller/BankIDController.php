<?php

/**
 * BankID login redirect endpoints.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  Controller
 */

namespace LOTS\Controller;

use LOTS\Auth\LoginMethod;
use LOTS\BankID\AuthRequest;
use LOTS\BankID\BankIdConfig;
use LOTS\BankID\BankIdException;
use LOTS\BankID\ProviderBuilder;
use LOTS\BankID\ProviderInterface;
use LOTS\BankID\SessionStore;

/**
 * BankID login redirect endpoints.
 *
 * Start    - creates a login attempt and sends the user to the provider
 * Callback - provider returns here; verifies state, gets the personnummer,
 *            then hands over to the normal VuFind login (auth_method=BankID)
 * Simulate - development-only stand-in for the provider's page
 *
 * Patron lookup and the VuFind user are handled by LOTS\Auth\BankID.
 */
class BankIDController extends \VuFind\Controller\AbstractBase
{
    /**
     * Send the user to the BankID provider.
     *
     * @return mixed
     */
    public function startAction()
    {
        if (!$this->isBankIdEnabled()) {
            return $this->notFoundAction();
        }
        try {
            $config = $this->getBankIdConfig();
            $request = AuthRequest::create($this->getCallbackUrl($config));
            $url = $this->getProvider($config)->getAuthorizationUrl($request);
            $this->getSessionStore()->saveRequest($request);
        } catch (BankIdException $e) {
            return $this->failure($e);
        }
        return $this->redirect()->toUrl($url);
    }

    /**
     * Return from the BankID provider.
     *
     * @return mixed
     */
    public function callbackAction()
    {
        if (!$this->isBankIdEnabled()) {
            return $this->notFoundAction();
        }
        try {
            $config = $this->getBankIdConfig();
            $request = $this->getSessionStore()->takeRequest(
                (string)$this->params()->fromQuery('state', '')
            );
            $error = (string)$this->params()->fromQuery('error', '');
            if ('' !== $error) {
                throw new BankIdException(
                    'access_denied' === $error
                        ? BankIdException::CANCELLED : BankIdException::FAILED,
                    "provider returned error '$error'"
                );
            }
            $personnummer = $this->getProvider($config)->fetchPersonnummer(
                (string)$this->params()->fromQuery('code', ''),
                $request
            );
            $this->getSessionStore()->saveVerified($personnummer);
        } catch (BankIdException $e) {
            return $this->failure($e);
        }
        // Standard VuFind login; LOTS\Auth\BankID::authenticate() takes over.
        return $this->redirect()->toRoute(
            'myresearch-home',
            [],
            ['query' => ['auth_method' => 'BankID']]
        );
    }

    /**
     * Development stand-in for the provider page (bankid_provider = simulated).
     *
     * @return mixed
     */
    public function simulateAction()
    {
        if (!$this->isBankIdEnabled()) {
            return $this->notFoundAction();
        }
        try {
            $config = $this->getBankIdConfig();
        } catch (BankIdException $e) {
            return $this->failure($e);
        }
        if (!$config->isSimulated()) {
            return $this->notFoundAction();
        }
        return $this->createViewModel(
            [
                'state' => (string)$this->params()->fromQuery('state', ''),
                'numbers' => $config->getSimulatedPersonnummer(),
            ]
        );
    }

    /**
     * Is BankID offered on this site?
     *
     * @return bool
     */
    protected function isBankIdEnabled(): bool
    {
        return LoginMethod::fromLotsConfigOrDefault($this->getConfig('LOTS'))->usesBankId();
    }

    /**
     * BankID settings.
     *
     * @throws BankIdException
     * @return BankIdConfig
     */
    protected function getBankIdConfig(): BankIdConfig
    {
        return BankIdConfig::fromLotsConfig($this->getConfig('LOTS'));
    }

    /**
     * Provider for the settings.
     *
     * @param BankIdConfig $config Settings
     *
     * @return ProviderInterface
     */
    protected function getProvider(BankIdConfig $config): ProviderInterface
    {
        return $this->serviceLocator->get(ProviderBuilder::class)->create(
            $config,
            $this->url()->fromRoute('bankid-simulate')
        );
    }

    /**
     * Session store.
     *
     * @return SessionStore
     */
    protected function getSessionStore(): SessionStore
    {
        return $this->serviceLocator->get(SessionStore::class);
    }

    /**
     * Callback URL: bankid_return_url, or derived from the route.
     *
     * @param BankIdConfig $config Settings
     *
     * @return string
     */
    protected function getCallbackUrl(BankIdConfig $config): string
    {
        return '' !== $config->getReturnUrl()
            ? $config->getReturnUrl()
            : $this->getServerUrl('bankid-callback');
    }

    /**
     * Log, show a message and go back to the login page.
     *
     * @param BankIdException $e Failure
     *
     * @return mixed
     */
    protected function failure(BankIdException $e)
    {
        error_log('LOTS BankID: ' . ($e->getDetail() ?: $e->getMessage()));
        $this->flashMessenger()->addErrorMessage($e->getMessage());
        return $this->redirect()->toRoute('myresearch-userlogin');
    }
}
