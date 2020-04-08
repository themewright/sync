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
        preg_match_all('/import \'([a-z0-9\/\.-]+)\'; \/\/ ([0-9-]+)/', $this->file->getContent(), $matches);

        foreach ($matches[1] as $i => $path) {
            $numbers = explode('-', $matches[2][$i]);
            $priority = $numbers[0];
            $id = isset($numbers[1]) ? (int) $numbers[1] : null;

            $this->modules[$path] = (object) [
                'id' => $id,
                'path' => $path,
                'priority' => $priority,
            ];
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
     * @param  int  $id
     * @param  int  $priority
     * @return void
     */
    public function addModule(string $path, int $id = null, int $priority = 1000)
    {
        $this->modules[$path] = (object) [
            'id' => $id,
            'path' => $path,
            'priority' => $priority,
        ];
    }

    /**
     * Gets a JS module by its argument value.
     *
     * @param  string  $arg
     * @param  mixed  $value
     * @return array
     */
    public function getModuleBy(string $arg, $value)
    {
        $found = null;

        foreach ($this->modules as $module) {
            if ($module->$arg == $value) {
                $found = $module;
                break;
            }
        }

        return $found;
    }

    /**
     * Deletes a JS module by its path.
     *
     * @param  string  $path
     * @return void
     */
    public function deleteModule(string $path)
    {
        unset($this->modules[$path]);
    }

    /**
     * Deletes a JS module by an argument value.
     *
     * @param  string  $arg
     * @param  mixed  $value
     * @return void
     */
    public function deleteModuleBy(string $arg, $value)
    {
        $module = $this->getModuleBy($arg, $value);

        if ($module) {
            $this->deleteModule($module->path);
        }
    }

    /**
     * Creates a new or updates an existing main.js file.
     *
     * @return void
     */
    public function build()
    {
        ksort($this->modules);

        usort($this->modules, function ($a, $b) {
            if ($a->priority == $b->priority) {
                return 0;
            }

            return $a->priority < $b->priority ? -1 : 1;
        });

        $content = [];

        foreach ($this->modules as $module) {
            $content[] = "import '" . $module->path . "'; // " . $module->priority . ($module->id ? '-' . $module->id : '');
        }

        $this->file->setContent($content)->saveWithMessages($this->messages);
    }
}