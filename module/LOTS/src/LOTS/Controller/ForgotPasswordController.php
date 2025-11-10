<?php
/**
 * Controller to help recover passwords
 *
 * /vufind/ForgotPassword
 */

namespace LOTS\Controller;

use VuFind\Exception\ILS as ILSException;

class ForgotPasswordController extends \VuFind\Controller\AbstractBase implements
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\ILS\Driver\OAuth2TokenTrait;

    protected $koha_rest_config = null;
    protected $oauth_token = null;

    /**
     * Main action - show form and process password reset request
     *
     * @return \Laminas\View\Model\ViewModel
     */

     public function homeAction()
     {
         $message = '';
         $messageType = 'info';

         // Get configured search fields for display in template
         $config = $this->getConfig('LOTS');
         $searchFields = [];

         if (isset($config->PasswordRecovery->patron_search_fields)) {
             $fieldsString = $config->PasswordRecovery->patron_search_fields;
             $searchFields = array_map('trim', explode(',', $fieldsString));
         } else {
             $searchFields = ['cardnumber'];
         }

         // Validate fields
         $validFields = ['cardnumber', 'email', 'userid'];
         $searchFields = array_intersect($searchFields, $validFields);

         // Handle form submission
         $username = $this->params()->fromPost('username');

         if (!empty($username)) {
             try {
                 // Search for patron using configured search fields
                 $patron = $this->findPatron($username);

                 if ($patron && !empty($patron['email'])) {
                     // Generate and save token
                     $tokenTable = $this->getTable('PasswordResetToken');
                     $token = $tokenTable->createToken($patron['patron_id'], $patron['email']);

                     // Send email
                     $this->sendResetEmail($patron['email'], $token);

                     // Generic message (don't reveal if user exists)
                     $message = $this->translate('password_reset_email_sent');
                     $messageType = 'success';
                 } else {
                     // Generic message (don't reveal if user exists or has no email)
                     $message = $this->translate('password_reset_email_sent');
                     $messageType = 'success';
                 }
             } catch (\Exception $e) {
                 error_log('Password reset error: ' . $e->getMessage());
                 $message = $this->translate('password_reset_error');
                 $messageType = 'error';
             }
         }

         return $this->createViewModel([
             'message' => $message,
             'messageType' => $messageType,
             'searchFields' => $searchFields
         ]);
     }


    /**
     * Find patron by searching configured fields
     *
     * @param string $searchValue Value to search for
     *
     * @return ?array Patron data or null
     */
    protected function findPatron(string $searchValue): ?array
    {
        $config = $this->getConfig('LOTS');
        $searchFields = [];

        // Get configured search fields from LOTS.ini
        if (isset($config->PasswordRecovery->patron_search_fields)) {
            $fieldsString = $config->PasswordRecovery->patron_search_fields;
            $searchFields = array_map('trim', explode(',', $fieldsString));
        } else {
            // Default to cardnumber only if not configured
            $searchFields = ['cardnumber'];
        }

        // Valid field names that Koha API supports
        $validFields = ['cardnumber', 'email', 'userid'];
        $searchFields = array_intersect($searchFields, $validFields);

        if (empty($searchFields)) {
            error_log('No valid patron search fields configured');
            return null;
        }

        // Initialize Koha connection
        $this->koha_rest_config = $this->getConfig('KohaRest');
        $this->oauth_token = $this->getOAuth2Token();

        // Try each configured field until we find a patron
        foreach ($searchFields as $field) {
            $patron = $this->searchPatronByField($field, $searchValue);
            if ($patron) {
                return $patron;
            }
        }

        return null;
    }

    /**
     * Search for patron by specific field using Koha REST API
     *
     * @param string $field Field name (cardnumber, email, or userid)
     * @param string $value Value to search for
     *
     * @return ?array Patron data or null
     */
    protected function searchPatronByField(string $field, string $value): ?array
    {
        $baseUrl = $this->koha_rest_config->Catalog->host . '/v1';
        $url = $baseUrl . '/patrons?' . $field . '=' . urlencode($value);

        $client = $this->httpService->createClient($url);
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Authorization', $this->oauth_token)
            ->addHeaderLine('Content-Type', 'application/json');

        try {
            $response = $client->send();

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode($response->getBody(), true);

            // API returns array of patrons, we need the first one
            if (empty($data) || !is_array($data) || count($data) === 0) {
                return null;
            }

            $patron = $data[0];

            return [
                'patron_id' => $patron['patron_id'] ?? null,
                'email' => $patron['email'] ?? null,
                'cardnumber' => $patron['cardnumber'] ?? null,
                'userid' => $patron['userid'] ?? null
            ];
        } catch (\Exception $e) {
            error_log("Patron search by $field failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send password reset email
     *
     * @param string $email User email
     * @param string $token Reset token
     *
     * @return void
     */
    protected function sendResetEmail(string $email, string $token): void
    {
        $config = $this->getConfig();
        $fromEmail = $config->Site->email ?? 'noreply@library.se';

        $request = $this->getRequest();
        $serverUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        $resetUrl = $serverUrl . '/vufind/ResetPassword?token=' . urlencode($token);

        $body = $this->translate('password_reset_line1') . PHP_EOL . PHP_EOL .
                $this->translate('password_reset_line2') . PHP_EOL .
                $resetUrl . PHP_EOL . PHP_EOL .
                $this->translate('password_reset_line3') . PHP_EOL . PHP_EOL .
                $this->translate('password_reset_line4');

        $mailer = $this->serviceLocator->get('VuFind\Mailer');
        $mailer->send($email, $fromEmail, $this->translate('password_reset_email_subject'), $body);
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
}
