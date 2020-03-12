<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\File;
use ThemeWright\Sync\Filesystem\Filesystem;

class Includes
{
    /**
     * The Filesystem instance.
     *
     * @var \ThemeWright\Sync\Filesystem\Filesystem
     */
    protected $fs;

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
     * Handles the class files from the /includes directory.
     *
     * @param  string  $themeDir
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$functions, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->functions = &$functions;
        $this->messages = &$messages;
    }

    /**
     * Creates or updates the files from the /includes directory and adds a TW functions code chunk.
     *
     * @return void
     */
    public function build()
    {
        $sourcePaths = glob(__DIR__ . '/../../templates/includes/tw/*.php');

        $files = [];

        $chunk = [
            'type' => 'includes',
            'code' => [
                '// Include the ThemeWright files',
            ],
        ];

        foreach ($sourcePaths as $path) {
            $path = realpath($path);
            $basename = basename($path);

            $files[$basename] = [
                'source' => new File($path),
                'destination' => $this->fs->file('includes/tw/' . $basename),
            ];
        }

        $existingFiles = $this->fs->getThemeFiles('includes');

        foreach ($existingFiles as $existingFile) {
            if (!isset($files[$existingFile->basename])) {
                $existingFile->deleteWithMessages($this->messages);
            }
        }

        foreach ($files as $file) {
            $content = $file['source']->getContent();
            $file['destination']->setContent($content)->saveWithMessages($this->messages);
            $chunk['code'][] = "include get_stylesheet_directory() . '/{$file['destination']->pathInTheme}';";
        }

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        $this->functions->addChunk($chunk);
    }
}