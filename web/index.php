<?php

use Silex\Provider\MonologServiceProvider;
use Symfony\Component\Yaml\Yaml;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

require_once __DIR__.'/../vendor/autoload.php'; 

$app = new Silex\Application(); 
$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));
$app['config'] = function ($app) {
    return new \ArrayObject(array_merge([
        'secret' => '1234'
    ], file_exists(__DIR__ . '/../config.yml') ? Yaml::parse(file_get_contents(__DIR__ . '/../config.yml')) : []));
};
$app['debug'] = true;
$app['process_runner'] = function ($app) {
    return new ProcessRunner($app['logger']);
};

$app->post('/deploy_docs/{secret}', function(Request $request) use($app) { 
    if ($request->attributes->get('secret') !== $app['config']->offsetGet('secret')) {
        $app['logger']->addError('Bad secret');
        return new Response('', 401);
    }
    var_dump($app['config']);die();;

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

    $docsDir = __DIR__ . '/../docs';

    if (!file_exists($docsDir)) {
        $app['logger']->addError(sprintf('Docs dir "%s" does not exist', $docsDir));
        throw new \InvalidArgumentException(sprintf(
            'Docs directory "%s" does not exist.'
        , $docsDir));
    }

    $repositoryName = $payload->repository->name;

    if (!$repositoryName) {
        throw new \InvalidArgumentException(
            'No repository name (expected [payload]->repository->name'
        );
    }

    $repositoryDir = sprintf('%s/../docs/components/%s', __DIR__, $repositoryName);

    if (!file_exists($repositoryDir)) {
        $app['logger']->addError(sprintf('No documentation repository in "%s" (or submodules are outdated)', $repositoryDir));
        return new Response('', 500);
    }

    $app['process_runner']->run('git pull origin master', $repositoryDir);
    $app['process_runner']->run('git commit -am "Updated docs"', $docsDir);
    $app['process_runner']->run('git push origin master', $docsDir);

    return new Response('', 200);
}); 

$app->run(); 

class ProcessRunner
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function run($cmd, $cwd)
    {
        $process = new Process($cmd, $cwd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Could not execute `%s`: [%s] `%s` `%s`', $cmd, $process->getExitCode(), $process->getErrorOutput(), $process->getOutput()));
        }
    }
}
