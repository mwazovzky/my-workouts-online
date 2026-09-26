<?php

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
|
| PHPUnit writes the <env force="true"> values from phpunit.xml into $_ENV
| and putenv(), but Laravel resolves configuration from $_SERVER first. In a
| container the real environment (APP_ENV=local, DB_CONNECTION=mysql, ...)
| is present in $_SERVER and would silently win, pointing RefreshDatabase at
| the development database. Mirror the PHPUnit values into $_SERVER so the
| suite always runs against its own configuration.
|
*/

foreach ($_ENV as $key => $value) {
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
