<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class Styles
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
     * Handles WP styles registration.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$functions)
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->functions = &$functions;
    }

    /**
     * Adds and removes TW functions code chunks.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->styles as $style) {
            $chunk = $this->createChunk($style);
            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Creates a TW functions code chunk for a style.
     *
     * @param  mixed  $style
     * @return array
     */
    protected function createChunk($style)
    {
        $handleSnake = str_replace('-', '_', $style->handle);
        $src = strpos($style->src, 'http') === 0 || strpos($style->src, '//') ? "'{$style->src}'" : "get_template_directory_uri() . '/" . ltrim($style->src, '/') . "'";
        $deps = $style->deps ? 'array( \'' . implode("', '", $style->deps) . '\' )' : 'array()';

        if (empty($style->ver)) {
            $ver = 'null';
        } else if ($style->ver == 'time') {
            $ver = strpos($style->src, 'http') === 0 || strpos($style->src, '//') ? 'null' : "tw_filemtime( '{$style->src}' )";
        } else if ($style->ver == 'auto') {
            $ver = 'false';
        } else {
            $ver = "'{$style->ver}'";
        }

        $chunk = [
            'type' => 'style',
            'code' => [
                "// Enqueue CSS stylesheet: {$style->handle} (#{$style->id})",
                "function tw_enqueue_style_{$handleSnake}() {",
                "\twp_enqueue_style( '{$style->handle}', {$src}, {$deps}, {$ver}, '{$style->media}' );",
                "}",
                "add_action( 'wp_enqueue_scripts', 'tw_enqueue_style_{$handleSnake}' );",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}
