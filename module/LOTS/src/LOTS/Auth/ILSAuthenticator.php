<?php

/**
 * ILS authenticator with support for BankID users.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  Authentication
 */

namespace LOTS\Auth;

/**
 * Core storedCatalogLogin() re-validates the stored Koha userid + password on
 * every request. A user who logged in with BankID has no password, so for
 * them the patron is loaded by userid instead (server-side; the userid was set
 * by LOTS\Auth\BankID after verified BankID login). Everyone else goes through
 * the unchanged core code.
 */
class ILSAuthenticator extends \VuFind\Auth\ILSAuthenticator
{
    /**
     * Log the user into the catalog using stored credentials.
     *
     * @return array|bool Patron array, or false if not logged in to the ILS
     */
    public function storedCatalogLogin()
    {
        $user = $this->auth->isLoggedIn();
        if (!$user
            || BankID::AUTH_METHOD !== ($user->auth_method ?? null)
            || empty($user->cat_username)
        ) {
            return parent::storedCatalogLogin();
        }

        if (isset($this->ilsAccount[$user->cat_username])) {
            return $this->ilsAccount[$user->cat_username];
        }
        $patron = $this->catalog->getPatronByUserid($user->cat_username);
        if (empty($patron)) {
            // Patron gone or renamed in Koha: same handling as core.
            $user->clearCredentials();
            return false;
        }
        $this->ilsAccount[$user->cat_username] = $patron;
        return $patron;
    }
}
