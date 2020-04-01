<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class MainJs
{
    /**
     * The main.js file.
     *
     * @var \ThemeWright\Sync\Filesystem\File
     */
    protected $file;

    /**
     * The JS modules.
     *
     * @var string[]
     */
    protected $modules = [];

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
    public function __construct(string $themeDir, &$data, &$messages = [])
    {
        $this->file = (new Filesystem($themeDir))->file('assets/js/main.js');
        $this->data = &$data;
        $this->messages = &$messages;

        $this->parseModules();
    }

    /**
     * Parses the JS modules from the main.js file.
     *
     * @return void
     */
    protected function parseModules()
    {
        preg_match_all('/import \'([a-z0-9\/\.-]+)\';/', $this->file->getContent(), $matches);

        foreach ($matches[1] as $path) {
            $this->modules[] = $path;
        }
    }

    /**
     * Empties all require lines from the main.js file.
     *
     * @return void
     */
    public function emptyModules()
    {
        $this->modules = [];
    }

    /**
     * Adds a new JS module.
     *
     * @param  string  $path
     * @return void
     */
    public function addModule(string $path)
    {
        if (!in_array($path, $this->modules)) {
            $this->modules[] = $path;
        }
    }

    /**
     * Deletes a JS module.
     *
     * @param  string  $path
     * @return void
     */
    public function deleteModule(string $path)
    {
        $index = array_search($path, $this->modules);

        if ($index !== false) {
            unset($this->modules[$index]);
        }
    }

    /**
     * Creates a new or updates an existing main.js file.
     *
     * @return void
     */
    public function build()
    {
        $content = [];

        foreach ($this->modules as $module) {
            $content[] = "import '{$module}';";
        }

        $this->file->setContent($content)->saveWithMessages($this->messages);
    }
}
