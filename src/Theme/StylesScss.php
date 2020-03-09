<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class StylesScss
{
    /**
     * The styles.scss file.
     *
     * @var \ThemeWright\Sync\Filesystem\File
     */
    protected $file;

    /**
     * The SCSS partials paths.
     *
     * @var string[]
     */
    protected $partials = [];

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
     * Handles the styles.scss file.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data = false, &$messages = [])
    {
        $this->file = (new Filesystem($themeDir))->file('assets/scss/styles.scss');
        $this->data = &$data;
        $this->messages = &$messages;

        $this->parsePartials();
    }

    /**
     * Parses the style partials from the styles.scss file.
     *
     * @return void
     */
    protected function parsePartials()
    {
        preg_match_all('/@import "([a-z0-9\/-]+)";/', $this->file->getContent(), $matches);

        foreach ($matches[1] as $path) {
            $this->partials[] = $path;
        }
    }

    /**
     * Empties all import lines from the styles.scss file.
     *
     * @return void
     */
    public function emptyPartials()
    {
        $this->partials = [];
    }

    /**
     * Adds a new SCSS partial.
     *
     * @param  string  $path
     * @return void
     */
    public function addPartial(string $path)
    {
        if (!in_array($path, $this->partials)) {
            $this->partials[] = $path;
        }
    }

    /**
     * Deletes a SCSS partial.
     *
     * @param  string  $path
     * @return void
     */
    public function deletePartial(string $path)
    {
        $index = array_search($path, $this->partials);

        if ($index !== false) {
            unset($this->partials[$index]);
        }
    }

    /**
     * Creates a new or updates an existing styles.scss file.
     *
     * @return void
     */
    public function build()
    {
        $content = [];

        foreach ($this->partials as $partial) {
            $content[] = '@import "' . $partial . '";';
        }

        $this->file->setContent($content)->saveWithMessages($this->messages);
    }
}