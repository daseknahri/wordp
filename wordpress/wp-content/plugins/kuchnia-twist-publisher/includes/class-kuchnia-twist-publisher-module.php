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
        if (is_callable([$this, $name])) {
            return $this->{$name}(...$arguments);
        }

        if (method_exists($this->plugin, $name) && method_exists($this->plugin, 'invoke_local_method')) {
            return $this->plugin->invoke_local_method($name, $arguments);
        }

        if (is_callable([$this->plugin, $name])) {
            return $this->plugin->{$name}(...$arguments);
        }

        if (method_exists($this->plugin, 'invoke_module_method')) {
            return $this->plugin->invoke_module_method($name, $arguments, $this);
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
