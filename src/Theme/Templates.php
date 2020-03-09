<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\View\Element;

class Templates
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
     * Handles the WP template files.
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
     * Builds the template files and their components.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->templates as $template) {
            $chunk = $this->createChunk($template);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Template specific options: ([a-z0-9-]+)\.php \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the menu slug changed
                if ($oldNameMatch && $oldNameMatch[1] != $template->name) {
                    $this->deleteFiles($oldNameMatch[1]);
                }
            }

            $file = $this->fs->file($template->name . '.php');
            $scss = $this->fs->file('assets/scss/templates/_' . $template->name . '.scss');
            $js = $this->fs->file('assets/js/templates/' . $template->name . '.js');

            if ($template->scss) {
                $scss->setContent($template->scss)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->stylesScss->addPartial('templates/' . $template->name);
            } else {
                $scss->deleteWithMessages($this->messages);
                $this->stylesScss->deletePartial('templates/' . $template->name);
            }

            if ($template->js) {
                $js->setContent($template->js)->doubleSpacesToTabs()->saveWithMessages($this->messages);
                $this->mainJs->addModule('./templates/' . $template->name);
            } else {
                $js->deleteWithMessages($this->messages);
                $this->mainJs->deleteModule('./templates/' . $template->name);
            }

            if ($template->viewRaw) {
                $fileContent = $template->viewRaw;
            } else {
                $elements = array_map(function ($args) {
                    return (new Element($args))->parse();
                }, $template->view);

                $fileContent = implode(PHP_EOL, $elements);
            }

            $file->setContent($fileContent)->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes all files associated to a template.
     *
     * This method does not delete TW functions, styles.scss and main.js code chunks.
     *
     * @param  string  $name
     * @return ThemeWright\Sync\Theme\Templates
     */
    public function deleteFiles(string $name)
    {
        $this->fs->file($name . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/templates/_' . $name . '.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/templates/' . $name . '.js')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes templates and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions, styles.scss and main.js code chunks.
     *
     * @return ThemeWright\Sync\Theme\Templates
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->templates, 'name');

        // Don't delete the functions files
        $names[] = 'functions';
        $names[] = 'tw-functions';

        $assets = array_merge(
            $this->fs->getThemeFiles('assets/scss/templates'),
            $this->fs->getThemeFiles('assets/js/templates')
        );

        foreach ($assets as $asset) {
            preg_match('/^_?([a-z0-9-]+)\.(?:scss|js)$/', $asset->basename, $templateMatch);

            if ($templateMatch && !in_array($templateMatch[1], $names)) {
                $asset->deleteWithMessages($this->messages);
            }
        }

        $files = $this->fs->getThemeFiles();

        foreach ($files as $file) {
            preg_match('/^([a-z0-9-]+)\.php$/', $file->basename, $templateMatch);

            if ($templateMatch && !in_array($templateMatch[1], $names)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a template object.
     *
     * @param  mixed  $template
     * @return array
     */
    protected function createChunk($template)
    {
        $chunk = [
            'type' => 'template',
            'code' => [
                "// Template specific options: {$template->name}.php (#{$template->id})",
            ],
        ];

        if ($template->type == 'template') {
            // @todo
        }

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}