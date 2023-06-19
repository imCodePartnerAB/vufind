<?php

namespace LOTS\ILS;

class Connection extends \VuFind\ILS\Connection
{
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


}

