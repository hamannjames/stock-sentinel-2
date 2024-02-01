<?php

namespace App\Http\Integrations;

use App\Http\Integrations\EfdConnector;

/*
    Just a wrapper for getting one instance of an efd connector. Useful for things like testing. 
*/
class EfdConnectorSingleton
{
    private static $instance;

    public static function getInstance() : EfdConnector
    {
        if (self::$instance == null) {
            self::$instance = new EfdConnector();
        }

        return self::$instance;
    }
}