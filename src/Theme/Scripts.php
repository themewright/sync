<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;

class Scripts
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
     * Handles WP script registration.
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
        foreach ($this->data->scripts as $script) {
            $chunk = $this->createChunk($script);
            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Creates a TW functions code chunk for a script.
     *
     * @param  mixed  $script
     * @return array
     */
    protected function createChunk($script)
    {
        $handleSnake = str_replace('-', '_', $script->handle);
        $src = strpos($script->src, 'http') === 0 || strpos($script->src, '//') ? "'{$script->src}'" : "get_template_directory_uri() . '/" . ltrim($script->src, '/') . "'";
        $deps = $script->deps ? 'array( \'' . implode("', '", $script->deps) . '\' )' : 'array()';

        if (empty($script->ver)) {
            $ver = 'null';
        } else if ($script->ver == 'time') {
            $ver = strpos($script->src, 'http') === 0 || strpos($script->src, '//') ? 'null' : "tw_filemtime( '{$script->src}' )";
        } else if ($script->ver == 'auto') {
            $ver = 'false';
        } else {
            $ver = "'{$script->ver}'";
        }

        $inFooter = $script->inFooter ? 'true' : 'false';

        $code = [
            "// Enqueue script: {$script->handle} (#{$script->id})",
            "function tw_enqueue_script_{$handleSnake}() {",
            "\twp_register_script( '{$script->handle}', {$src}, {$deps}, {$ver}, {$inFooter} );",
        ];

        if ($script->localize) {
            $code[] = "\twp_localize_script(";
            $code[] = "\t\t'{$script->handle}',";
            $code[] = "\t\t'{$script->localizationObject}',";

            if ($script->localizationData) {
                $code[] = "\t\tarray(";

                $args = new ArrayArgs();

                foreach ($script->localizationData as $data) {
                    $args->add(
                        $data->name,
                        '@php:' . ($data->default != '' ? $data->default : 'null')
                    );
                }

                $code = array_merge($code, $args->format(3, true));

                $code[] = "\t\t)";
            } else {
                $code[] = "\t\tarray()";
            }

            $code[] = "\t);";

        }

        $code[] = "\twp_enqueue_script( '{$script->handle}' );";
        $code[] = "}";
        $code[] = "add_action( 'wp_enqueue_scripts', 'tw_enqueue_script_{$handleSnake}' );";

        $chunk = [
            'type' => 'script',
            'code' => $code,
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}