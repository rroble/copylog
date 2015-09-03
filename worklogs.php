<?php

use Doctrine\Common\Cache\PhpFileCache;
use Jira\Config;
use Jira\Jira;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require_once __DIR__.'/vendor/autoload.php';

$logger = new Logger('worklog');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
$logger->pushHandler(new StreamHandler(__DIR__.'/logs/worklog.log', Logger::INFO));

try {
    $config = new Config(__DIR__);
} catch(Exception $e) {
    $logger->error($e->getMessage());
    exit();
}

$cache = new PhpFileCache(__DIR__.'/cache');
$logger->info('START');


$from = mktime(0, 0, 0, 8, 1);
$to = mktime(23, 59, 59, 8, 31);
  
$alpha = new Jira($config->from, $logger, $cache);
$alpha->verifyUser();
find_work_logs(
        $alpha, 
        'alpha', 
        ['SS','ACC','FCS','AC','ABIOY','TCS','ABB'], 
        $from, 
        $to, 
        ['s.schmid', 'rroble', 'd.delgado']);

$arcanys = new Jira($config->to, $logger, $cache);
$arcanys->verifyUser();
find_work_logs(
        $arcanys, 
        'arc', 
        ['AIL'], 
        $from, 
        $to, 
        ['rroble', 's.schmid', 'd.delgado']);

function find_work_logs(Jira $jira, $name, $projects, $from, $to, $authors)
{
    $issues = $jira->findIssuesWithWorklogs($projects);
    $issueLogs = $jira->getWorklogsFromIssues($issues);

    $all = array();

    foreach ($issueLogs as $issue => $worlogs) {
        list($project,) = explode('-', $issue);
        foreach ($worlogs as $worlog) {
            $author = $worlog['author']['key'];

            // skip
            if (!in_array($author, $authors)) continue;

            if (!isset($all[$author])) {
                $all[$author] = array();
            }
            if (!isset($all[$author][$project])) {
                $all[$author][$project] = array();
            }
            $date = $worlog['started'];
            $dt = new DateTime($date);
            $ts = $dt->getTimestamp();

            // skip
            if ($ts < $from || $ts > $to) continue;

            $all[$author][$project][$date] = array(
                $date, $issue, $worlog['timeSpent'], $worlog['timeSpentSeconds'], $worlog['comment'], $worlog['id'],
            );
        }
    }

    foreach ($all as $author => &$projects) {
        ksort($projects);

        $csvfile = sprintf('%s/%s/%s.csv', __DIR__, 'worklogs', $author);
        if (!is_dir(dirname($csvfile))) {
            mkdir(dirname($csvfile));
        }
        $fp = fopen(sprintf('%s/%s/%s_%s.csv', __DIR__, 'worklogs', $name, $author), 'w');
        $total = 0;
        foreach ($projects as $projkey => $worlogs) {
            fputcsv($fp, [$projkey]);
            foreach ($worlogs as $worlog) {
                $total += $worlog[3];
                fputcsv($fp, $worlog);
            }
        }
        fputcsv($fp, ['','','', $total, 'seconds']);
        fputcsv($fp, ['','','',($total/60), 'minutes']);
        fputcsv($fp, ['','','',(($total/60)/60), 'hours']);
        fclose($fp);
    }
}

$logger->info('FINISH');
