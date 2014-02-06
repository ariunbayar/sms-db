<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();  // {{{1
$app['debug'] = @$_SERVER['REMOTE_ADDR'] == '127.0.0.1';
ErrorHandler::register($app['debug']);
ExceptionHandler::register($app['debug']);

// Register database library, doctrine {{{1
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__.'/db/app.db',
    ),
));

// Register security service provider {{{1
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => [
        'admin' => array(
            'pattern' => '^/admin',
            'http' => true,
            'users' => $app->share(function () use ($app) {
                return new Library\UserProvider($app['db'], $app);
            }),
        ),
    ],
    'security.access_rules' => [
        ['^/admin', 'ROLE_ADMIN'],
    ],
));

// Register templating engine, twig {{{1
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Constants {{{1
const STATUS_SENDING = 0;
const STATUS_SENT = 1;
const STATUS_RECIEVED = 2;

require __DIR__.'/controllers.php';  // {{{1
$app->run();


// vim: fdm=marker tw=120
