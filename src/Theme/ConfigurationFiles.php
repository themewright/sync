<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;

class ConfigurationFiles
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
     * The theme functions instance.
     *
     * @var \ThemeWright\Sync\Theme\Functions
     */
    protected $functions;

    /**
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles the configuration files (Webpack, Gulp, Composer etc.).
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$functions, &$messages = [])
    {
        $this->wpUrl = $_ENV['TW_WP_URL'] ?? explode('/sync/webhook.php', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])[0];
        $this->themeSlug = preg_split('/\/\\\/', $themeDir);
        $this->themeSlug = end($this->themeSlug);
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->functions = &$functions;
        $this->messages = &$messages;
    }

    /**
     * Creates or updates the configuration files and adds TW functions code chunks.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->configurationFiles as $configurationFile) {
            $chunk = $this->createChunk($configurationFile);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Register configuration file: ([a-zA-Z0-9_\-\.]+) \(#[0-9]+\)/', $oldChunk['code'], $oldFilenameMatch);

                // Delete old files if the filename changed
                if ($oldFilenameMatch && $oldFilenameMatch[1] != $configurationFile->filename) {
                    $this->fs->file($oldFilenameMatch[1])->deleteWithMessages($this->messages);
                }
            }

            $file = $this->fs->file($configurationFile->filename);

            $fileContent = str_replace(
                [
                    '{{ TW_THEME_SLUG }}',
                    '{{ TW_THEME_VERSION }}',
                    '{{ TW_THEME_DESCRIPTION }}',
                    '{{ TW_ORGANIZATION_NAME }}',
                    '{{ TW_WP_URL }}',
                ],
                [
                    $this->themeSlug,
                    $this->data->version,
                    $this->data->description,
                    $this->data->organization->name,
                    $this->wpUrl,
                ],
                $configurationFile->content
            );

            $file->setContent($fileContent)->doubleSpacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes configuration files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\ConfigurationFiles
     */
    public function deleteExceptData()
    {
        $filenames = array_column($this->data->configurationFiles, 'filename');

        $fields = $this->fs->getThemeFiles();

        foreach ($fields as $field) {
            preg_match('/^([a-zA-Z0-9_\-\.]+\.(?:json|js))$/', $field->basename, $nameMatch);

            if ($nameMatch && !in_array($nameMatch[1], $filenames)) {
                $field->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk to include the configuration file.
     *
     * @param  mixed  $configurationFile
     * @return array
     */
    protected function createChunk($configurationFile)
    {
        $args = new ArrayArgs();

        $chunk = [
            'type' => 'configuration-file',
            'code' => [
                "// Register configuration file: {$configurationFile->filename} (#{$configurationFile->id})",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}