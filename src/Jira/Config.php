<?php

namespace Jira;

/**
 * @author Randolph Roble <r.roble@arcanys.com>
 */
class Config
{

    /**
     * @var \stdClass
     */
    public $from;

    /**
     * @var \stdClass
     */
    public $to;

    /**
     * @var array
     */
    public $projects;
    
    public $since;
    
    public function __construct($dir, $file = 'config.json', $local = 'config.local.json')
    {
        $config = [];
        
        $configFile = sprintf('%s%s%s', $dir, DIRECTORY_SEPARATOR, $file);
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
        }
        
        $localConfigFile = sprintf('%s%s%s', $dir, DIRECTORY_SEPARATOR, $local);
        if (file_exists($localConfigFile))
        {
            $config2 = json_decode(file_get_contents($localConfigFile), true);
            $config = array_merge($config, $config2);
        }
        
        if (!$config) {
            throw new \Exception(sprintf('Config file "%s" or "%s" does not exists!', $file, $local));
        }
        
        $this->from = (object) $config['from'];
        $this->to = (object) $config['to'];
        $this->projects = $config['projects'];
        $this->since = $config['since'];
    }

}
