<?php

namespace LOTS\ILS;

class Connection extends \VuFind\ILS\Connection
{
    /**
     * Check for checkMethodupdateTransactionHistoryState
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports change of
     * the checkout history state.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateTransactionHistoryState(
        $functionConfig,
        $params
    ) {
        if (!isset($functionConfig['method'])) {
            return false;
        }

        $capability = $this->checkCapability(
            'updateTransactionHistoryState',
            [$params ?: []]
        );
        return $capability ? $functionConfig : false;
    }

    /**
     * Check for updatePhone
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating phone
     * number.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdatePhone($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'email'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updatePhone', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }

    /**
     * Check for updateSmsNumber
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating phone
     * number.
     *
     * @param array $functionConfig The configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, array of configuration data; on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateSmsNumber($functionConfig, $params)
    {
        if (!isset($functionConfig['method'])) {
            return false;
        }
        if ($functionConfig['method'] == 'driver'
            && !empty($functionConfig['emailAddress'])
        ) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'url' && !empty($functionConfig['url'])) {
            return $functionConfig;
        }
        if ($functionConfig['method'] == 'driver'
            && $this->checkCapability('updateSmsNumber', [$params ?: []])
        ) {
            return $functionConfig;
        }

        return false;
    }
}
