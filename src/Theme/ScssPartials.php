<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class ScssPartials
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
     * The style.scss file instance.
     *
     * @var \ThemeWright\Sync\Theme\StylesScss
     */
    protected $stylesScss;

    /**
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles SCSS partials.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\StylesScss  $stylesScss
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$stylesScss, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->stylesScss = &$stylesScss;
        $this->messages = &$messages;
    }

    /**
     * Builds the SCSS partial file and adds it to the styles.scss.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->scssPartials as $scssPartial) {
            $oldPartial = $this->stylesScss->getPartialBy('id', $scssPartial->id);

            if ($oldPartial && $oldPartial->path != 'partials/' . $scssPartial->name) {
                $oldPartialPathParts = explode('/', $oldPartial->path);
                $this->fs->file('assets/scss/partials/_' . end($oldPartialPathParts) . '.scss')->deleteWithMessages($this->messages);

                $this->stylesScss->deletePartial($oldPartial->path);
            }

            $scss = $this->fs->file('assets/scss/partials/_' . $scssPartial->name . '.scss');
            $scss->setContent($scssPartial->scss)->spacesToTabs()->saveWithMessages($this->messages);

            $this->stylesScss->addPartial('partials/' . $scssPartial->name, $scssPartial->id, $scssPartial->priority);
        }
    }

    /**
     * Deletes SCSS partials which are not included in the current $data object.
     *
     * @return ThemeWright\Sync\Theme\ScssPartials
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->scssPartials, 'name');

        $files = $this->fs->getThemeFiles('assets/scss/partials');

        foreach ($files as $file) {
            preg_match('/^_([a-z0-9-]+)\.scss$/', $file->basename, $match);

            if ($match && !in_array($match[1], $names)) {
                $file->deleteWithMessages($this->messages);
                $this->stylesScss->deletePartial('partials/' . $match[1]);
            }
        }

        return $this;
    }
}