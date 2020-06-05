<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;

class Actions
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
     * Handles WP actions.
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
     * Builds the action file.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->actions as $action) {
            $chunk = $this->createChunk($action);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Add action: ([a-z0-9_]+) \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the action name changed
                if ($oldNameMatch && $oldNameMatch[1] != $action->name) {
                    $this->fs->file('includes/actions/action-' . Str::slug($oldNameMatch[1]) . '.php')->deleteWithMessages($this->messages);
                }
            }

            $file = $this->fs->file('includes/actions/action-' . Str::slug($action->name) . '.php');

            $file->setContent($action->php)->spacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes action associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\Actions
     */
    public function deleteExceptData()
    {
        $slugs = array_column($this->data->actions, 'name');

        foreach ($slugs as $i => $name) {
            $slugs[$i] = Str::slug($name);
        }

        $files = $this->fs->getThemeFiles('includes/actions');

        foreach ($files as $file) {
            preg_match('/^action-([a-z0-9-]+)\.php$/', $file->basename, $actionMatch);

            if ($actionMatch && !in_array($actionMatch[1], $slugs)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for an action.
     *
     * @param  mixed  $action
     * @return array
     */
    protected function createChunk($action)
    {
        $slug = Str::slug($action->name);

        $chunk = [
            'type' => 'action',
            'code' => [
                "// Add action: $action->name (#{$action->id})",
                "include get_template_directory() . '/includes/actions/action-{$slug}.php';",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}