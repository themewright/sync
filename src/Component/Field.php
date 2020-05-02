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
     * The related field sets.
     *
     * @var mixed
     */
    protected $fieldSets;

    /**
     * Handles an ACF field.
     *
     * @param  mixed  $args
     * @param  mixed  $fieldSets
     * @return void
     */
    public function __construct($args, $fieldSets)
    {
        $this->args = $args;
        $this->fieldSets = $fieldSets;
    }

    /**
     * Builds PHP code for ACF field arrays.
     *
     * @param  int  $indent
     * @param  string  $keyPrefix
     * @param  string  $return
     * @return mixed
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
                        if (isset($subFieldArgs->fieldSet)) {
                            $i = array_search($subFieldArgs->fieldSet, array_column($this->fieldSets, 'id'));

                            foreach ($this->fieldSets[$i]->fieldGroup->fields as $fieldSetFieldArgs) {
                                $subFields[] = (new Field($fieldSetFieldArgs, []))->build($indent + 1, $keyPrefix . $keySuffix . '__', 'ArrayArgs');
                            }
                        } else {
                            $subFields[] = (new Field($subFieldArgs, $this->fieldSets))->build($indent + 1, $keyPrefix . $keySuffix . '__', 'ArrayArgs');
                        }
                    }

                    $args->add($snakeKey, $subFields);
                } else if ($snakeKey == 'conditional_logic' || $snakeKey == 'wrapper') {
                    $args->add($snakeKey, json_decode(json_encode($value), true));
                } else {
                    $args->add($snakeKey, $value);
                }
            }
        }

        $args->asort();

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