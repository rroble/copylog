<?php

namespace Jira;

use Psr\Log\LoggerInterface;

/**
 * @author Randolph Roble <r.roble@arcanys.com>
 */
trait Logger
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function logCall($message = null)
    {
        if ($this->logger) {
            $d = debug_backtrace()[1];
            $this->logger->info(sprintf('%s::%s() %s', $d['class'], $d['function'], $message));
        }
    }

}
