<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Original source:
 * https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/Session/Storage/Handler/RedisSessionHandler.php
 * Last revision: https://github.com/symfony/symfony/commit/239a022cc01cca52c3f6ddde3231199369cf34c2
 */

namespace EzSystems\SymfonyTools\Symfony\Components\HttpFoundation\Session\Storage\Handler;

use Predis\Response\ErrorInterface;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class RedisSessionHandler extends AbstractSessionHandler
{
    private $redis;

    /**
     * @var string Key prefix for shared environments
     */
    private $prefix;

    /**
     * List of available options:
     *  * prefix: The prefix to use for the keys in order to avoid collision on the Redis server.
     *
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client|RedisProxy $redis
     * @param array                                                      $options An associative array of options
     *
     * @throws \InvalidArgumentException When unsupported client or options are passed
     */
    public function __construct($redis, array $options = array())
    {
        if (
            !$redis instanceof \Redis &&
            !$redis instanceof \RedisArray &&
            !$redis instanceof \RedisCluster &&
            !$redis instanceof \Predis\Client &&
            !$redis instanceof RedisProxy &&
            !$redis instanceof RedisClusterProxy
        ) {
            throw new \InvalidArgumentException(sprintf('%s() expects parameter 1 to be Redis, RedisArray, RedisCluster or Predis\Client, %s given', __METHOD__, \is_object($redis) ? \get_class($redis) : \gettype($redis)));
        }

        if ($diff = array_diff(array_keys($options), array('prefix'))) {
            throw new \InvalidArgumentException(sprintf('The following options are not supported "%s"', implode(', ', $diff)));
        }

        $this->redis = $redis;
        $this->prefix = $options['prefix'] ? 'sf_s' : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead($sessionId)
    {
        return $this->redis->get($this->prefix.$sessionId) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($sessionId, $data)
    {
        $result = $this->redis->setEx($this->prefix.$sessionId, (int) ini_get('session.gc_maxlifetime'), $data);

        return $result && !$result instanceof ErrorInterface;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy($sessionId)
    {
        $this->redis->del($this->prefix.$sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        return (bool) $this->redis->expire($this->prefix.$sessionId, (int) ini_get('session.gc_maxlifetime'));
    }
}
