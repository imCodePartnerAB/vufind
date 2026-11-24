<?php

/**
 * BankID via an OpenID Connect e-ID broker.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * OpenID Connect authorization code flow with PKCE, confidential client.
 *
 * The ID token is received directly from the token endpoint over TLS, so per
 * OpenID Connect Core 1.0 section 3.1.3.7 (6) TLS server validation is used
 * in place of checking the token signature. iss, aud, azp, exp and nonce are
 * validated. This avoids a JWT/JWKS library dependency in VuFind.
 */
class OidcProvider implements ProviderInterface
{
    /**
     * Allowed clock skew in seconds.
     */
    public const LEEWAY = 60;

    /**
     * HTTP timeout in seconds.
     */
    public const TIMEOUT = 15;

    /**
     * Settings
     *
     * @var BankIdConfig
     */
    protected $config;

    /**
     * HTTP service
     *
     * @var \VuFindHttp\HttpServiceInterface|null
     */
    protected $httpService;

    /**
     * Discovery document (lazy).
     *
     * @var array|null
     */
    protected $discovery = null;

    /**
     * Constructor
     *
     * @param BankIdConfig                          $config      Settings
     * @param \VuFindHttp\HttpServiceInterface|null $httpService HTTP service
     */
    public function __construct(BankIdConfig $config, $httpService = null)
    {
        $this->config = $config;
        $this->httpService = $httpService;
    }

    /**
     * URL to send the user to.
     *
     * @param AuthRequest $request Login attempt
     *
     * @throws BankIdException
     * @return string
     */
    public function getAuthorizationUrl(AuthRequest $request): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->config->getClientId(),
            'redirect_uri' => $request->getRedirectUri(),
            'scope' => $this->config->getScope(),
            'state' => $request->getState(),
            'nonce' => $request->getNonce(),
            'code_challenge' => $request->getCodeChallenge(),
            'code_challenge_method' => 'S256',
        ];
        if ('' !== $this->config->getAcrValues()) {
            $params['acr_values'] = $this->config->getAcrValues();
        }
        $endpoint = $this->getEndpoint('authorization_endpoint');
        $glue = false === strpos($endpoint, '?') ? '?' : '&';
        return $endpoint . $glue . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the code and return the personnummer from the ID token (or the
     * userinfo endpoint when the ID token does not carry the claim).
     *
     * @param string      $code    Authorization code
     * @param AuthRequest $request Login attempt
     *
     * @throws BankIdException
     * @return Personnummer
     */
    public function fetchPersonnummer(string $code, AuthRequest $request): Personnummer
    {
        if ('' === $code) {
            throw new BankIdException(BankIdException::FAILED, 'callback without code');
        }

        $tokens = $this->postTokenRequest($code, $request);
        if (empty($tokens['id_token']) || !is_string($tokens['id_token'])) {
            throw new BankIdException(BankIdException::FAILED, 'token response without id_token');
        }
        $claims = $this->validateIdToken($tokens['id_token'], $request->getNonce());

        $claim = $this->config->getPersonnummerClaim();
        $value = $claims[$claim] ?? null;
        if (null === $value && !empty($tokens['access_token'])) {
            $userinfo = $this->getUserinfo((string)$tokens['access_token']);
            if (($userinfo['sub'] ?? null) !== ($claims['sub'] ?? null)) {
                throw new BankIdException(BankIdException::FAILED, 'userinfo sub does not match id_token');
            }
            $value = $userinfo[$claim] ?? null;
        }
        if (!is_string($value) || '' === $value) {
            throw new BankIdException(
                BankIdException::FAILED,
                "claim '$claim' missing in id_token and userinfo"
            );
        }

        try {
            return Personnummer::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new BankIdException(
                BankIdException::FAILED,
                "claim '$claim' is not a valid personnummer: " . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Call the token endpoint.
     *
     * @param string      $code    Authorization code
     * @param AuthRequest $request Login attempt
     *
     * @throws BankIdException
     * @return array
     */
    protected function postTokenRequest(string $code, AuthRequest $request): array
    {
        $headers = ['Accept' => 'application/json'];
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $request->getRedirectUri(),
            'code_verifier' => $request->getCodeVerifier(),
        ];
        if (BankIdConfig::TOKEN_AUTH_POST === $this->config->getTokenAuthMethod()) {
            $form['client_id'] = $this->config->getClientId();
            $form['client_secret'] = $this->config->getClientSecret();
        } else {
            $headers['Authorization'] = 'Basic ' . base64_encode(
                rawurlencode($this->config->getClientId()) . ':'
                . rawurlencode($this->config->getClientSecret())
            );
        }
        [$status, $body] = $this->httpRequest(
            'POST',
            $this->getEndpoint('token_endpoint'),
            $headers,
            $form
        );
        $data = json_decode($body, true);
        if (200 !== $status || !is_array($data)) {
            $error = is_array($data) ? ($data['error'] ?? '') : '';
            throw new BankIdException(
                BankIdException::FAILED,
                "token endpoint returned HTTP $status $error"
            );
        }
        return $data;
    }

    /**
     * Decode the ID token payload and validate its claims.
     *
     * @param string $idToken JWT
     * @param string $nonce   Expected nonce
     *
     * @throws BankIdException
     * @return array Claims
     */
    protected function validateIdToken(string $idToken, string $nonce): array
    {
        $parts = explode('.', $idToken);
        $claims = 3 === count($parts) ? json_decode($this->base64UrlDecode($parts[1]), true) : null;
        if (!is_array($claims)) {
            throw new BankIdException(BankIdException::FAILED, 'malformed id_token');
        }

        $clientId = $this->config->getClientId();
        $audience = (array)($claims['aud'] ?? []);
        $now = time();
        $problems = [];
        if (($claims['iss'] ?? null) !== $this->getEndpoint('issuer')) {
            $problems[] = 'iss';
        }
        if (!in_array($clientId, $audience, true)) {
            $problems[] = 'aud';
        }
        if (count($audience) > 1 && ($claims['azp'] ?? null) !== $clientId) {
            $problems[] = 'azp';
        }
        if (!isset($claims['exp']) || (int)$claims['exp'] < $now - self::LEEWAY) {
            $problems[] = 'exp';
        }
        if (isset($claims['iat']) && (int)$claims['iat'] > $now + self::LEEWAY) {
            $problems[] = 'iat';
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            $problems[] = 'nonce';
        }
        if ($problems) {
            throw new BankIdException(
                BankIdException::FAILED,
                'id_token validation failed: ' . implode(', ', $problems)
            );
        }
        return $claims;
    }

    /**
     * Call the userinfo endpoint.
     *
     * @param string $accessToken Access token
     *
     * @throws BankIdException
     * @return array
     */
    protected function getUserinfo(string $accessToken): array
    {
        $endpoint = $this->getDiscovery()['userinfo_endpoint'] ?? '';
        if ('' === $endpoint) {
            return [];
        }
        [$status, $body] = $this->httpRequest(
            'GET',
            $endpoint,
            ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json']
        );
        $data = json_decode($body, true);
        if (200 !== $status || !is_array($data)) {
            throw new BankIdException(BankIdException::FAILED, "userinfo returned HTTP $status");
        }
        return $data;
    }

    /**
     * Required value from the discovery document.
     *
     * @param string $key Key
     *
     * @throws BankIdException
     * @return string
     */
    protected function getEndpoint(string $key): string
    {
        $value = $this->getDiscovery()[$key] ?? '';
        if (!is_string($value) || '' === $value) {
            throw new BankIdException(
                BankIdException::NOT_AVAILABLE,
                "OpenID configuration has no $key"
            );
        }
        return $value;
    }

    /**
     * Discovery document of bankid_provider_url.
     *
     * @throws BankIdException
     * @return array
     */
    protected function getDiscovery(): array
    {
        if (null === $this->discovery) {
            $url = $this->config->getProviderUrl() . '/.well-known/openid-configuration';
            [$status, $body] = $this->httpRequest('GET', $url, ['Accept' => 'application/json']);
            $data = json_decode($body, true);
            if (200 !== $status || !is_array($data)) {
                throw new BankIdException(
                    BankIdException::NOT_AVAILABLE,
                    "OpenID discovery $url returned HTTP $status"
                );
            }
            $this->discovery = $data;
        }
        return $this->discovery;
    }

    /**
     * Perform an HTTP request.
     *
     * @param string $method  GET or POST
     * @param string $url     URL
     * @param array  $headers Headers
     * @param array  $form    Form fields for POST
     *
     * @throws BankIdException
     * @return array [status code, body]
     */
    protected function httpRequest(
        string $method,
        string $url,
        array $headers = [],
        array $form = []
    ): array {
        if (null === $this->httpService) {
            throw new BankIdException(BankIdException::NOT_AVAILABLE, 'no HTTP service');
        }
        try {
            $client = $this->httpService->createClient($url, $method, self::TIMEOUT);
            $client->setHeaders($headers);
            if ($form) {
                $client->setParameterPost($form);
            }
            $response = $client->send();
        } catch (\Exception $e) {
            throw new BankIdException(
                BankIdException::NOT_AVAILABLE,
                "$method $url failed: " . $e->getMessage(),
                $e
            );
        }
        return [$response->getStatusCode(), $response->getBody()];
    }

    /**
     * Base64url decode.
     *
     * @param string $value Encoded
     *
     * @return string
     */
    protected function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        return (string)base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
