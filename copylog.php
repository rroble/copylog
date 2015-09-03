<?php

use Doctrine\Common\Cache\ArrayCache;
use Jira\Config;
use Jira\Jira;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once __DIR__.'/vendor/autoload.php';

$logger = new Logger('copylog');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
$logger->pushHandler(new StreamHandler(__DIR__.'/logs/copylog.log', Logger::INFO));

try {
    $config = new Config(__DIR__);
} catch(Exception $e) {
    $logger->error($e->getMessage());
    exit();
}

$cache = new ArrayCache();
$logger->info('START');

$alpha = new Jira($config->from, $logger, $cache);
$alpha->verifyUser();

$arcanys = new Jira($config->to, $logger, $cache);
$arcanys->verifyUser();

foreach ($config->projects as $from => $to) 
{
    $logger->info(sprintf('%s ----------------------------> %s', $from, $to ));
    try {
        $worklogs = $alpha->findWorklogs($from, $config->from->username, $config->since);
        $arcanys->copyWorklogs($worklogs, $to, $config->to->username);
    } catch(Exception $e) {
        $logger->error($e->getMessage());
    }
}

$logger->info('FINISH');
