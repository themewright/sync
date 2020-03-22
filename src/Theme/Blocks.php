<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\Element;
use ThemeWright\Sync\Component\Field;
use ThemeWright\Sync\Filesystem\Filesystem;
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
    public function __construct(string $themeDir, &$data = false, &$functions, &$stylesScss, &$mainJs, &$messages = [])
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
                preg_match('/\/\/ Include block: ([a-z0-9-]+) \(#[0-9]+\)/', $oldChunk['code'], $oldSlugMatch);

                // Delete old files if the block slug changed
                if ($oldSlugMatch && $oldSlugMatch[1] != $block->slug) {
                    $this->deleteFiles($oldSlugMatch[1]);
                }
            }

            $class = $this->fs->file('includes/blocks/class-' . $block->slug . '.php');
            $view = $this->fs->file('views/blocks/' . $block->slug . '.php');
            $scss = $this->fs->file('assets/scss/blocks/_' . $block->slug . '.scss');
            $js = $this->fs->file('assets/js/blocks/' . $block->slug . '.js');

            $classContent = $this->getClassContent($block);
            $class->setContent($classContent)->saveWithMessages($this->messages);

            if ($block->scss) {
                $scss->setContent($block->scss)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->stylesScss->addPartial('blocks/' . $block->slug);
            } else {
                $scss->deleteWithMessages($this->messages);
                $this->stylesScss->deletePartial('blocks/' . $block->slug);
            }

            if ($block->js) {
                $js->setContent($block->js)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->mainJs->addModule('./blocks/' . $block->slug);
            } else {
                $js->deleteWithMessages($this->messages);
                $this->mainJs->deleteModule('./blocks/' . $block->slug);
            }

            if ($block->viewRaw) {
                $viewContent = $block->viewRaw;
            } else {
                $elements = array_map(function ($args) use ($block) {
                    return (new Element($args, $block->templates, $block->parts))->parse();
                }, $block->view);

                $viewContent = implode(PHP_EOL, $elements);
            }

            $view->setContent($viewContent)->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Gets the content of the block class file.
     *
     * @param  mixed  $block
     * @return string
     */
    protected function getClassContent($block)
    {
        $classname = Str::studly($block->slug);

        $php = [
            "<?php",
            "",
            "namespace ThemeWright\\Blocks;",
            "",
            "/**",
            " * Handles the {$block->name} block.",
            " */",
            "class {$classname} {",
            "\t/**",
            "\t * Class constructor.",
            "\t */",
        ];

        if ($block->fields) {
            $php[] = "\tpublic function __construct() {";
            $php[] = "\t\tadd_action( 'acf/init', array( \$this, 'register_fields' ) );";
            $php[] = "\t}";
            $php[] = "";
            $php[] = "\t/**";
            $php[] = "\t * Registers the ACF fields.";
            $php[] = "\t *";
            $php[] = "\t * @return void";
            $php[] = "\t */";
            $php[] = "\tpublic function register_fields() {";
            $php[] = "\t\tacf_add_local_field_group(";
            $php[] = "\t\t\tarray(";
            $php[] = "\t\t\t\t'key'    => 'group_block_{$block->id}',";
            $php[] = "\t\t\t\t'title'  => '{$block->name}',";
            $php[] = "\t\t\t\t'fields' => array(";

            foreach ($block->fields as $field) {
                if ($field->type == 'field_group') {
                    // @todo get fields from the group
                } else {
                    $php[] = (new Field($field))->build(5, "field_block_{$block->id}_");
                }
            }

            $php[] = "\t\t\t\t),";
            $php[] = "\t\t\t)";
            $php[] = "\t\t);";
            $php[] = "\t}";
        } else {
            $php[] = "\tpublic function __construct() { }";
        }

        $php[] = "}";

        return implode(PHP_EOL, $php);
    }

    /**
     * Deletes all files associated to a content block.
     *
     * This method does not delete TW functions, styles.scss and main.js code chunks.
     *
     * @param  string  $slug
     * @return ThemeWright\Sync\Theme\Parts
     */
    public function deleteFiles(string $slug)
    {
        $this->fs->file('includes/blocks/class-' . $slug . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('views/blocks/' . $slug . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/blocks/_' . $slug . '.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/blocks/' . $slug . '.js')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes content blocks and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions, styles.scss and main.js code chunks.
     *
     * @return ThemeWright\Sync\Theme\Blocks
     */
    public function deleteExceptData()
    {
        $slugs = array_column($this->data->blocks, 'slug');

        $classes = $this->fs->getThemeFiles('includes/blocks');

        foreach ($classes as $class) {
            preg_match('/^class-([a-z0-9-]+)\.php$/', $class->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $slugs)) {
                $class->deleteWithMessages($this->messages);
            }
        }

        $assets = array_merge(
            $this->fs->getThemeFiles('assets/scss/blocks'),
            $this->fs->getThemeFiles('assets/js/blocks')
        );

        foreach ($assets as $asset) {
            preg_match('/^_?([a-z0-9-]+)\.(?:scss|js)$/', $asset->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $slugs)) {
                $asset->deleteWithMessages($this->messages);
            }
        }

        $views = $this->fs->getThemeFiles('views/blocks');

        foreach ($views as $view) {
            preg_match('/^([a-z0-9-]+)\.php$/', $view->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $slugs)) {
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
        $classname = Str::studly($block->slug);

        $chunk = [
            'type' => 'block',
            'code' => [
                "// Include block: {$block->slug} (#{$block->id})",
                "include get_template_directory() . '/includes/blocks/class-{$block->slug}.php';",
                "new ThemeWright\\Blocks\\{$classname}();",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}