<?php

require_once 'vendor/autoload.php';

$config = new Jira\Config();
$cache = new Doctrine\Common\Cache\PhpFileCache('cache');
$logger = new Monolog\Logger('copylog');

$alpha = new Jira\Jira($config->from, $cache, $logger);
$arcanys = new Jira\Jira($config->to, $cache, $logger);

foreach ($config->projects as $from => $to) 
{
    $logger->debug(sprintf('%s ----------------------------> %s', $from, $to ));
    $worklogs = $alpha->findWorklogs($from, $config->from->author, $config->since);
    $arcanys->copyWorklogs($worklogs, $to, $config->to->author);
}
