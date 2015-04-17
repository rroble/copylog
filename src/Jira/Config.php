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
    
    public function __construct($file = 'config.json', $local = 'config.local.json')
    {
        if (!file_exists($file)) {
            throw new \Exception(sprintf('Config file "%s" does not exists!', $file));
        }
        $config = json_decode(file_get_contents($file), true);
        
        if (file_exists($local))
        {
            $config2 = json_decode(file_get_contents($local), true);
            $config = array_merge($config, $config2);
        }
        
        $this->from = (object) $config['from'];
        $this->to = (object) $config['to'];
        $this->projects = $config['projects'];
        $this->since = $config['since'];
    }

}
