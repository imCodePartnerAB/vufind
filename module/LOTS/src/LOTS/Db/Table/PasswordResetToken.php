<?php
/**
 * Table Definition for lots_password_reset_tokens
 *
 * PHP version 7
 *
 * @category VuFind
 * @package  Db_Table
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace LOTS\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;

/**
 * Table Definition for lots_password_reset_tokens
 *
 * @category VuFind
 * @package  Db_Table
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PasswordResetToken extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'lots_password_reset_tokens'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Create a new password reset token
     *
     * @param string $userId User ID (patron_id from Koha)
     * @param string $email  User email
     *
     * @return string Generated token
     */
    public function createToken(string $userId, string $email): string
    {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        
        // Calculate expiration (2 days from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+2 days'));
        
        // Insert into database
        $this->insert([
            'user_id' => $userId,
            'token' => $token,
            'email' => $email,
            'expires_at' => $expiresAt,
            'used' => 0
        ]);
        
        return $token;
    }

    /**
     * Get token data if valid (not expired and not used)
     *
     * @param string $token Token to validate
     *
     * @return ?array Token data or null if invalid
     */
    public function getValidToken(string $token): ?array
    {
        $select = $this->getSql()->select();
        $select->where([
            'token' => $token,
            'used' => 0
        ]);
        $select->where->lessThanOrEqualTo('created_at', date('Y-m-d H:i:s'));
        $select->where->greaterThan('expires_at', date('Y-m-d H:i:s'));
        
        $result = $this->selectWith($select)->current();
        
        if (!$result) {
            return null;
        }
        
        return [
            'id' => $result->id,
            'user_id' => $result->user_id,
            'email' => $result->email,
            'token' => $result->token,
            'created_at' => $result->created_at,
            'expires_at' => $result->expires_at
        ];
    }

    /**
     * Mark token as used
     *
     * @param string $token Token to mark as used
     *
     * @return bool Success
     */
    public function markAsUsed(string $token): bool
    {
        return $this->update(
            ['used' => 1],
            ['token' => $token]
        ) > 0;
    }

    /**
     * Delete expired tokens (cleanup)
     *
     * @return int Number of deleted tokens
     */
    public function deleteExpired(): int
    {
        return $this->delete([
            'expires_at < ?' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Delete all tokens for a specific user
     *
     * @param string $userId User ID
     *
     * @return int Number of deleted tokens
     */
    public function deleteByUserId(string $userId): int
    {
        return $this->delete(['user_id' => $userId]);
    }
}
