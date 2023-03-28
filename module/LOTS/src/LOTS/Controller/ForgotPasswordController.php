<?php
/*
 * Controler to help recover passwords
 *
 * /vufind/ForgotPassword
 * 
 */

namespace LOTS\Controller;

#Needed??
#use Laminas\View\Renderer\RendererInterface;

class ForgotPasswordController extends \VuFind\Controller\AbstractBase implements
\VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    public function homeAction()
    {
        # Geting LOTS.ini file for reading config.
        $config = $this->getConfig('LOTS');
        $opacUrl = $config->PasswordRecovery->kohaOpacUrl;

        # Type of call represent the answer to give bellow.
        $typeOfCall  = 'home';
        #Get username so we can test if this is a post
        $username = $this->params()->fromPost('username');
        # resp contains the responce for a http request.
        $resp = "";
        # message store any message to send back to user.
        $message = '';
        # together with opacUrl we create this url for any local http calls.
        # none local we could give the whole url.
        $url = '';

        if ($this->params()->fromQuery('resendEmail', false)) {
            /*
             * if the querystring has resendEmail=true then this is a
             * request to resend the email. We recreeate the request
             * and sends it to the opac backend instead.
             */
            $typeOfCall = 'resend';
            $email = $this->params()->fromQuery('email');
            $username = $this->params()->fromQuery('username', false);
            $message = "Email: ".$email." username: ".$username;
            $fields = [
               'username'     => $username,
               'email'        =>$email,
               'resendEmail'  => 'true',
               'language'     => 'sv-SE',
            ];
            $url = $opacUrl."/cgi-bin/koha/opac-password-recovery.pl";
            $resp =  $this->httpPost($url, $fields, 'GET');
        } elseif (empty($username) != true) {
            /*
             * If we get a value in username, then we should be dealing
             * with a request to reset password. So we will take the
             * username value and send it to the backend koha-opac.
             */

            $typeOfCall = 'recover';
            $fields = [
                'username'      => $username,
                'sendEmail'     => 'Submit',
                'language'     => 'sv-SE',
             ];
 
            if(strpos("$username", "@") !== false){
                    $fields = [
                        'email'      => $username,
                        'sendEmail'     => 'Submit',
                        'language'     => 'sv-SE',
                    ];
            } 
            $url = $opacUrl."/cgi-bin/koha/opac-password-recovery.pl";
            $resp =  $this->httpPost($url, $fields, 'POST');
        }
        /* ELSE
         * If none of the two above is set (resendEmail and username)
         * We are dealing with a new visitor to request new password.
         *
         * We will still test the response for messages from koha-opac
         * bellow, we will preg_match to get only the message part of
         * the html response. And then we use preg_replace to rewrite
         * "known" urls to our own.
         */
        if (preg_match("/alert-warning/i",$resp)) {
            $typeOfCall = 'warning';
        }
        preg_match('/<div class="alert alert-(warning|info)">(.*?)<\/div>/s', $resp, $message);
        $message = preg_replace('/\<a.*href.*opac-password-recovery\.pl(.*)">(.*)\<\/a\>/m', '<a href="/vufind/ForgotPassword$1">'.$this->translate('SendNewEmail').'</a>', $message);
        $message = preg_replace('/\<a.*href.*opac-main\.pl(.*)">(.*)\<\/a\>/m', '<a href="/">'.$this->translate('Go to homepage').'</a>', $message);

        # We must test if the message exists or set it to nothing.
        # To not get an error
        if (isset($message[0])) {
            $message = $message[0];
        } else {
            $message = "";
        }

        # Here we sendback the variables to the viewmodel.
        # typeOfCall to determine the response template and
        # any message we got from koha-opac
        return $this->createViewModel(
            [
            'typeOfCall' => $typeOfCall,
            'message' => $message,
            ]
        );
    }
    /*
     * Simple function to make http requests with vufind
     * builtin curl client.
     */
    public function httpPost($url, $data, $type)
    {

        $client = $this->httpService->createClient($url);
        $adapter = $client->getAdapter();
        $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, false);
        $adapter->setCurlOption(CURLOPT_CUSTOMREQUEST, $type);
        $adapter->setCurlOption(CURLOPT_POSTFIELDS, http_build_query($data));
        $adapter->setCurlOption(CURLOPT_ENCODING, '');
        $adapter->setCurlOption(CURLOPT_RETURNTRANSFER, true);
        $adapter->setCurlOption(CURLOPT_COOKIE, "KohaOpacLanguage=sv-SE;a=a");
        $response = $client->send();
        return $response->getBody();
    }
}
