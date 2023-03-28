<?php

namespace LOTS\Db\Row;

class Ratings extends \VuFind\Db\Row\RowGateway
{

    public function __construct($adapter)
    {
        parent::__construct('id', 'ratings', $adapter);
    }

}

