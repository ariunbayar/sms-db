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
$app['debug'] = true;
ErrorHandler::register($app['debug']);
ExceptionHandler::register($app['debug']);

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(  // {{{1
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__.'/db/app.db',
    ),
));
// $app['db'] - instance of Doctrine\DBAL\Connection

$app->before(function (Request $request) use ($app) {  // {{{1
    $is_json = 0 === strpos($request->headers->get('Content-Type'), 'application/json');
    $api_key = trim($request->headers->get('Api-Key'));
    if ($is_json && $api_key) {  // {{{2
        // Validates the api key
        $user = $app['db']->fetchAssoc("SELECT * FROM user WHERE token=?", [$api_key]);  // TODO rename the field to api_key
        if (empty($user)) throw new Exception('invalid api_key: '.$api_key);
        // Replaces request data with json data
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }  // }}}
});

require __DIR__.'/controllers.php';  // {{{1
$app->run();


// vim: fdm=marker
