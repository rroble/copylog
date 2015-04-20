<?php

require_once 'vendor/autoload.php';

$config = new Jira\Config();
$cache = new Doctrine\Common\Cache\ArrayCache();
$logger = new Monolog\Logger('copylog');

$alpha = new Jira\Jira($config->from, $cache, $logger);
$alpha->setLogger($logger)
        ->setCacheProvider($cache)
        ->verifyUser();

$arcanys = new Jira\Jira($config->to, $cache, $logger);
$arcanys->setLogger($logger)
        ->setCacheProvider($cache)
        ->verifyUser();

foreach ($config->projects as $from => $to) 
{
    $logger->debug(sprintf('%s ----------------------------> %s', $from, $to ));
    $worklogs = $alpha->findWorklogs($from, $config->from->username, $config->since);
    $arcanys->copyWorklogs($worklogs, $to, $config->to->username);
}
