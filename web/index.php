<?php

use Silex\Provider\MonologServiceProvider;
use Symfony\Component\Yaml\Yaml;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 
$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));
$app['config'] = function ($app) {
    return new \ArrayObject(array_merge([
        'secret' => '1234'
    ], file_exists('config.yml') ? Yaml::parse(file_get_contents('config.yml')) : []));
};
$app['debug'] = true;

$app->post('/deploy_docs/{secret}', function(Request $request) use($app) { 
    if ($request->attributes->get('secret') !== $app['config']->offsetGet('secret')) {
        $app['logger']->addError('Bad secret');
        return new Response('', 401);
    }

    if (!$request->request->has('payload')) {
        throw new \InvalidArgumentException(
            'Expected payload, but none found'
        );
    }

    $payload = json_decode($request->request->get('payload'));

    if (!$payload) {
        throw new \InvalidArgumentException(
            'Could not decode payload'
        );
    }

    $app['logger']->addInfo(print_r($payload, true));

    return new Response('', 200);
}); 

$app->run(); 
