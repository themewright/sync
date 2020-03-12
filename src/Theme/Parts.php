<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\View\Element;

class Parts
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
     * Handles the WP template part files.
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
     * Builds the template part files and their components.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->parts as $part) {
            $chunk = $this->createChunk($part);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Template part specific options: ([a-z0-9-]+)\.php \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the menu slug changed
                if ($oldNameMatch && $oldNameMatch[1] != $part->name) {
                    $this->deleteFiles($oldNameMatch[1]);
                }
            }

            $file = $this->fs->file('parts/' . $part->name . '.php');
            $scss = $this->fs->file('assets/scss/parts/_' . $part->name . '.scss');
            $js = $this->fs->file('assets/js/parts/' . $part->name . '.js');

            if ($part->scss) {
                $scss->setContent($part->scss)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->stylesScss->addPartial('parts/' . $part->name);
            } else {
                $scss->deleteWithMessages($this->messages);
                $this->stylesScss->deletePartial('parts/' . $part->name);
            }

            if ($part->js) {
                $js->setContent($part->js)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->mainJs->addModule('./parts/' . $part->name);
            } else {
                $js->deleteWithMessages($this->messages);
                $this->mainJs->deleteModule('./parts/' . $part->name);
            }

            if ($part->viewRaw) {
                $fileContent = $part->viewRaw;
            } else {
                $elements = array_map(function ($args) {
                    return (new Element($args))->parse();
                }, $part->view);

                $fileContent = implode(PHP_EOL, $elements);
            }

            $fileContent = $this->buildArgs($part->args) . PHP_EOL . $fileContent;

            $file->setContent($fileContent)->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Builds the template part arguments code.
     *
     * @param  array  $args
     * @return string
     */
    protected function buildArgs(array $args)
    {
        $php = [];

        if ($args) {
            $php[] = '<?php';

            foreach ($args as $arg) {
                $default = $arg->default ?: 'null';
                $php[] = "\t{$arg->name} = {$arg->name} ?? {$default};";
            }

            $php[] = '?' . '>';
        }

        return implode(PHP_EOL, $php);
    }

    /**
     * Deletes all files associated to a template part.
     *
     * This method does not delete TW functions, styles.scss and main.js code chunks.
     *
     * @param string $name
     * @return ThemeWright\Sync\Theme\Parts
     */
    public function deleteFiles(string $name)
    {
        $this->fs->file('parts/' . $name . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/parts/_' . $name . '.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/parts/' . $name . '.js')->deleteWithMessages($this->messages);

        return $this;
    }

/**
 * Deletes template parts and associated files which are not included in the current $data object.
 *
 * This method does not delete TW functions, styles.scss and main.js code chunks.
 *
 * @return ThemeWright\Sync\Theme\Parts
 */
    public function deleteExceptData()
    {
        $names = array_column($this->data->parts, 'name');

// Don't delete the functions files
        $names[] = 'functions';
        $names[] = 'tw-functions';

        $assets = array_merge(
            $this->fs->getThemeFiles('assets/scss/parts'),
            $this->fs->getThemeFiles('assets/js/parts')
        );

        foreach ($assets as $asset) {
            preg_match('/^_?([a-z0-9-]+)\.(?:scss|js)$/', $asset->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $names)) {
                $asset->deleteWithMessages($this->messages);
            }
        }

        $files = $this->fs->getThemeFiles('parts');

        foreach ($files as $file) {
            preg_match('/^([a-z0-9-]+)\.php$/', $file->basename, $partMatch);

            if ($partMatch && !in_array($partMatch[1], $names)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

/**
 * Creates a TW functions code chunk for a template part object.
 *
 * @param mixed $part
 * @return array
 */
    protected function createChunk($part)
    {
        $chunk = [
            'type' => 'part',
            'code' => [
                "// Template part specific options: {$part->name}.php (#{$part->id})",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}