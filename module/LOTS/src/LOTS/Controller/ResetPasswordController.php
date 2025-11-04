<?php
/**
 * Controller for resetting password with token
 *
 * /vufind/ResetPassword
 */

namespace LOTS\Controller;

use VuFind\Exception\ILS as ILSException;

class ResetPasswordController extends \VuFind\Controller\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\ILS\Driver\OAuth2TokenTrait;

    protected $koha_rest_config = null;
    protected $oauth_token = null;

    /**
     * Main action - validate token and show form or process password change
     *
     * @return \Laminas\View\Model\ViewModel|\Laminas\Http\Response
     */
    public function homeAction()
    {
	$token = $this->params()->fromQuery('token') ?? $this->params()->fromPost('token');
	error_log("DEBUG: Received token from URL: " . var_export($token, true));
	error_log("DEBUG: Token length: " . strlen($token));
        $message = '';
        $messageType = 'info';
        $tokenValid = false;
        $tokenData = null;
        
        if (empty($token)) {
            $message = $this->translate('password_reset_invalid_token');
            $messageType = 'error';
        } else {
            // Validate token
	    $tokenTable = $this->getTable('PasswordResetToken');
            $tokenData = $tokenTable->getValidToken($token);
	    error_log("DEBUG: Token data from DB: " . var_export($tokenData, true));
            
            if (!$tokenData) {
                $message = $this->translate('password_reset_token_expired');
                $messageType = 'error';
            } else {
                $tokenValid = true;
                
                // Handle form submission
                if ($this->getRequest()->isPost()) {
                    $newPin = $this->params()->fromPost('new_pin');
                    $confirmPin = $this->params()->fromPost('confirm_pin');
                    
                    // Validate PIN
                    $validation = $this->validatePin($newPin, $confirmPin);
                    
                    if ($validation['valid']) {
                        try {
                            // Update password in Koha
                            $this->updatePatronPassword($tokenData['user_id'], $newPin);
                            
                            // Mark token as used
                            $tokenTable->markAsUsed($token);
                            
                            $message = $this->translate('password_reset_success');
                            $messageType = 'success';
                            $tokenValid = false; // Hide form
                            
                            // Redirect to login after 3 seconds
                            $this->layout()->setVariable('redirectUrl', '/vufind/MyResearch/Home');
                            $this->layout()->setVariable('redirectDelay', 3000);
                        } catch (\Exception $e) {
                            error_log('Password update error: ' . $e->getMessage());
                            $message = $this->translate('password_reset_update_error');
                            $messageType = 'error';
                        }
                    } else {
                        $message = $validation['error'];
                        $messageType = 'error';
                    }
                }
            }
        }
        
        return $this->createViewModel([
            'message' => $message,
            'messageType' => $messageType,
            'tokenValid' => $tokenValid,
            'token' => $token
        ]);
    }

    /**
     * Validate PIN format and match
     *
     * @param ?string $newPin     New PIN
     * @param ?string $confirmPin Confirmation PIN
     *
     * @return array ['valid' => bool, 'error' => string]
     */
    protected function validatePin(?string $newPin, ?string $confirmPin): array
    {
        if (empty($newPin) || empty($confirmPin)) {
            return [
                'valid' => false,
                'error' => $this->translate('password_reset_pin_required')
            ];
        }
        
        if ($newPin !== $confirmPin) {
            return [
                'valid' => false,
                'error' => $this->translate('password_reset_pin_mismatch')
            ];
        }
        
        // PIN must be exactly 4 digits
        if (!preg_match('/^\d{4}$/', $newPin)) {
            return [
                'valid' => false,
                'error' => $this->translate('password_reset_pin_format')
            ];
        }
        
        return ['valid' => true, 'error' => ''];
    }

    /**
     * Update patron password in Koha
     *
     * @param string $patronId Patron ID
     * @param string $newPin   New PIN
     *
     * @return void
     * @throws \Exception
     */

protected function updatePatronPassword(string $patronId, string $newPin): void
{
    $this->koha_rest_config = $this->getConfig('KohaRest');
    $this->oauth_token = $this->getOAuth2Token();
    
    error_log("DEBUG: Updating password for patron: " . $patronId);
    
    // Use correct endpoint: POST /patrons/{id}/password
    $data = [
        'password' => $newPin,
        'password_2' => $newPin
    ];
    
    $response = $this->json_http("POST", "/patrons/$patronId/password", json_encode($data));
    
    error_log("Koha password update response: " . $response);
    
    // Check if response contains error
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        throw new \Exception('Koha API error: ' . $result['error']);
    }
}

    /**
     * Get OAuth2 token for Koha API
     *
     * @return string Token header value
     */
    protected function getOAuth2Token(): string
    {
        $baseUrl = $this->koha_rest_config->Catalog->host . '/v1';
        $clientId = $this->koha_rest_config->Catalog->clientId;
        $clientSecret = $this->koha_rest_config->Catalog->clientSecret;
        $grantType = $this->koha_rest_config->Catalog->grantType ?? 'client_credentials';
        $tokenUrl = $baseUrl . '/oauth/token';

        try {
            $token = $this->getNewOAuth2Token(
                $tokenUrl,
                $clientId,
                $clientSecret,
                $grantType
            );
        } catch (\Exception $exception) {
            throw new ILSException(
                'Problem with Koha REST API: ' . $exception->getMessage()
            );
        }
        return $token->getHeaderValue();
    }

public function json_http($method, $api, $postData = null)
{
    $baseUrl = $this->koha_rest_config->Catalog->host . '/v1';
    $url = $baseUrl . $api;
    $client = $this->httpService->createClient($url);

    // Set headers
    $client->getRequest()->getHeaders()
        ->addHeaderLine('Authorization', $this->oauth_token)
        ->addHeaderLine('Content-Type', 'application/json');

    $client->getRequest()->setAllowCustomMethods(true);
    // Set method
    $client->setMethod($method);

    // Set post data
    $client->getRequest()->setContent($postData);

    // Send request to the server
    $response = $client->send();

    // Get the response body/JSON
    return $response->getBody();
}

}
