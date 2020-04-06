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

        $keySuffix = $this->args->name ?: $this->args->twKey;

        $args->add('key', $keyPrefix . $keySuffix);

        foreach ($this->args as $key => $value) {
            if ($value != '' && !is_null($value)) {
                $snakeKey = Str::snake($key);

                if ($snakeKey == 'choices') {
                    $choices = new ArrayArgs;

                    foreach ($value as $option) {
                        $choices->add($option->value, $option->text);
                    }

                    $args->add($snakeKey, $choices);
                } else if ($snakeKey == 'sub_fields' || $snakeKey == 'layouts') {
                    $subFields = [];

                    foreach ($value as $subFieldArgs) {
                        $subFields[] = (new Field($subFieldArgs))->build($indent + 1, $keyPrefix . $keySuffix . '__', 'ArrayArgs');
                    }

                    $args->add($snakeKey, $subFields);
                } else {
                    $args->add($snakeKey, $value);
                }
            }
        }

        $args->remove('tw_key')->asort();

        $lines = [
            str_repeat("\t", $indent) . "array(",
            implode("\n", $args->format($indent + 1)),
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