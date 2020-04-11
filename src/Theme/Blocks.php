<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\Element;
use ThemeWright\Sync\Component\Field;
use ThemeWright\Sync\Component\FieldGroup;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\Helper\Str;

class Blocks
{
    /**
     * The Filesystem instance.
     *
     * @var \ThemeWright\Sync\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * The request data.
     *
     * @var mixed
     */
    protected $data;

    /**
     * The theme functions instance.
     *
     * @var \ThemeWright\Sync\Theme\Functions
     */
    protected $functions;

    /**
     * The style.scss file instance.
     *
     * @var \ThemeWright\Sync\Theme\StylesScss
     */
    protected $stylesScss;

    /**
     * The main.js file instance.
     *
     * @var \ThemeWright\Sync\Theme\MainJs
     */
    protected $mainJs;

    /**
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles the content block files.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @param  \ThemeWright\Sync\Theme\StylesScss  $stylesScss
     * @param  \ThemeWright\Sync\Theme\MainJs  $mainJss
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$functions, &$stylesScss, &$mainJs, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->functions = &$functions;
        $this->stylesScss = &$stylesScss;
        $this->mainJs = &$mainJs;
        $this->messages = &$messages;
    }

    /**
     * Builds the content block files and their components.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->blocks as $block) {
            $chunk = $this->createChunk($block);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Register block: ([a-z0-9_]+) \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the block name changed
                if ($oldNameMatch && $oldNameMatch[1] != $block->name) {
                    $this->deleteFiles($oldNameMatch[1]);
                }
            }

            $slug = Str::slug($block->name);

            $fields = $this->fs->file('includes/blocks/fields-' . $slug . '.php');
            $view = $this->fs->file('views/blocks/' . $slug . '.php');
            $scss = $this->fs->file('assets/scss/blocks/_' . $slug . '.scss');
            $js = $this->fs->file('assets/js/blocks/' . $slug . '.js');

            $fieldsContent = $this->getFieldsContent($block);
            $fields->setContent($fieldsContent)->saveWithMessages($this->messages);

            if ($block->scss) {
                $scss->setContent($block->scss)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->stylesScss->addPartial('blocks/' . $slug);
            } else {
                $scss->deleteWithMessages($this->messages);
                $this->stylesScss->deletePartial('blocks/' . $slug);
            }

            if ($block->js) {
                $js->setContent($block->js)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->mainJs->addModule('./blocks/' . $slug);
            } else {
                $js->deleteWithMessages($this->messages);
                $this->mainJs->deleteModule('./blocks/' . $slug);
            }

            if ($block->viewRaw) {
                $viewContent = $block->viewRaw;
            } else {
                $elements = array_map(function ($args) use ($block) {
                    return (new Element($args, $this->data->domain, $block->templates, $block->parts))->parse();
                }, $block->view);

                $viewContent = implode(PHP_EOL, $elements);
            }

            $view->setContent($viewContent)->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Gets the content of the fields file.
     *
     * @param  mixed  $block
     * @return string
     */
    protected function getFieldsContent($block)
    {
        $php = [
            "<?php",
            "",
            "// Register ACF field group for block: {$block->name}",
            "TW_Block::register(",
            "\t__( '{$block->label}', '{$this->data->domain}' ),",
            "\t'{$block->name}',",
            "\tarray(",
        ];

        $fields = new ArrayArgs();

        foreach ($block->fields as $fieldArgs) {
            if (isset($fieldArgs->fieldSet)) {
                $i = array_search($fieldArgs->fieldSet, array_column($block->fieldSets, 'id'));

                foreach ($block->fieldSets[$i]->fields as $fieldSetFieldArgs) {
                    $field = (new Field($fieldSetFieldArgs, $block->fieldSets))->build(2, 'field_', 'ArrayArgs');
                    $fields->add('', $field);
                }
            } else {
                $field = (new Field($fieldArgs, $block->fieldSets))->build(2, 'field_', 'ArrayArgs');
                $fields->add('', $field);
            }
        }

        FieldGroup::fixConditionalLogics($fields);
        $fields->remove('tw_key', true)->remove('auto_name', true);

        $php[] = implode(PHP_EOL, $fields->format(2));
        $php[] = "\t)";
        $php[] = ");";

        return implode(PHP_EOL, $php);
    }

    /**
     * Deletes all files associated to a content block.
     *
     * This method does not delete TW functions code chunks, styles.scss and main.js.
     *
     * @param  string  $name
     * @return ThemeWright\Sync\Theme\Parts
     */
    public function deleteFiles(string $name)
    {
        $slug = Str::slug($name);

        $this->fs->file('includes/blocks/fields-' . $slug . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('views/blocks/' . $slug . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/blocks/_' . $slug . '.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/blocks/' . $slug . '.js')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes content blocks and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks, styles.scss and main.js.
     *
     * @return ThemeWright\Sync\Theme\Blocks
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->blocks, 'name');

        foreach ($names as $i => $name) {
            $names[$i] = str_replace('_', '-', $name);
        }

        $fieldsFiles = $this->fs->getThemeFiles('includes/blocks');

        foreach ($fieldsFiles as $fields) {
            preg_match('/^fields-([a-z0-9-]+)\.php$/', $fields->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $names)) {
                $fields->deleteWithMessages($this->messages);
            }
        }

        $assets = array_merge(
            $this->fs->getThemeFiles('assets/scss/blocks'),
            $this->fs->getThemeFiles('assets/js/blocks')
        );

        foreach ($assets as $asset) {
            preg_match('/^_?([a-z0-9-]+)\.(?:scss|js)$/', $asset->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $names)) {
                $asset->deleteWithMessages($this->messages);
            }
        }

        $views = $this->fs->getThemeFiles('views/blocks');

        foreach ($views as $view) {
            preg_match('/^([a-z0-9-]+)\.php$/', $view->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $names)) {
                $view->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a content block object.
     *
     * @param mixed $block
     * @return array
     */
    protected function createChunk($block)
    {
        $slug = Str::slug($block->name);

        $chunk = [
            'type' => 'block',
            'code' => [
                "// Register block: {$block->name} (#{$block->id})",
                "include get_template_directory() . '/includes/blocks/fields-{$slug}.php';",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}