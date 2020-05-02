<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;

class Ajaxes
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
     * Handles WP Ajax actions.
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
     * Builds the Ajax file.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->ajax as $ajax) {
            $chunk = $this->createChunk($ajax);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Add Ajax: ([a-z0-9_]+) \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the action name changed
                if ($oldNameMatch && $oldNameMatch[1] != $ajax->action) {
                    $this->fs->file('includes/ajax/ajax-' . Str::slug($oldNameMatch[1]) . '.php')->deleteWithMessages($this->messages);
                }
            }

            $file = $this->fs->file('includes/ajax/ajax-' . Str::slug($ajax->action) . '.php');

            $file->setContent($ajax->php)->spacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes Ajax associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\Ajax
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->ajax, 'action');

        foreach ($names as $i => $name) {
            $names[$i] = str_replace('_', '-', $name);
        }

        $files = $this->fs->getThemeFiles('includes/ajax');

        foreach ($files as $file) {
            preg_match('/^ajax-([a-z0-9-]+)\.php$/', $file->basename, $ajaxMatch);

            if ($ajaxMatch && !in_array($ajaxMatch[1], $names)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for an Ajax action.
     *
     * @param  mixed  $ajax
     * @return array
     */
    protected function createChunk($ajax)
    {
        $slug = Str::slug($ajax->action);

        $chunk = [
            'type' => 'ajax',
            'code' => [
                "// Add Ajax: $ajax->action (#{$ajax->id})",
                "include get_template_directory() . '/includes/ajax/ajax-{$slug}.php';",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}