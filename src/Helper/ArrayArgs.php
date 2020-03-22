<?php

namespace ThemeWright\Sync\Helper;

class ArrayArgs
{
    /**
     * The array arguments object.
     *
     * @var object
     */
    private $args = [];

    /**
     * The character count of the longest key of all arguments.
     *
     * @var integer
     */
    private $maxKeyChars = 0;

    /**
     * Helper for building formatted array arguments.
     *
     * @param  array  $array
     * @return void
     */
    public function __construct($array = [])
    {
        foreach ($array as $key => $value) {
            $key = is_string($key) ? $key : '';
            $this->add($key, $value);
        }
    }

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
            $value = new ArrayArgs($value);
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
     * Checks if the $args array is empty.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return empty($this->args);
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
            $key = $arg->key ? "'{$arg->key}'" . str_repeat(' ', $this->maxKeyChars - strlen($arg->key)) . " => " : '';

            if ($arg->value instanceof ArrayArgs) {
                $out[] = str_repeat("\t", $indent) . $key . "array(";
                $out = array_merge($out, $arg->value->format($indent + 1));
                $out[] = str_repeat("\t", $indent) . '),';
            } else {
                $out[] = str_repeat("\t", $indent) . $key . "{$arg->value},";
            }
        }

        return $out;
    }
}