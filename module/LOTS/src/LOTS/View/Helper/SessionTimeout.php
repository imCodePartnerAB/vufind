<?php
namespace LOTS\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Auth\Manager as AuthManager;

class SessionTimeout extends AbstractHelper
{
    protected $authManager;

    public function __construct(AuthManager $authManager)
    {
        $this->authManager = $authManager;
    }

    public function __invoke()
    {
        return $this->getSessionTimeoutData();
    }

    public function getSessionTimeoutData()
    {
        $user = $this->authManager->getUserObject(); $isLoggedIn = ($user !== null);

        // Get configuration from existing location (via VuFind's config system)
        $configManager = $this->getView()->getHelperPluginManager()->get('config');
        $lotsConfig = $configManager->get('LOTS');
        $sessionTimeoutConfig = $lotsConfig->SessionTimeout ?? [];
        $sessionTimeoutEnabled = $sessionTimeoutConfig->enabled ?? false;
        $useSessionExpire = $sessionTimeoutConfig->use_session_expire ?? false;
        $sessionTimeoutMinutes = $sessionTimeoutConfig->timeout_minutes ?? 60;

        // Calculate timeout based on configuration
        $timeoutMilliseconds = 0;

        if ($useSessionExpire) {
            // Calculate session expire time from session start + lifetime
            if (isset($_SESSION['__Laminas']['_REQUEST_ACCESS_TIME'])) {
                $sessionStart = $_SESSION['__Laminas']['_REQUEST_ACCESS_TIME'];
                $sessionLifetime = ini_get('session.gc_maxlifetime') ?: 3600; // fallback to 1 hour
                $sessionExpire = $sessionStart + $sessionLifetime;
                $currentTime = time();
                $remainingSeconds = $sessionExpire - $currentTime;
                $timeoutMilliseconds = max(0, round($remainingSeconds * 1000)); // Round to integer milliseconds
            } else {
                // Fallback to configured minutes if session start time not found
                $timeoutMilliseconds = round($sessionTimeoutMinutes * 60 * 1000);
            }
        } else {
            // Use configured timeout minutes
            $timeoutMilliseconds = round($sessionTimeoutMinutes * 60 * 1000);
        }

        $sessionExpiredMessage = $this->getView()->translate(
            'session_expired',
            null,
            'Your session has expired. Please login again.'
        );

        return [
            'enabled' => $isLoggedIn && $sessionTimeoutEnabled && $timeoutMilliseconds > 0,
            'timeoutMilliseconds' => $timeoutMilliseconds,
            'message' => $sessionExpiredMessage,
            'logoutUrl' => $this->getView()->url('myresearch-home')
        ];
    }
}
