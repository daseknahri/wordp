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

        $invoker = \Closure::bind(
            function (...$invoke_arguments) use ($name) {
                return $this->{$name}(...$invoke_arguments);
            },
            $this,
            get_class($this)
        );

        return $invoker(...$arguments);
    }
}
