<?php

defined('ABSPATH') || exit;

abstract class Kuchnia_Twist_Publisher_Module extends Kuchnia_Twist_Publisher_Base
{
    protected Kuchnia_Twist_Publisher $plugin;

    public function __construct(Kuchnia_Twist_Publisher $plugin)
    {
        $this->plugin = $plugin;
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->{$name}(...$arguments);
        }

        if (method_exists($this->plugin, $name)) {
            return $this->plugin->{$name}(...$arguments);
        }

        if (method_exists($this->plugin, '__call')) {
            return $this->plugin->__call($name, $arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s not found on module or plugin.', $name));
    }

    public function invoke(string $name, array $arguments)
    {
        if (!method_exists($this, $name)) {
            throw new BadMethodCallException(sprintf('Method %s not found on module.', $name));
        }

        return $this->{$name}(...$arguments);
    }
}
