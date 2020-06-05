<?php

namespace ThemeWright\Sync\Helper;

class ArrayArgs
{
    /**
     * The arguments array.
     *
     * @var array
     */
    public $args = [];

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
     * @param  bool  $allKeys
     * @return void
     */
    public function __construct($array = [], $allKeys = false)
    {
        foreach ($array as $key => $value) {
            if (!$allKeys) {
                $key = is_string($key) ? $key : '';
            }

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
    public function add($key, $value)
    {
        if ($value === true) {
            $value = 'true';
        } else if ($value === false) {
            $value = 'false';
        } else if (is_null($value)) {
            $value = 'null';
        } else if (is_string($value) && strpos($value, '@php:') === 0) {
            $value = substr($value, 5);
        } else if (is_string($value)) {
            $value = "'{$value}'";
        } else if (is_array($value)) {
            $value = new ArrayArgs($value);
        } else if (is_object($value) && !is_a($value, 'ThemeWright\Sync\Helper\ArrayArgs')) {
            $value = $value;
        }

        $arg = (object) [
            'key' => $key,
            'value' => $value,
        ];

        if (strlen($key) > $this->maxKeyChars) {
            $this->maxKeyChars = strlen($key);
            $this->args[$key] = $arg;
        } else {
            $this->args[] = $arg;
        }

        return $this;
    }

    /**
     * Removes an array argument.
     *
     * @param  string  $key
     * @param  boolean  $recursive
     * @return ThemeWright\Sync\Helper\ArrayArgs
     */
    public function remove(string $key, $recursive = false)
    {
        unset($this->args[$key]);

        if ($recursive) {
            foreach ($this->args as $arg) {
                if (is_object($arg->value) && is_a($arg->value, 'ThemeWright\Sync\Helper\ArrayArgs')) {
                    $arg->value->remove($key, true);
                }
            }
        }

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
     * @param  bool  $deep
     * @return ThemeWright\Sync\Helper\ArrayArgs
     */
    public function asort($deep = false)
    {
        asort($this->args);

        if ($deep) {
            foreach ($this->args as $arg) {
                if ($arg->value instanceof ArrayArgs) {
                    $arg->value->asort(true);
                }
            }
        }

        return $this;
    }

    /**
     * Aligns and outputs the array arguments as an array of string.
     *
     * @param  int  $indent
     * @param  bool  $rawKeys
     * @return string[]
     */
    public function format($indent = 1, $rawKeys = false)
    {
        $out = [];

        foreach ($this->args as $arg) {
            if ($rawKeys) {
                $key = $arg->key != '' ? $arg->key . str_repeat(' ', $this->maxKeyChars - strlen($arg->key)) . " => " : '';
            } else {
                $key = $arg->key != '' ? "'{$arg->key}'" . str_repeat(' ', $this->maxKeyChars - strlen($arg->key)) . " => " : '';
            }

            if ($arg->value instanceof ArrayArgs) {
                $out[] = str_repeat("\t", $indent) . $key . "array(";
                $out = array_merge($out, $arg->value->format($indent + 1));
                $out[] = str_repeat("\t", $indent) . '),';
            } else if (is_string($arg->value) || is_numeric($arg->value)) {
                $out[] = str_repeat("\t", $indent) . $key . "{$arg->value},";
            }
        }

        return $out;
    }
}