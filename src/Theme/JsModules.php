<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class JsModules
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
     * Handles JS modules.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\MainJs  $mainJs
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$mainJs, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->mainJs = &$mainJs;
        $this->messages = &$messages;
    }

    /**
     * Builds the JS module file and adds it to the main.js.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->jsModules as $jsModule) {
            $oldPartial = $this->mainJs->getModuleBy('id', $jsModule->id);

            if ($oldPartial && $oldPartial->path != './modules/' . $jsModule->name) {
                $oldPartialPathParts = explode('/', $oldPartial->path);
                $this->fs->file('assets/js/modules/' . end($oldPartialPathParts) . '.js')->deleteWithMessages($this->messages);

                $this->mainJs->deleteModule($oldPartial->path);
            }

            $js = $this->fs->file('assets/js/modules/' . $jsModule->name . '.js');
            $js->setContent($jsModule->js)->doubleSpacesToTabs()->saveWithMessages($this->messages);

            if ($jsModule->import) {
                $this->mainJs->addModule('./modules/' . $jsModule->name, $jsModule->id, $jsModule->priority);
            } else {
                $this->mainJs->deleteModule('./modules/' . $jsModule->name);
            }
        }
    }

    /**
     * Deletes JS modules which are not included in the current $data object.
     *
     * @return ThemeWright\Sync\Theme\JsModules
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->jsModules, 'name');

        $files = $this->fs->getThemeFiles('assets/js/modules');

        foreach ($files as $file) {
            preg_match('/^([a-z0-9\.-]+)\.js$/', $file->basename, $match);

            if ($match && !in_array($match[1], $names)) {
                $file->deleteWithMessages($this->messages);
                $this->mainJs->deleteModule('./modules/' . $match[1]);
            }
        }

        return $this;
    }
}