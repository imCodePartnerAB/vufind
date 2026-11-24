<?php

/**
 * BankID settings from LOTS.ini [Authentication].
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

use Laminas\Config\Config;

/**
 * BankID settings from LOTS.ini [Authentication], validated once.
 */
final class BankIdConfig
{
    public const PROVIDER_OIDC = 'oidc';
    public const PROVIDER_SIMULATED = 'simulated';

    public const TOKEN_AUTH_BASIC = 'client_secret_basic';
    public const TOKEN_AUTH_POST = 'client_secret_post';

    /** @var string */
    private $provider;
    /** @var string */
    private $providerUrl;
    /** @var string */
    private $returnUrl;
    /** @var string */
    private $clientId;
    /** @var string */
    private $clientSecret;
    /** @var string */
    private $scope;
    /** @var string */
    private $acrValues;
    /** @var string */
    private $tokenAuthMethod;
    /** @var string */
    private $personnummerClaim;
    /** @var string */
    private $patronField;
    /** @var string */
    private $personnummerFormat;
    /** @var Personnummer[] */
    private $simulatedPersonnummer;

    /**
     * Constructor
     *
     * @param array $s Settings
     */
    private function __construct(array $s)
    {
        foreach ($s as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Build from LOTS.ini.
     *
     * @param Config $lots LOTS.ini
     *
     * @throws BankIdException when the configuration is incomplete or invalid
     * @return self
     */
    public static function fromLotsConfig(Config $lots): self
    {
        $a = $lots->Authentication;
        $get = function (string $key, string $default = '') use ($a): string {
            return trim((string)($a->$key ?? $default));
        };
        $errors = [];

        $provider = strtolower($get('bankid_provider', self::PROVIDER_OIDC));
        if (!in_array($provider, [self::PROVIDER_OIDC, self::PROVIDER_SIMULATED], true)) {
            $errors[] = "bankid_provider must be oidc or simulated, got '$provider'";
        }

        $patronField = $get('bankid_patron_field');
        if (!preg_match('/^(cardnumber|userid|attribute:[A-Za-z0-9_-]+)$/', $patronField)) {
            $errors[] = 'bankid_patron_field must be cardnumber, userid or attribute:<code>';
        }

        $format = $get('bankid_personnummer_format', Personnummer::FORMAT_12);
        if (!in_array($format, Personnummer::FORMATS, true)) {
            $errors[] = 'bankid_personnummer_format must be one of '
                . implode(', ', Personnummer::FORMATS);
        }

        $settings = [
            'provider' => $provider,
            'providerUrl' => rtrim($get('bankid_provider_url'), '/'),
            'returnUrl' => $get('bankid_return_url'),
            'clientId' => $get('bankid_client_id'),
            'clientSecret' => $get('bankid_client_secret'),
            'scope' => $get('bankid_scope', 'openid'),
            'acrValues' => $get('bankid_acr_values'),
            'tokenAuthMethod' => $get('bankid_token_auth_method', self::TOKEN_AUTH_BASIC),
            'personnummerClaim' => $get('bankid_personnummer_claim'),
            'patronField' => $patronField,
            'personnummerFormat' => $format,
            'simulatedPersonnummer' => [],
        ];

        if (self::PROVIDER_OIDC === $provider) {
            foreach (['bankid_client_id' => 'clientId', 'bankid_client_secret' => 'clientSecret',
                'bankid_personnummer_claim' => 'personnummerClaim'] as $key => $prop
            ) {
                if ('' === $settings[$prop]) {
                    $errors[] = "$key is required";
                }
            }
            if (0 !== strpos($settings['providerUrl'], 'https://')) {
                $errors[] = 'bankid_provider_url must be an https:// URL';
            }
            // Empty return URL = derived from the route.
            if ('' !== $settings['returnUrl'] && 0 !== strpos($settings['returnUrl'], 'https://')) {
                $errors[] = 'bankid_return_url must be an https:// URL';
            }
            if (!in_array($settings['tokenAuthMethod'], [self::TOKEN_AUTH_BASIC, self::TOKEN_AUTH_POST], true)) {
                $errors[] = 'bankid_token_auth_method must be '
                    . self::TOKEN_AUTH_BASIC . ' or ' . self::TOKEN_AUTH_POST;
            }
        } else {
            foreach (explode(',', $get('bankid_simulated_personnummer')) as $raw) {
                if ('' === trim($raw)) {
                    continue;
                }
                try {
                    $settings['simulatedPersonnummer'][] = Personnummer::fromString($raw);
                } catch (\InvalidArgumentException $e) {
                    $errors[] = 'bankid_simulated_personnummer contains an invalid number';
                }
            }
            if (empty($settings['simulatedPersonnummer'])) {
                $errors[] = 'bankid_simulated_personnummer must list at least one test personnummer';
            }
        }

        if ($errors) {
            throw new BankIdException(
                BankIdException::NOT_AVAILABLE,
                'LOTS.ini [Authentication]: ' . implode('; ', $errors)
            );
        }
        return new self($settings);
    }

    /** @return string */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /** @return bool */
    public function isSimulated(): bool
    {
        return self::PROVIDER_SIMULATED === $this->provider;
    }

    /** @return string Issuer URL without trailing slash */
    public function getProviderUrl(): string
    {
        return $this->providerUrl;
    }

    /** @return string Empty = derive from route */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    /** @return string */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /** @return string */
    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /** @return string */
    public function getScope(): string
    {
        return $this->scope;
    }

    /** @return string */
    public function getAcrValues(): string
    {
        return $this->acrValues;
    }

    /** @return string client_secret_basic | client_secret_post */
    public function getTokenAuthMethod(): string
    {
        return $this->tokenAuthMethod;
    }

    /** @return string */
    public function getPersonnummerClaim(): string
    {
        return $this->personnummerClaim;
    }

    /** @return string cardnumber | userid | attribute:<code> */
    public function getPatronField(): string
    {
        return $this->patronField;
    }

    /** @return string One of Personnummer::FORMATS */
    public function getPersonnummerFormat(): string
    {
        return $this->personnummerFormat;
    }

    /** @return Personnummer[] */
    public function getSimulatedPersonnummer(): array
    {
        return $this->simulatedPersonnummer;
    }
}
