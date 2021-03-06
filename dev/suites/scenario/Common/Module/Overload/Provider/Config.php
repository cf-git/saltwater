<?php

namespace Saltwater\Overload\Provider;

use Saltwater\App\Common\Config as AbstractConfig;

class Config extends AbstractConfig
{
    public function __construct()
    {
        return (object) array(
            'database' => (object) array(
                'name'     => 'overload',
                'dsn'      => 'sqlite:/tmp/oodb_overload.db',
                'prefix'   => 'ovrld_',
                'user'     => null,
                'password' => null
            )
        );
    }
}
