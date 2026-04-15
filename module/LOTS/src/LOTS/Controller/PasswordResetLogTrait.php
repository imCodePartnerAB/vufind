<?php
/**
 * Trait for logging password reset events to a dedicated log file
 */

namespace LOTS\Controller;

trait PasswordResetLogTrait
{
    /**
     * Log password reset event to dedicated log file (if enabled in LOTS.ini).
     * Always calls error_log() as fallback; additionally writes to
     * password_reset.log in the same directory as vufind.log when
     * PasswordRecovery.password_reset_log = true in LOTS.ini.
     *
     * @param string $message Log message
     *
     * @return void
     */
    protected function logPasswordReset(string $message): void
    {
        $lotsConfig = $this->getConfig('LOTS');
        $enabled = $lotsConfig->PasswordRecovery->password_reset_log ?? false;

        $logMessage = date('Y-m-d H:i:s') . ' [PasswordReset] ' . $message . PHP_EOL;

        if ($enabled) {
            // Derive log dir from main config [Logging] file setting
            $mainConfig = $this->getConfig();
            $logFile = $mainConfig->Logging->file ?? '';
            // Strip alert level suffix (e.g. "/var/log/vufind/vufind.log:alert,error")
            $logFilePath = explode(':', $logFile)[0];
            $logDir = $logFilePath ? dirname($logFilePath) : '/var/log/vufind';
            $resetLogFile = rtrim($logDir, '/') . '/password_reset.log';
            error_log($logMessage, 3, $resetLogFile);
        } else {
            error_log('[PasswordReset] ' . $message);
        }
    }
}
