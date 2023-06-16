<?php

namespace LOTS\Controller;

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
            if ($this->processLibraryDataUpdate($patron, $values, $user)) {
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
}

