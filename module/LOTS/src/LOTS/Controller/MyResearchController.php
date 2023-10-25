<?php

namespace LOTS\Controller;

use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\AuthToken as AuthTokenException;

class MyResearchController extends \VuFind\Controller\MyResearchController implements
\VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\ILS\Driver\OAuth2TokenTrait;

    
    protected $koha_rest_config = null;
    protected $oath_token = null;

    public function profileAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        $values = $this->getRequest()->getPost();
        $view = parent::profileAction();
        $patron = $this->catalogLogin();
        $history = $this->params()->fromPost('loan_history', false);

        if (is_array($patron) && $history >= 0) {
            if ($this->processLibraryDataUpdate($patron, $values)) {
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            }
            $view = parent::profileAction();
        }
        return $view;
    }

    /**
     * Changing transaction history
     * LOTS added here for transaction history relating to LOBININTEG-19
     *
     * @param array  $patron patron data
     * @param object $values form values
     *
     * @return bool
     */
    protected function processLibraryDataUpdate($patron, $values)
    {
        // Connect to the ILS:
        $catalog = $this->getILS();

        $success = true;
        if (isset($values->profile_email)) {
            $validator = new \Laminas\Validator\EmailAddress();
            if ($validator->isValid($values->profile_email)
                && $catalog->checkFunction('updateEmail', compact('patron'))
            ) {
                // Update email
                $result = $catalog->updateEmail($patron, $values->profile_email);
                if (!$result['success']) {
                    $this->flashMessenger()->addErrorMessage($result['status']);
                    $success = false;
                }
            }
        }
        // Update phone
        if (isset($values->profile_tel)
            && $catalog->checkFunction('updatePhone', compact('patron'))
        ) {
            $result = $catalog->updatePhone($patron, $values->profile_tel);
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update Mobile Number
        if (isset($values->profile_mobile_number)
            && $catalog->checkFunction('updateMobileNumber', compact('patron'))
        ) {
            $result = $catalog->updateMobileNumber(
                $patron,
                $values->profile_mobile_number
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update SMS Number
        if (isset($values->profile_sms_number)
            && $catalog->checkFunction('updateSmsNumber', compact('patron'))
        ) {
            $result = $catalog->updateSmsNumber(
                $patron,
                $values->profile_sms_number
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        // Update checkout history state
        $updateState = $catalog
            ->checkFunction('updateTransactionHistoryState', compact('patron'));
        if (isset($values->loan_history) && $updateState) {
            $result = $catalog->updateTransactionHistoryState(
                $patron,
                $values->loan_history
            );
            if (!$result['success']) {
                $this->flashMessenger()->addErrorMessage($result['status']);
                $success = false;
            }
        }
        return $success;
    }

    public function verifyEmailAction()
    {
        // If we have a submitted form
        if ($hash = $this->params()->fromQuery('hash')) {
            $hashtime = $this->getHashAge($hash);
            $config = $this->getConfig();
            // Check if hash is expired
            $hashLifetime = $config->Authentication->recover_hash_lifetime
                ?? 1209600; // Two weeks
            if (time() - $hashtime > $hashLifetime) {
                $this->flashMessenger()
                    ->addMessage('recovery_expired_hash', 'error');
                return $this->forwardTo('MyResearch', 'Login');
            } else {
                $table = $this->getTable('User');
                $user = $table->getByVerifyHash($hash);
                // If the hash is valid, store validation in DB and forward to login
                if (null != $user) {
                    // Apply pending email address change, if applicable:
                    if (!empty($user->pending_email)) {
                        $user->updateEmail($user->pending_email, true);
                        $this->set_user_email($user->pending_email,$user->cat_id); 
                        $user->pending_email = '';
                    }
                    $user->saveEmailVerified();
                    #$this->set_user_email("busig@js.se",$user->cat_id);

                    $this->flashMessenger()->addMessage('verification_done', 'info');
                    return $this->redirect()->toRoute('myresearch-userlogin');
                }
            }
        }
        $this->flashMessenger()->addMessage('recovery_invalid_hash', 'error');
        return $this->redirect()->toRoute('myresearch-userlogin');
    }

    protected function set_user_email($email, $userid) {
        $this->koha_rest_config = $this->getConfig('KohaRest');
        $this->oath_token=$this->getOAuth2Token(false);
        
        $data = array();
        $ret_http = "";
        if (empty($email) != true) {
            $data = array(
                "email" => $email,
            );
            
            $jsonData = json_encode($data);

            $ret_http = $this->json_http("PATCH","/contrib/kohasuomi/patrons/".$userid, $jsonData);
        }        
        return;

        // PATCH {{baseUrl}}/contrib/kohasuomi/patrons/{{patronsId}} HTTP/1.1
        // Authorization: Bearer {{token}}
        // Content-Type: "application/json"

        // {
        //     "email":"js@js.se"
        // }
    }

    public function json_http($method,$api,$postData = null){
        $baseUrl = $this->koha_rest_config->Catalog->host . '/v1';
        $url = $baseUrl . $api;
        $client = $this->httpService->createClient($url);

        // Set headers
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Authorization', $this->oath_token)
            ->addHeaderLine('Content-Type', 'application/json');

        $client->getRequest()->setAllowCustomMethods(true);
        // Set method POST
        $client->setMethod($method);

        // Set post data
        $client->getRequest()->setContent($postData);

        // Send request to the server
        $response = $client->send();

        // Get the response body/JSON
        return $response->getBody();
    }

    protected function getOAuth2Token()
    {
        $baseUrl = $this->koha_rest_config->Catalog->host . '/v1';
        $clientId = $this->koha_rest_config->Catalog->clientId;
        $clientSecret = $this->koha_rest_config->Catalog->clientSecret;
        $client_credentials = $this->koha_rest_config->Catalog->grantType ?? 'client_credentials';
        $tokenUrl = $baseUrl . '/oauth/token';

        try {
            $token = $this->getNewOAuth2Token(
                $tokenUrl,
                $clientId,
                $clientSecret,
                $client_credentials
            );
        } catch (AuthTokenException $exception) {
            throw new ILSException(
                'Problem with Koha REST API: ' . $exception->getMessage()
            );
        }
        return $token->getHeaderValue();
    } 
    public function logoutAction()
    {
        $config = $this->getConfig();
        if (!empty($config->Site->logOutRoute)) {
            $logoutTarget = $this->getServerUrl($config->Site->logOutRoute);
        } else {

            setrawcookie("currentVufindUser", "", 0, "/");
            setrawcookie("currentVufindUserFirstName", "", 0, "/");
            setrawcookie("currentVufindUserLastName", "", 0, "/");
            setrawcookie("currentVufindUserHoldLibrary", "", 0, "/");
            setcookie("currentVufindUserFirstName", "", 0, "/");
            setcookie("currentVufindUserLastName", "", 0, "/");
            setcookie("currentVufindUserHoldLibrary", "", 0, "/");
            

            $logoutTarget = $this->getRequest()->getServer()->get('HTTP_REFERER');
            if (empty($logoutTarget)) {
                $logoutTarget = $this->getServerUrl('home');
            }


            // If there is an auth_method parameter in the query, we should strip
            // it out. Otherwise, the user may get stuck in an infinite loop of
            // logging out and getting logged back in when using environment-based
            // authentication methods like Shibboleth.
            $logoutTarget = preg_replace(
                '/([?&])auth_method=[^&]*&?/',
                '$1',
                $logoutTarget
            );
            $logoutTarget = rtrim($logoutTarget, '?');

            // Another special case: if logging out will send the user back to
            // the MyResearch home action, instead send them all the way to
            // VuFind home. Otherwise, they might get logged back in again,
            // which is confusing. Even in the best scenario, they'll just end
            // up on a login screen, which is not helpful.
            if ($logoutTarget == $this->getServerUrl('myresearch-home')) {
                $logoutTarget = $this->getServerUrl('home');
            }
        }

        return $this->redirect()
            ->toUrl($this->getAuthManager()->logout($logoutTarget));
    }

    /**
     * Prepare and direct the home page where it needs to go
     *
     * @return mixed
     */
    public function homeAction()
    {
        // Process login request, if necessary (either because a form has been
        // submitted or because we're using an external login provider):
        if ($this->params()->fromPost('processLogin')
            || $this->getSessionInitiator()
            || $this->params()->fromPost('auth_method')
            || $this->params()->fromQuery('auth_method')
        ) {
            try {
                if (!$this->getAuthManager()->isLoggedIn()) {
                    $this->getAuthManager()->login($this->getRequest());
                    // Return early to avoid unnecessary processing if we are being
                    // called from login lightbox and don't have a followup action.
                    if ($this->getAuthManager()->isLoggedIn()) {
                        $user = $this->getAuthManager()->isLoggedIn();
                        setrawcookie("currentVufindUserHoldLibrary", $user["home_library"], 0, "/");
                        setrawcookie("currentVufindUser", $user["username"], 0, "/");
                        setcookie("currentVufindUserFirstName", $user["firstname"], 0, "/");
                        setcookie("currentVufindUserLastName", $user["lastname"], 0, "/");                        
                    }

                    if ($this->params()->fromPost('processLogin')
                        && $this->inLightbox()
                        && empty($this->getFollowupUrl())
                    ) {
                        return $this->getRefreshResponse();
                    }
                }
            } catch (AuthException $e) {
                $this->processAuthenticationException($e);
            }
        }

        // Not logged in?  Force user to log in:
        if (!$this->getAuthManager()->isLoggedIn()) {
            // Allow bypassing of post-login redirect
            if ($this->params()->fromQuery('redirect', true)) {
                $this->setFollowupUrlToReferer();
            }
            return $this->forwardTo('MyResearch', 'Login');
        }
        // Logged in?  Forward user to followup action
        // or default action (if no followup provided):
        if ($url = $this->getFollowupUrl()) {
            $this->clearFollowupUrl();
            // If a user clicks on the "Your Account" link, we want to be sure
            // they get to their account rather than being redirected to an old
            // followup URL. We'll use a redirect=0 GET flag to indicate this:
            if ($this->params()->fromQuery('redirect', true)) {
                return $this->redirect()->toUrl($url);
            }
        }

        $config = $this->getConfig();
        $page = $config->Site->defaultAccountPage ?? 'Favorites';

        // Default to search history if favorites are disabled:
        if ($page == 'Favorites' && !$this->listsEnabled()) {
            return $this->forwardTo('Search', 'History');
        }
        return $this->forwardTo('MyResearch', $page);
    }
}

