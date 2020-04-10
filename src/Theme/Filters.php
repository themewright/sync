<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;

class Filters
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
     * Handles WP filters.
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
     * Builds the filter file.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->filters as $filter) {
            $chunk = $this->createChunk($filter);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Add filter: ([a-z0-9_]+) \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the filter name changed
                if ($oldNameMatch && $oldNameMatch[1] != $filter->name) {
                    $this->fs->file('includes/filters/filter-' . Str::slug($oldNameMatch[1]) . '.php')->deleteWithMessages($this->messages);
                }
            }

            $file = $this->fs->file('includes/filters/filter-' . Str::slug($filter->name) . '.php');

            $fileContent = [
                "<?php",
                $filter->php,
            ];

            $file->setContent($fileContent)->doubleSpacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes filter associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\Filters
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->filters, 'name');

        foreach ($names as $i => $name) {
            $names[$i] = str_replace('_', '-', $name);
        }

        $files = $this->fs->getThemeFiles('includes/filters');

        foreach ($files as $file) {
            preg_match('/^filter-([a-z0-9-]+)\.php$/', $file->basename, $filterMatch);

            if ($filterMatch && !in_array($filterMatch[1], $names)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a filter.
     *
     * @param  mixed  $filter
     * @return array
     */
    protected function createChunk($filter)
    {
        $slug = Str::slug($filter->name);

        $chunk = [
            'type' => 'filter',
            'code' => [
                "// Add filter: $filter->name (#{$filter->id})",
                "include get_template_directory() . '/includes/filters/filter-{$slug}.php';",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}