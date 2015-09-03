<?php

namespace Jira;

use Doctrine\Common\Cache\CacheProvider;

/**
 * @author Randolph Roble <r.roble@arcanys.com>
 */
trait Cache
{

    /**
     * @var CacheProvider
     */
    private $cacheProvider;
    
    public function getCacheProvider()
    {
        return $this->cacheProvider;
    }

    public function setCacheProvider(CacheProvider $cache)
    {
        $this->cacheProvider = $cache;
        return $this;
    }
    
    public function getCache($id)
    {
        if ($this->cacheProvider) {
            if ($this->cacheProvider->contains($id)) {
                return $this->cacheProvider->fetch($id);
            }
        }
    }
    
    public function saveCache($id, $data, $lifeTime = 0)
    {
        if ($this->cacheProvider) {
            $this->cacheProvider->save($id, $data, $lifeTime);
        }
        return $this;
    }
    
}
