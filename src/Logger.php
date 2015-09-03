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

    public function debug($message = null, $indent = 0)
    {
        if ($this->logger) {
            $d = debug_backtrace()[1];
            $i = $indent ? $indent-1 : 0;
            $pre = str_repeat('    ', $i);
            if ($indent) {
                $pre .= '  -> ';
            }
            $this->logger->debug(sprintf('%s%s() %s', $pre, $d['function'], $message));
        }
    }

    public function info($message = null)
    {
        if ($this->logger) {
            $d = debug_backtrace()[1];
            $this->logger->info(sprintf('%s() %s', $d['function'], $message));
        }
    }

    public function error($message = null)
    {
        if ($this->logger) {
            $d = debug_backtrace()[1];
            $this->logger->error(sprintf('%s() %s', $d['function'], $message));
        }
    }

}
