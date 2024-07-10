<?php

namespace Rowles;

use ArrayAccess;
use SplObjectStorage;
use Rowles\Exceptions\ExpectedInvokableException;
use Rowles\Exceptions\BlockedServiceException;
use Rowles\Exceptions\UnknownIdentifierException;
use Rowles\Contracts\ServiceProviderInterface;

class Container implements ArrayAccess
{
    /** @var array */
    private array $values = [];

    /** @var SplObjectStorage */
    private SplObjectStorage $factories;

    /** @var SplObjectStorage */
    private SplObjectStorage $protected;

    /** @var array */
    private array $blocked = [];

    /** @var array */
    private array $raw = [];

    /** @var array */
    private array $keys = [];

    /**
     * Container constructor
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->factories = new SplObjectStorage();
        $this->protected = new SplObjectStorage();

        foreach ($values as $key => $value) {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * Sets a parameter or an object.
     *
     * @param string $id
     * @param mixed
     * @return void
     * @throws BlockedServiceException
     */
    public function offsetSet($id, $value): void
    {
        if (isset($this->blocked[$id])) {
            throw new BlockedServiceException($id);
        }

        $this->values[$id] = $value;
        $this->keys[$id] = true;
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $id 
     * @return mixed
     * @throws UnknownIdentifierException
     */
    public function offsetGet($id)
    {
        if (!isset($this->keys[$id])) {
            throw new UnknownIdentifierException($id);
        }

        if (
            isset($this->raw[$id])
            || !is_object($this->values[$id])
            || isset($this->protected[$this->values[$id]])
            || !method_exists($this->values[$id], '__invoke')
        ) {
            return $this->values[$id];
        }

        if (isset($this->factories[$this->values[$id]])) {
            return $this->values[$id]($this);
        }

        $raw = $this->values[$id];
        $val = $this->values[$id] = $raw($this);
        $this->raw[$id] = $raw;

        $this->blocked[$id] = true;

        return $val;
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $id
     * @return bool
     */
    public function offsetExists($id): bool
    {
        return isset($this->keys[$id]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id
     * @return void
     */
    public function offsetUnset($id): void
    {
        if (isset($this->keys[$id])) {
            if (is_object($this->values[$id])) {
                unset($this->factories[$this->values[$id]], $this->protected[$this->values[$id]]);
            }

            unset($this->values[$id], $this->blocked[$id], $this->raw[$id], $this->keys[$id]);
        }
    }

    /**
     * Marks a callable as being a factory service.
     *
     * @param callable $callable
     * @return callable
     * @throws ExpectedInvokableException
     */
    public function factory($callable)
    {
        if (!is_object($callable) || !method_exists($callable, '__invoke')) {
            throw new ExpectedInvokableException('Service definition is not a Closure or invokable object.');
        }

        $this->factories->attach($callable);

        return $callable;
    }

    /**
     * Returns all defined value names.
     *
     * @return array
     */
    public function keys()
    {
        return array_keys($this->values);
    }

    /**
     * Registers a service provider.
     *
     * @param array $values
     * @return static
     */
    public function register(ServiceProviderInterface $provider, array $values = [])
    {
        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }
}