<?php

/**
 * BankID data kept in the user's session.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * Keeps the pending login attempt (Start -> Callback) and the verified
 * personnummer (Callback -> BankID auth) in the session. Both are one-time
 * values with a short lifetime.
 */
class SessionStore
{
    public const REQUEST_TTL = 600;   // seconds the user may spend at the provider
    public const VERIFIED_TTL = 120;  // seconds between Callback and login

    /**
     * Session container (Laminas\Session\Container in production).
     *
     * @var \ArrayAccess
     */
    protected $session;

    /**
     * Clock
     *
     * @var callable
     */
    protected $now;

    /**
     * Constructor
     *
     * @param \ArrayAccess  $session Session container
     * @param callable|null $now     Returns current Unix time (tests)
     */
    public function __construct(\ArrayAccess $session, ?callable $now = null)
    {
        $this->session = $session;
        $this->now = $now ?? 'time';
    }

    /**
     * Remember a new login attempt (replaces any previous one).
     *
     * @param AuthRequest $request Attempt
     *
     * @return void
     */
    public function saveRequest(AuthRequest $request): void
    {
        $this->session['request'] = $request->toArray();
    }

    /**
     * Take the login attempt matching $state (one-time).
     *
     * @param string $state State from the provider response
     *
     * @throws BankIdException
     * @return AuthRequest
     */
    public function takeRequest(string $state): AuthRequest
    {
        $data = $this->session['request'] ?? null;
        unset($this->session['request']);
        if (!is_array($data)) {
            throw new BankIdException(
                BankIdException::SESSION_EXPIRED,
                'no pending BankID request in session'
            );
        }
        $request = AuthRequest::fromArray($data);
        if ('' === $state || !hash_equals($request->getState(), $state)) {
            throw new BankIdException(
                BankIdException::SESSION_EXPIRED,
                'state mismatch'
            );
        }
        if (($this->now)() - $request->getCreatedAt() > self::REQUEST_TTL) {
            throw new BankIdException(
                BankIdException::SESSION_EXPIRED,
                'BankID request expired'
            );
        }
        return $request;
    }

    /**
     * Remember a personnummer verified by the provider.
     *
     * @param Personnummer $personnummer Verified number
     *
     * @return void
     */
    public function saveVerified(Personnummer $personnummer): void
    {
        $this->session['verified'] = [
            'personnummer' => $personnummer->format(Personnummer::FORMAT_12),
            'createdAt' => ($this->now)(),
        ];
    }

    /**
     * Take the verified personnummer (one-time).
     *
     * @throws BankIdException
     * @return Personnummer
     */
    public function takeVerified(): Personnummer
    {
        $data = $this->session['verified'] ?? null;
        unset($this->session['verified']);
        if (!is_array($data)
            || ($this->now)() - (int)($data['createdAt'] ?? 0) > self::VERIFIED_TTL
        ) {
            throw new BankIdException(
                BankIdException::SESSION_EXPIRED,
                'no fresh verified personnummer in session'
            );
        }
        return Personnummer::fromString((string)$data['personnummer']);
    }
}
