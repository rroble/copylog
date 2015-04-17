<?php

namespace Jira;

use Doctrine\Common\Cache\CacheProvider;
use JiraApi\Clients\IssueClient;
use JiraApi\Clients\ProjectClient;
use JiraApi\Search\SearchBuilder;
use Psr\Log\LoggerInterface;

/**
 * @author Randolph Roble <r.roble@arcanys.com>
 */
class Jira
{

    /**
     * @var ProjectClient
     */
    private $projectClient;

    /**
     * @var IssueClient
     */
    private $issueClient;

    /**
     * @var CacheProvider
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(\stdClass $config, CacheProvider $cache = null, LoggerInterface $logger = null)
    {
        $this->projectClient = new ProjectClient($config->url, $config->username, $config->password);
        $this->issueClient = new IssueClient($config->url, $config->username, $config->password);
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function copyWorklogs(array $worklogs, $project, $author)
    {
        $this->logCall();

        foreach ($worklogs as $worklog) 
        {
            $summary = $worklog['issue']['fields']['summary'];
            $issue = $this->findIssue($summary, $project);
            if (!$issue) {
                try {
                    $issue = $this->createIssue($summary, $project);
                } catch (\Exception $e) {
                    if ($this->logger) {
                        $this->logger->warning($e->getMessage());
                    }
                    continue;
                }
            }

            $logs = $this->getWorklogs($issue['key']);
            foreach ($logs['worklogs'] as $log) {
                if ($log['author']['name'] == $author) {
                    if ($log['comment'] == $worklog['comment']) {
                        continue 2;
                    }
                }
            }

            $this->createWorklog($issue['key'], $worklog);
        }
        
        $this->logCall('done');
    }

    public function createWorklog($idOrKey, array $worklog)
    {
        $this->logCall($idOrKey);

        $data = array(
            'comment' => $worklog['comment'],
            'timeSpentSeconds' => $worklog['timeSpentSeconds'],
            'started' => $worklog['started'],
        );
        $this->issueClient->createWorklog($idOrKey, $data);
    }

    public function createIssue($summary, $projectKey)
    {
        $this->logCall($summary);
        
        $project = $this->getProject($projectKey);
        if (!$project) {
            throw new \Exception(sprintf('Project %s not found!', $projectKey));
        }
        $data = array(
            'fields' => [
                'project' => [
                    'id' => $project['id'],
                ],
                'summary' => $summary,
                'issuetype' => [
                    'id' => 3, // FIXME: Task
                ]
            ]
        );

        $result = $this->issueClient->create($data)->json();
        return $result;
    }

    public function findIssue($summary, $project)
    {
        $this->logCall($summary);

        $cacheId = sprintf('%s_issue_%s', $project, md5($summary));
        if ($this->cache->contains($cacheId)) {
            return $this->cache->fetch($cacheId);
        }

        $builder = new SearchBuilder();
        $jql = sprintf('project = %s AND text ~ "%s"', $project, $summary);
        $builder->setJql($jql);

        $issues = $this->issueClient->search($builder)->json();
        if (isset($issues['issues'][0])) {
            $issue = $issues['issues'][0];

            $this->cache->save($cacheId, $issue, 60);

            return $issue;
        }
    }

    public function getProject($idOrKey)
    {
        $cacheId = sprintf('project_%s', $idOrKey);
        if ($this->cache->contains($cacheId)) {
            return $this->cache->fetch($cacheId);
        }

        $project = $this->projectClient->get($idOrKey)->json();

        $this->cache->save($cacheId, $project, 60);

        return $project;
    }

    public function getWorklogs($idOrKey)
    {
        $this->logCall($idOrKey);

        $cacheId = sprintf('%s_worklogs', $idOrKey);
        if ($this->cache->contains($cacheId)) {
            return $this->cache->fetch($cacheId);
        }

        $worklogs = $this->issueClient->getFullWorklog($idOrKey)->json();

        $this->cache->save($cacheId, $worklogs, 60);

        return $worklogs;
    }
    
    public function findWorklogs($project, $author, $since)
    {
        $this->logCall(sprintf('from project %s by %s since %s', $project, $author, $since));
        $logs = [];
        
        $cacheId = sprintf('%s_worklogs_%s', $project, $author);
        if ($this->cache->contains($cacheId)) {
            $logs = $this->cache->fetch($cacheId);
        }
        else
        {
            $builder = new SearchBuilder();
            $jql = sprintf('project = %s AND status in (Open, "In Progress", Reopened, "Ready for QA") AND updated >= %s ORDER BY updated DESC', $project, $since);
            $builder->setJql($jql);
            $builder->setLimit(50);

            $issues = $this->issueClient->search($builder)->json();

            foreach ($issues['issues'] as $issue) {
                if ($issue['fields']['timespent']) {
                    $worklogs = $this->issueClient->getFullWorklog($issue['key'])->json();
                    foreach ($worklogs['worklogs'] as $worklog) {
                        if ($worklog['author']['name'] == $author) {
                            $worklog['issue'] = $issue;
                            $logs[] = $worklog;
                        }
                    }
                }
            }
            $this->cache->save($cacheId, $logs, 60 * 60 * 5); // 5 mins?
        }
        
        $this->logCall(sprintf('found %d worklogs', count($logs)));
        
        return $logs;
    }
    
    public function getCache()
    {
        return $this->cache;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function setCache(CacheProvider $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function logCall($message = null)
    {
        if ($this->logger) {
            $d = debug_backtrace()[1];
            $this->logger->info(sprintf('%s::%s() %s', $d['class'], $d['function'], $message));
        }
    }

}
