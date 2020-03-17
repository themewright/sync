<?php

namespace ThemeWright\Sync\Helper;

/**
 * Helper for building formatted array arguments.
 */
class ArrayArgs
{
    /**
     * The array arguments object.
     *
     * @var object
     */
    private $args;

    /**
     * The character count of the longest key of all arguments.
     *
     * @var integer
     */
    private $maxKeyChars = 0;

    /**
     * Adds an array argument.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return ThemeWright\Sync\Helper\ArrayArgs
     */
    public function add(string $key, $value)
    {
        if ($value === true) {
            $value = 'true';
        } else if ($value === false) {
            $value = 'false';
        } else if (is_null($value)) {
            $value = 'null';
        } else if (is_string($value)) {
            $value = "'{$value}'";
        } else if (is_array($value)) {
            // @todo
        }

        if (strlen($key) > $this->maxKeyChars) {
            $this->maxKeyChars = strlen($key);
        }

        $this->args[] = (object) [
            'key' => $key,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Sort the array arguments and maintain index association.
     *
     * @return ThemeWright\Sync\Helper\ArrayArgs
     */
    public function asort()
    {
        asort($this->args);
        return $this;
    }

    /**
     * Aligns and outputs the array arguments as an array of string.
     *
     * @param  int  $indent
     * @return string[]
     */
    public function format($indent = 1)
    {
        $out = [];

        foreach ($this->args as $arg) {
            $out[] = str_repeat("\t", $indent) . "'{$arg->key}'" . str_repeat(' ', $this->maxKeyChars - strlen($arg->key)) . " => {$arg->value},";
        }

        return $out;
    }
}