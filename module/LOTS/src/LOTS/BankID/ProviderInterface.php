<?php

/**
 * BankID identity provider.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * An identity provider that authenticates the user with BankID and returns
 * their personnummer. The rest of the login does not know the protocol, so a
 * SAML provider could be added as another implementation.
 */
interface ProviderInterface
{
    /**
     * URL to send the user to.
     *
     * @param AuthRequest $request Login attempt
     *
     * @throws BankIdException
     * @return string
     */
    public function getAuthorizationUrl(AuthRequest $request): string;

    /**
     * Finish the login after the provider sent the user back.
     *
     * @param string      $code    Authorization code from the callback
     * @param AuthRequest $request Login attempt (state already verified)
     *
     * @throws BankIdException
     * @return Personnummer
     */
    public function fetchPersonnummer(string $code, AuthRequest $request): Personnummer;
}
