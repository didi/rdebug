<?php declare(strict_types=1);

namespace Midi;

use Midi\Exception\ContainerException;
use Midi\Exception\ContainerValueNotFoundException;
use Psr\Container\ContainerInterface;
use Pimple\Container as PimpleContainer;

final class Container extends PimpleContainer implements ContainerInterface
{
    private static $instance;

    /**
     * Get container instance.
     */
    public static function ins()
    {
        if (static::$instance) {
            return static::$instance;
        }
        return static::$instance = new self();
    }

    /**
     * Make from container.
     *
     * @param $name
     * @return mixed
     * @throws ContainerException
     * @throws ContainerValueNotFoundException
     */
    public static function make($name)
    {
        return static::ins()->get($name);
    }

    public static function bind($name, $value)
    {
        return static::ins()->offsetSet($name, $value);
    }

    /**
     * Make from container.
     *
     * @param string $name .
     *
     * @return mixed
     * @throws ContainerException               Error while retrieving the entry.
     *
     * @throws ContainerValueNotFoundException  No entry was found for this name.
     */
    public function get($name)
    {
        if (!$this->offsetExists($name)) {
            throw new ContainerValueNotFoundException(sprintf('Identifier "%s" is not found.', $name));
        }
        try {
            return $this->offsetGet($name);
        } catch (\InvalidArgumentException $exception) {
            if ($this->exceptionThrownByContainer($exception)) {
                throw new ContainerException(sprintf('Container error while retrieving "%s"', $name), null, $exception);
            } else {
                throw $exception;
            }
        }
    }

    /**
     * Is exist in container.
     *
     * @param string $name
     *
     * @return boolean
     */
    public function has($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * Is thrown by pimple.
     *
     * @param \Exception $exception
     *
     * @return bool
     */
    private function exceptionThrownByContainer(\Exception $exception)
    {
        $trace = $exception->getTrace()[0];

        return $trace['class'] === PimpleContainer::class && $trace['function'] === 'offsetGet';
    }
}