<?php

/**
 * One BankID login attempt (state, nonce, PKCE verifier).
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * One BankID login attempt. Kept in the session between Start and Callback.
 */
final class AuthRequest
{
    /** @var string */
    private $state;
    /** @var string */
    private $nonce;
    /** @var string */
    private $codeVerifier;
    /** @var string */
    private $redirectUri;
    /** @var int */
    private $createdAt;

    /**
     * Constructor
     *
     * @param string $state        OAuth2 state
     * @param string $nonce        OIDC nonce
     * @param string $codeVerifier PKCE verifier
     * @param string $redirectUri  Callback URL
     * @param int    $createdAt    Unix time
     */
    public function __construct(
        string $state,
        string $nonce,
        string $codeVerifier,
        string $redirectUri,
        int $createdAt
    ) {
        $this->state = $state;
        $this->nonce = $nonce;
        $this->codeVerifier = $codeVerifier;
        $this->redirectUri = $redirectUri;
        $this->createdAt = $createdAt;
    }

    /**
     * New attempt with random secrets.
     *
     * @param string $redirectUri Callback URL
     *
     * @return self
     */
    public static function create(string $redirectUri): self
    {
        return new self(
            self::random(),
            self::random(),
            self::random(),
            $redirectUri,
            time()
        );
    }

    /**
     * Restore from session data.
     *
     * @param array $data Data from toArray()
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['state'] ?? ''),
            (string)($data['nonce'] ?? ''),
            (string)($data['codeVerifier'] ?? ''),
            (string)($data['redirectUri'] ?? ''),
            (int)($data['createdAt'] ?? 0)
        );
    }

    /**
     * Session data (scalars only).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'nonce' => $this->nonce,
            'codeVerifier' => $this->codeVerifier,
            'redirectUri' => $this->redirectUri,
            'createdAt' => $this->createdAt,
        ];
    }

    /** @return string */
    public function getState(): string
    {
        return $this->state;
    }

    /** @return string */
    public function getNonce(): string
    {
        return $this->nonce;
    }

    /** @return string */
    public function getCodeVerifier(): string
    {
        return $this->codeVerifier;
    }

    /** @return string PKCE S256 challenge */
    public function getCodeChallenge(): string
    {
        return self::base64Url(hash('sha256', $this->codeVerifier, true));
    }

    /** @return string */
    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    /** @return int */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * 256-bit random, URL safe.
     *
     * @return string
     */
    private static function random(): string
    {
        return self::base64Url(random_bytes(32));
    }

    /**
     * Base64url without padding.
     *
     * @param string $bytes Bytes
     *
     * @return string
     */
    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
