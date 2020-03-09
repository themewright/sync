<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class Bundlers
{
    /**
     * The WordPress instance URL.
     *
     * @var string
     */
    protected $wpUrl;

    /**
     * The theme slug.
     *
     * @var string
     */
    protected $themeSlug;

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
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles the npm, gulpfile webpack configuration files.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data = false, &$messages = [])
    {
        $this->wpUrl = $_ENV['TW_WP_URL'] ?? explode('/sync/webhook.php', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])[0];
        $this->themeSlug = preg_split('/\/\\\/', $themeDir);
        $this->themeSlug = end($this->themeSlug);
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->messages = &$messages;
    }

    /**
     * Replaces the placeholders and copies the JS(ON) files to the theme's root directory.
     *
     * @return void
     */
    public function build()
    {
        $packageJson = str_replace(
            [
                '{{ TW_THEME_SLUG }}',
                '{{ TW_THEME_VERSION }}',
                '{{ TW_THEME_DESCRIPTION }}',
                '{{ TW_ORGANIZATION_NAME }}',
            ],
            [
                $this->themeSlug,
                $this->data->version,
                $this->data->description,
                $this->data->organization->name,
            ],
            $this->data->bundlers->packageJson
        );

        $gulpfileJs = str_replace(
            '{{ TW_WP_URL }}',
            $this->wpUrl,
            $this->data->bundlers->gulpfileJs
        );

        $webpackConfigJs = $this->data->bundlers->webpackConfigJs;

        $this->fs->file('package.json')->setContent($packageJson)->doubleSpacesToTabs()->saveWithMessages($this->messages);
        $this->fs->file('gulpfile.js')->setContent($gulpfileJs)->doubleSpacesToTabs()->saveWithMessages($this->messages);
        $this->fs->file('webpack.config.js')->setContent($webpackConfigJs)->doubleSpacesToTabs()->saveWithMessages($this->messages);
    }
}