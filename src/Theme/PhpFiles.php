<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;

class PhpFiles
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
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles the custom PHP files.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$functions, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->functions = &$functions;
        $this->messages = &$messages;
    }

    /**
     * Creates or updates the custom PHP files and adds TW functions code chunks.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->phpFiles as $phpFile) {
            $chunk = $this->createChunk($phpFile);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Include file: ([a-zA-Z0-9_-]+\.php) \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the name changed
                if ($oldNameMatch && $oldNameMatch[1] != $phpFile->name) {
                    $this->fs->file('includes/custom/' . $oldNameMatch[1])->deleteWithMessages($this->messages);
                }
            }

            $file = $this->fs->file('includes/custom/' . $phpFile->name);

            $file->setContent($phpFile->php)->doubleSpacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes custom PHP files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\PhpFiles
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->phpFiles, 'name');

        $fields = $this->fs->getThemeFiles('includes/custom');

        foreach ($fields as $field) {
            preg_match('/^([a-zA-Z0-9_-]+\.php)$/', $field->basename, $nameMatch);

            if ($nameMatch && !in_array($nameMatch[1], $names)) {
                $field->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk to include the custom PHP file.
     *
     * @param  mixed  $phpFile
     * @return array
     */
    protected function createChunk($phpFile)
    {
        $args = new ArrayArgs();

        $chunk = [
            'type' => 'php-file',
            'code' => [
                "// Include file: {$phpFile->name} (#{$phpFile->id})",
            ],
        ];

        if ($phpFile->include) {
            $chunk['code'][] = "include get_template_directory() . '/includes/custom/{$phpFile->name}';";
        }

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}