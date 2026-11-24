<?php

/**
 * LOTS login method ([Authentication] login_method in LOTS.ini).
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  Authentication
 */

namespace LOTS\Auth;

use Laminas\Config\Config;

/**
 * Value object for the configured login method and its mapping to VuFind auth.
 *
 *   password - username/password (PIN) only; config.ini is left untouched
 *   bankid   - BankID only
 *   both     - user chooses between password and BankID (VuFind ChoiceAuth)
 */
final class LoginMethod
{
    public const PASSWORD = 'password';
    public const BANKID = 'bankid';
    public const BOTH = 'both';

    /**
     * Name of the BankID auth plugin (also used as ChoiceAuth strategy name).
     */
    public const BANKID_AUTH = 'BankID';

    /**
     * Login method => VuFind [Authentication] method (null = keep config.ini).
     */
    private const AUTH_METHODS = [
        self::PASSWORD => null,
        self::BANKID => self::BANKID_AUTH,
        self::BOTH => 'ChoiceAuth',
    ];

    /**
     * @var string
     */
    private $value;

    /**
     * Constructor
     *
     * @param string $value One of the class constants
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Build from LOTS.ini. A missing or empty setting means "password", i.e.
     * the behaviour before BankID existed.
     *
     * @param Config $lots LOTS.ini
     *
     * @throws \InvalidArgumentException on an unknown value
     * @return self
     */
    public static function fromLotsConfig(Config $lots): self
    {
        $raw = (string)($lots->Authentication->login_method ?? '');
        $value = strtolower(trim($raw));
        if ('' === $value) {
            return new self(self::PASSWORD);
        }
        if (!array_key_exists($value, self::AUTH_METHODS)) {
            throw new \InvalidArgumentException(
                "Invalid [Authentication] login_method '$raw' in LOTS.ini;"
                . ' expected password, bankid or both'
            );
        }
        return new self($value);
    }

    /**
     * Like fromLotsConfig(), but an invalid value is logged and treated as
     * "password" so a typo in LOTS.ini never takes login down.
     *
     * @param Config $lots LOTS.ini
     *
     * @return self
     */
    public static function fromLotsConfigOrDefault(Config $lots): self
    {
        try {
            return self::fromLotsConfig($lots);
        } catch (\InvalidArgumentException $e) {
            error_log('LOTS: ' . $e->getMessage() . "; falling back to 'password'");
            return new self(self::PASSWORD);
        }
    }

    /**
     * Configured value (password, bankid or both).
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Is BankID login offered?
     *
     * @return bool
     */
    public function usesBankId(): bool
    {
        return self::PASSWORD !== $this->value;
    }

    /**
     * VuFind [Authentication] method to use, or null to keep config.ini as is.
     *
     * @return string|null
     */
    public function getAuthMethod(): ?string
    {
        return self::AUTH_METHODS[$this->value];
    }

    /**
     * VuFind [ChoiceAuth] choice_order to use, or null when not applicable.
     *
     * @return string|null
     */
    public function getChoiceOrder(): ?string
    {
        return self::BOTH === $this->value ? 'ILS,' . self::BANKID_AUTH : null;
    }
}
