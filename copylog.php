<?php

use Doctrine\Common\Cache\ArrayCache;
use Jira\Config;
use Jira\Jira;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once 'vendor/autoload.php';

$config = new Config();
$cache = new ArrayCache();

$logger = new Logger('copylog');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
$logger->pushHandler(new StreamHandler('copylog.log', Logger::INFO));
$logger->info('START');

$alpha = new Jira($config->from, $cache, $logger);
$alpha->setLogger($logger)
        ->setCacheProvider($cache)
        ->verifyUser();

$arcanys = new Jira($config->to, $cache, $logger);
$arcanys->setLogger($logger)
        ->setCacheProvider($cache)
        ->verifyUser();

foreach ($config->projects as $from => $to) 
{
    $logger->debug(sprintf('%s ----------------------------> %s', $from, $to ));
    $worklogs = $alpha->findWorklogs($from, $config->from->username, $config->since);
    $arcanys->copyWorklogs($worklogs, $to, $config->to->username);
}

$logger->info('FINISH');
