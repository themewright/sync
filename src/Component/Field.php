<?php

namespace ThemeWright\Sync\Component;

use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\Helper\Str;

class Field
{
    /**
     * The class constructor arguments.
     *
     * @var mixed
     */
    protected $args;

    /**
     * Handles an ACF field.
     *
     * @param  mixed  $args
     * @return void
     */
    public function __construct($args)
    {
        $this->args = $args;
    }

    /**
     * Builds PHP code for ACF field arrays.
     *
     * @param  int  $indent
     * @param  string  $keyPrefix
     * @param  string  $return
     * @return string
     */
    public function build(int $indent = 0, $keyPrefix = 'field_', $return = 'string')
    {
        $args = new ArrayArgs();

        $args->add('key', $keyPrefix . $this->args->name);

        foreach ($this->args as $key => $value) {
            $args->add(Str::snake($key), $value);
        }

        $lines = [
            str_repeat("\t", $indent) . "array(",
            implode("\n", $args->asort()->format($indent + 1)),
            str_repeat("\t", $indent) . "),",
        ];

        if ($return == 'array') {
            return $lines;
        } else if ($return == 'ArrayArgs') {
            return $args;
        } else {
            return implode(PHP_EOL, $lines);
        }
    }
}