<?php

namespace Jira;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Exception\ClientException;
use JiraApi\Clients\IssueClient;
use JiraApi\Clients\ProjectClient;
use JiraApi\Search\SearchBuilder;
use Psr\Log\LoggerInterface;

/**
 * @author Randolph Roble <r.roble@arcanys.com>
 */
class Jira
{

    use Logger;
    use Cache;
    
    /**
     * @var ProjectClient
     */
    private $projectClient;

    /**
     * @var IssueClient
     */
    private $issueClient;
    
    /**
     * @var \stdClass
     */
    private $config;
    
    private $versions = [];

    public function __construct(\stdClass $config, LoggerInterface $logger, CacheProvider $cacheProvider = null)
    {
        $this->config = $config;
        $this->projectClient = new ProjectClient($config->url, $config->username, $config->password);
        $this->issueClient = new IssueClient($config->url, $config->username, $config->password);
        
        $this->logger = $logger;
        $this->cacheProvider = $cacheProvider ? $cacheProvider : new ArrayCache();
    }
    
    public function verifyUser()
    {
        try {
            // TODO: ask password from INPUT
            $this->issueClient->getRequest(sprintf('user?username=%s', $this->config->username))->json();
            $this->debug(sprintf('%s ok', $this->config->url));
        } catch(ClientException $e) {
            $this->error($e->getMessage());
            die('Please check config file.');
        }
        return $this;
    }
    
    public function copyWorklogs(array $worklogs, $to, $author)
    {
        $this->debug('', 1);
        
        $parts = array_map('trim', explode('>', $to));
        $project = $parts[0];
        $version = isset($parts[1]) ? $parts[1] : null;

        foreach ($worklogs as $worklog) 
        {
            $summary = sprintf('[%s] %s', $worklog['issue']['key'], $worklog['issue']['fields']['summary']);
            $issue = $this->findIssue($summary, $project);
            if (!$issue) {
                try {
                    $issue = $this->createIssue($summary, $project, $version, $worklog['issue']);
                } catch (\Exception $e) {
                    $this->debug($e->getMessage());
                    continue;
                }
            }

            $copied = false;
            $logs = $this->getWorklogs($issue['key']);
            foreach ($logs['worklogs'] as $log) 
            {
                // only from author
                if ($log['author']['name'] != $author) {
                    continue;
                }
                
                // check if already copied
                $copied = $this->isCopied($log, $worklog);
                if ($copied) {
                    break;
                }
            }

            if (!$copied) {
                $this->createWorklog($issue['key'], $worklog);
            }
        }
        
        $this->debug('done', 1);
    }
    
    protected function isCopied(array $worklog1, array $worklog2)
    {
        // compare comments
        if ($worklog1['comment'] != $worklog2['comment']) {
            return false;
        }
        
        // compare time spent
        if ($worklog1['timeSpentSeconds'] != $worklog2['timeSpentSeconds']) {
            return false;
        }
        
        // compare dates
        $d1 = new \DateTime($worklog1['started']);
        $d2 = new \DateTime($worklog2['started']);
        $tz = new \DateTimeZone('Asia/Manila');
        $d1->setTimezone($tz);
        $d2->setTimezone($tz);
        if ($d1->format('Y-m-d') != $d2->format('Y-m-d')) {
            return false;
        }
        
        return true;
    }

    public function createWorklog($idOrKey, array $worklog)
    {
        $this->info(sprintf('********* %s ********', $idOrKey), 3);

        $data = array(
            'comment' => $worklog['comment'],
            'timeSpentSeconds' => $worklog['timeSpentSeconds'],
            'started' => $worklog['started'],
        );
        
        $progress = $worklog['issue']['fields']['progress'];
        $remaining = $progress['total'] - $progress['progress'];
        
        $this->issueClient->createWorklog($idOrKey, $data, 'new', sprintf('%dm', $remaining/60));
    }
    
    protected function fetchVersions($idOrKey)
    {
        $result = $this->projectClient->getVersions($idOrKey)->json();
        foreach ($result as $v) {
            $name = $v['name'];
            $this->versions[$idOrKey][$name] = $v['id'];
        }
    }

    protected function getVersionId($projectKey, $version)
    {
        if (!$version) return null;
        
        if (!isset($this->versions[$projectKey])) 
        {
            $this->fetchVersions($projectKey);
        }
        if (isset($this->versions[$projectKey][$version]))
        {
            return $this->versions[$projectKey][$version];
        }
    }

    public function createIssue($summary, $projectKey, $version, array $issue)
    {
        $this->info(sprintf('++++++++++ %s ++++++++++', $summary), 2);
        
        $project = $this->getProject($projectKey);
        if (!$project) {
            throw new \Exception(sprintf('Project %s not found!', $projectKey));
        }
        list($d,) = explode('/rest/api', $issue['self']);
        
        $data = array(
            'fields' => [
                'project' => [
                    'id' => $project['id'],
                ],
                'summary' => $summary,
                'issuetype' => [
                    'id' => 3, // FIXME: Task
                ],
                'description' => sprintf('CopyLog %s/browse/%s', $d, $issue['key']),
            ]
        );
        
        $versionId = $this->getVersionId($projectKey, $version);
        if ($versionId) {
            $data['fields']['fixVersions'] = array(['id' => $versionId]);
        }

        $result = $this->issueClient->create($data)->json();
        return $result;
    }

    public function findIssue($summary, $project)
    {
        $this->debug($summary, 2);

        $cacheId = sprintf('%s_issue_%s', $project, md5($summary));
        if (($cached = $this->getCache($cacheId))) {
            return $cached;
        }

        $builder = new SearchBuilder();
        $find = array('?', '-', '[', ']', '"');
        $replace = array('\\\\?', '\\\\-', '\\\\[', '\\\\]', '\\"');
        $escaped = str_replace($find, $replace, $summary);
        $jql = sprintf('project = %s AND text ~ "%s"', $project, $escaped);
        $this->debug($jql);
        $builder->setJql($jql);

        $issues = $this->issueClient->search($builder)->json();
        if (isset($issues['issues'][0])) {
            $issue = $issues['issues'][0];

            $this->saveCache($cacheId, $issue, 60);

            return $issue;
        }
    }

    public function getProject($idOrKey)
    {
        $cacheId = sprintf('project_%s', $idOrKey);
        if (($cached = $this->getCache($cacheId))) {
            return $cached;
        }

        $project = $this->projectClient->get($idOrKey)->json();

        $this->saveCache($cacheId, $project, 60);

        return $project;
    }

    public function getWorklogs($idOrKey)
    {
        $this->debug($idOrKey, 3);

        $cacheId = sprintf('%s_worklogs', $idOrKey);
        if (($cached = $this->getCache($cacheId))) {
            return $cached;
        }

        $worklogs = $this->issueClient->getFullWorklog($idOrKey)->json();

        $this->saveCache($cacheId, $worklogs, 60);
        
        $this->debug(sprintf('found %d worklogs', count($worklogs)), 4);

        return $worklogs;
    }
    
    public function findWorklogs($project, $author, $since, $limit = 150)
    {
        $this->debug(sprintf('from project %s by %s since %s', $project, $author, $since));
        $logs = [];
        
        $cacheId = sprintf('%s_worklogs_%s', $project, $author);
        if (($cached = $this->getCache($cacheId))) {
            return $cached;
        }
        else
        {
            $builder = new SearchBuilder();
            $jql = sprintf('project = %s AND updated >= %s ORDER BY updated DESC', $project, $since);
            $builder->setJql($jql);
            $builder->setLimit($limit);

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
            $this->saveCache($cacheId, $logs, 60 * 60 * 5); // 5 mins?
        }
        
        $this->debug(sprintf('found %d worklogs', count($logs)), 1);
        
        return $logs;
    }

}
