<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\Element;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\Helper\Str;

class Shortcodes
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
     * Handles the WP shortcodes.
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
     * Builds the shortcode files and their components.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->shortcodes as $shortcode) {
            $chunk = $this->createChunk($shortcode);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Add shortcode: ([a-z0-9_]+) \(#[0-9]+\)/', $oldChunk['code'], $oldTagMatch);

                // Delete old files if shortcode tag changed
                if ($oldTagMatch && $oldTagMatch[1] != $shortcode->tag) {
                    $this->deleteFiles($oldTagMatch[1]);
                }
            }

            $slug = Str::slug($shortcode->tag);

            $view = $this->fs->file('views/shortcodes/' . $slug . '.php');
            $scss = $this->fs->file('assets/scss/shortcodes/_' . $slug . '.scss');
            $js = $this->fs->file('assets/js/shortcodes/' . $slug . '.js');

            if ($shortcode->scss) {
                $scss->setContent($shortcode->scss)->spacesToTabs()->saveWithMessages($this->messages);
                $this->stylesScss->addPartial('shortcodes/' . $slug);
            } else {
                $scss->deleteWithMessages($this->messages);
                $this->stylesScss->deletePartial('shortcodes/' . $slug);
            }

            if ($shortcode->js) {
                $js->setContent($shortcode->js)->spacesToTabs()->saveWithMessages($this->messages);
                $this->mainJs->addModule('./shortcodes/' . $slug);
            } else {
                $js->deleteWithMessages($this->messages);
                $this->mainJs->deleteModule('./shortcodes/' . $slug);
            }

            if ($shortcode->viewRaw) {
                $viewContent = $shortcode->viewRaw;
            } else {
                $elements = array_map(function ($args) use ($shortcode) {
                    return (new Element($args, $this->data->domain, [], $shortcode->parts))->parse();
                }, $shortcode->view);

                $viewContent = implode(PHP_EOL, $elements);
            }

            $view->setContent($viewContent)->spacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes all files associated to a shortcode.
     *
     * This method does not delete TW functions code chunks.
     *
     * @param  string  $tag
     * @return ThemeWright\Sync\Theme\Shortcodes
     */
    public function deleteFiles(string $tag)
    {
        $slug = Str::slug($tag);

        $this->fs->file('views/shortcodes/' . $slug . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/shortcodes/_' . $slug . '.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/shortcodes/' . $slug . '.js')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes shortcodes and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\Shortcodes
     */
    public function deleteExceptData()
    {
        $slugs = array_column($this->data->shortcodes, 'tag');

        foreach ($slugs as $i => $tag) {
            $slugs[$i] = Str::slug($tag);
        }

        $assets = array_merge(
            $this->fs->getThemeFiles('assets/scss/shortcodes'),
            $this->fs->getThemeFiles('assets/js/shortcodes')
        );

        foreach ($assets as $asset) {
            preg_match('/^_?([a-z0-9-]+)\.(?:scss|js)$/', $asset->basename, $shortcodeMatch);

            if ($shortcodeMatch && !in_array($shortcodeMatch[1], $slugs)) {
                $asset->deleteWithMessages($this->messages);
            }
        }

        $views = $this->fs->getThemeFiles('views/shortcodes');

        foreach ($views as $view) {
            preg_match('/^([a-z0-9-]+)\.php$/', $view->basename, $shortcodeMatch);

            if ($shortcodeMatch && !in_array($shortcodeMatch[1], $slugs)) {
                $view->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a shortcode object.
     *
     * @param  mixed  $shortcode
     * @return array
     */
    protected function createChunk($shortcode)
    {
        $slug = Str::slug($shortcode->tag);

        $chunk = [
            'type' => 'shortcode',
            'code' => [
                "// Add shortcode: {$shortcode->tag} (#{$shortcode->id})",
            ],
        ];

        if ($shortcode->atts || $shortcode->content) {
            $chunk['code'][] = "function tw_add_shortcode_{$shortcode->tag}( \$atts, \$content = '' ) {";
        } else {
            $chunk['code'][] = "function tw_add_shortcode_{$shortcode->tag}() {";
        }

        $chunk['code'][] = "\tglobal \$postmeta, \$_postmeta, \$option;";

        if ($shortcode->atts) {
            $chunk['code'][] = "\t\$atts = shortcode_atts( array(";

            $args = new ArrayArgs();

            foreach ($shortcode->atts as $att) {
                $args->add(
                    trim($att->name, '\'"'),
                    '@php:' . ($att->default != '' ? $att->default : "''")
                );
            }

            $chunk['code'] = array_merge($chunk['code'], $args->format(2));

            $chunk['code'][] = "\t), \$atts );";
        }

        $chunk['code'][] = "\tob_start();";
        $chunk['code'][] = "\tinclude get_template_directory() . '/views/shortcodes/{$slug}.php';";
        $chunk['code'][] = "\treturn ob_get_clean();";
        $chunk['code'][] = "}";
        $chunk['code'][] = "add_shortcode( '{$shortcode->tag}', 'tw_add_shortcode_{$shortcode->tag}' );";

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}