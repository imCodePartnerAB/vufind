<?php
/*
 * Controler to help recover passwords
 *
 * /vufind/ForgotPassword
 * 
 */
namespace LOTS\Controller;


use VuFind\Exception\ILS as ILSException;
use VuFind\Exception\AuthToken as AuthTokenException;


class SuggestionsController extends \VuFind\Controller\AbstractBase implements
\VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\ILS\Driver\OAuth2TokenTrait;

    protected $koha_rest_config;

    public function homeAction()
    {
        $this->koha_rest_config = $this->getConfig('KohaRest');
        # Geting LOTS.ini file for reading config.
        $config = $this->getConfig('LOTS');
        $anonymous = $config->Suggestions->allowAnonymous;
        
        if (!$anonymous == true) {
            $user = $this->getUser();
            if ($user == false) {
                return $this->forceLogin();
            }
        }
        
        //Get the OAuth2 token
        $token=$this->getOAuth2Token(false);

        //Get the libraries json and stort it by name
        $test_json = $this->json_http("GET","/libraries",$token);
        $libraries = json_decode($test_json);

        usort($libraries, function($a, $b) {
            return strcmp($a->name, $b->name);
        });

        $title = $this->params()->fromPost('title');
        $author = $this->params()->fromPost('author');
        $copyrightdate = $this->params()->fromPost('copyrightdate');
        $isbn = $this->params()->fromPost('isbn');
        $publishercode = $this->params()->fromPost('publishercode');
        $collectiontitle = $this->params()->fromPost('collectiontitle');
        $place = $this->params()->fromPost('place');
        $quantity = $this->params()->fromPost('quantity');
        $itemtype = $this->params()->fromPost('itemtype');
        $branchcode = $this->params()->fromPost('branchcode');
        $note = $this->params()->fromPost('note');

        $data = array();
        if (empty($title) != true) {
            $data = array(
                "title" => $title,
                "author" => $author,
                "publication_year" => $copyrightdate,
                "isbn" => $isbn,
                "publisher_code" => $publishercode,
                "collection_title" => $collectiontitle,
                "publication_place" => $place,
                "quantity" => $quantity,
                "item_type" => $itemtype,
                "library_id" => $branchcode,
                "note" => $note,
                "suggested_by" => 1111
            );
            
            $jsonData = json_encode($data);

            $ret_http = $this->json_http("POST","/suggestions", $token, $jsonData);
        }

        return $this->createViewModel(
            [
            'libraries' => $libraries,
            'title' => $this->params()->fromPost('title'),
            'author' => $this->params()->fromPost('author'),
            'copyrightdate' => $this->params()->fromPost('copyrightdate'),
            'isbn' => $this->params()->fromPost('isbn'),
            'publishercode' => $this->params()->fromPost('publishercode'),
            'collectiontitle' => $this->params()->fromPost('collectiontitle'),
            'place' => $this->params()->fromPost('place'),
            'quantity' => $this->params()->fromPost('quantity'),
            'itemtype' => $this->params()->fromPost('itemtype'),
            'branchcode' => $this->params()->fromPost('branchcode'),
            'note' => $this->params()->fromPost('note'),
            'ret_http' => $ret_http,
            'http_data' =>  json_encode($data),
            ]
        );

    }

    public function json_http($method,$api,$token,$postData = null){
        $baseUrl = $this->koha_rest_config->Catalog->host . '/v1';
        $url = $baseUrl . $api;
        $client = $this->httpService->createClient($url);

        // Set headers
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Authorization', $token)
            ->addHeaderLine('Content-Type', 'application/json');

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
