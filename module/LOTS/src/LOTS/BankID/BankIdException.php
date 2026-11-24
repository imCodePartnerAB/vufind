<?php

/**
 * BankID login failure.
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * BankID login failure.
 *
 * getMessage() is a translation key shown to the user; getDetail() is the
 * technical reason for the log (never contains a full personnummer).
 */
class BankIdException extends \RuntimeException
{
    public const NOT_AVAILABLE = 'bankid_not_available';
    public const CANCELLED = 'bankid_cancelled';
    public const FAILED = 'bankid_failed';
    public const SESSION_EXPIRED = 'bankid_session_expired';
    public const NO_PATRON = 'bankid_no_patron';
    public const AMBIGUOUS_PATRON = 'bankid_ambiguous_patron';

    /**
     * Technical detail for the log.
     *
     * @var string
     */
    private $detail;

    /**
     * Constructor
     *
     * @param string          $messageKey Translation key for the user
     * @param string          $detail     Technical detail for the log
     * @param \Throwable|null $previous   Previous exception
     */
    public function __construct(
        string $messageKey,
        string $detail = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($messageKey, 0, $previous);
        $this->detail = $detail;
    }

    /**
     * Technical detail for the log.
     *
     * @return string
     */
    public function getDetail(): string
    {
        return $this->detail;
    }
}
