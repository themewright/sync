<?php

namespace ThemeWright\Sync\Component;

use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\Helper\Str;

class FieldGroup
{
    /**
     * The raw ACF fields settings from the app.
     *
     * @var array
     */
    protected $fields;

    /**
     * The ID of the field group used in 'key' arguments.
     *
     * @var string
     */
    protected $id;

    /**
     * The title of the fields meta box.
     *
     * @var string
     */
    protected $title;

    /**
     * The location rules for the meta box.
     *
     * @var array
     */
    protected $location;

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
     * Handles an ACF field group.
     *
     * @param  array  $args
     * @return void
     */
    public function __construct($args, $fieldSets)
    {
        $this->fields = $args['fields'] ?? [];
        $this->id = $args['id'] ?? Str::random(12);
        $this->title = $args['title'] ?? '';
        $this->location = $args['location'] ?? [];
        $this->menu_order = $args['menu_order'] ?? 0;
        $this->position = $args['position'] ?? 'normal';
        $this->style = $args['style'] ?? 'default';
        $this->label_placement = $args['label_placement'] ?? 'top';
        $this->hide_on_screen = $args['hide_on_screen'] ?? '';
        $this->active = $args['active'] ?? true;
        $this->description = $args['description'] ?? '';
        $this->args = $args;
        $this->fieldSets = $fieldSets;
    }

    /**
     * Builds PHP code for the ACF field group.
     *
     * @param  int  $indent
     * @return string
     */
    public function build(int $indent = 0)
    {
        $args = new ArrayArgs();
        $fields = new ArrayArgs();

        foreach ($this->fields as $fieldArgs) {
            if (isset($fieldArgs->fieldSet)) {
                $i = array_search($fieldArgs->fieldSet, array_column($this->fieldSets, 'id'));

                foreach ($this->fieldSets[$i]->fields as $fieldSetFieldArgs) {
                    $field = (new Field($fieldSetFieldArgs, $this->fieldSets))->build($indent + 1, "field_{$this->id}_", 'ArrayArgs');
                    $fields->add('', $field);
                }
            } else {
                $field = (new Field($fieldArgs, $this->fieldSets))->build($indent + 1, "field_{$this->id}_", 'ArrayArgs');
                $fields->add('', $field);
            }
        }

        $args->add('key', "group_{$this->id}");
        $args->add('title', $this->title);
        $args->add('fields', $fields);
        $args->add('location', $this->location);
        $args->add('menu_order', $this->menu_order);
        $args->add('position', $this->position);
        $args->add('style', $this->style);
        $args->add('label_placement', $this->label_placement);
        $args->add('hide_on_screen', $this->hide_on_screen);
        $args->add('active', $this->active);
        $args->add('description', $this->description);

        $lines = [
            str_repeat("\t", $indent) . "if ( function_exists( 'acf_add_local_field_group' ) ) {",
            str_repeat("\t", $indent + 1) . "acf_add_local_field_group(",
            str_repeat("\t", $indent + 2) . "array(",
            implode("\n", $args->format($indent + 3)),
            str_repeat("\t", $indent + 2) . ")",
            str_repeat("\t", $indent + 1) . ");",
            str_repeat("\t", $indent) . "}",
        ];

        return implode(PHP_EOL, $lines);
    }
}