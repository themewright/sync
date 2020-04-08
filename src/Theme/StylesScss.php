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
     * The SCSS partials.
     *
     * @var array
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
    public function __construct(string $themeDir, &$data, &$messages = [])
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
        preg_match_all('/@import "([a-z0-9\/_-]+)"; \/\/ ([0-9-]+)/', $this->file->getContent(), $matches);

        foreach ($matches[1] as $i => $path) {
            $numbers = explode('-', $matches[2][$i]);
            $priority = $numbers[0];
            $id = isset($numbers[1]) ? (int) $numbers[1] : null;

            $this->partials[$path] = (object) [
                'id' => $id,
                'path' => $path,
                'priority' => $priority,
            ];
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
     * @param  int  $id
     * @param  int  $priority
     * @return void
     */
    public function addPartial(string $path, int $id = null, int $priority = 1000)
    {
        $this->partials[$path] = (object) [
            'id' => $id,
            'path' => $path,
            'priority' => $priority,
        ];
    }

    /**
     * Gets a SCSS partial by its argument value.
     *
     * @param  string  $arg
     * @param  mixed  $value
     * @return array
     */
    public function getPartialBy(string $arg, $value)
    {
        $found = null;

        foreach ($this->partials as $partial) {
            if ($partial->$arg == $value) {
                $found = $partial;
                break;
            }
        }

        return $found;
    }

    /**
     * Deletes a SCSS partial by its path.
     *
     * @param  string  $path
     * @return void
     */
    public function deletePartial(string $path)
    {
        unset($this->partials[$path]);
    }

    /**
     * Deletes a SCSS partial by an argument value.
     *
     * @param  string  $arg
     * @param  mixed  $value
     * @return void
     */
    public function deletePartialBy(string $arg, $value)
    {
        $partial = $this->getPartialBy($arg, $value);

        if ($partial) {
            $this->deletePartial($partial->path);
        }
    }

    /**
     * Creates a new or updates an existing styles.scss file.
     *
     * @return void
     */
    public function build()
    {
        ksort($this->partials);

        usort($this->partials, function ($a, $b) {
            if ($a->priority == $b->priority) {
                return 0;
            }

            return $a->priority < $b->priority ? -1 : 1;
        });

        $content = [];

        foreach ($this->partials as $partial) {
            $content[] = '@import "' . $partial->path . '"; // ' . $partial->priority . ($partial->id ? '-' . $partial->id : '');
        }

        $this->file->setContent($content)->saveWithMessages($this->messages);
    }
}