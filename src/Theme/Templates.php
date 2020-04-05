<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\Element;
use ThemeWright\Sync\Component\FieldGroup;
use ThemeWright\Sync\Filesystem\Filesystem;

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
    public function __construct(string $themeDir, &$data, &$functions, &$stylesScss, &$mainJs, &$messages = [])
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

                // Delete old files if the template name changed
                if ($oldNameMatch && $oldNameMatch[1] != $template->name) {
                    $this->deleteFiles($oldNameMatch[1]);
                }
            }

            $fields = $this->fs->file('includes/templates/fields-' . $template->name . '.php');
            $view = $this->fs->file($template->name . '.php');
            $scss = $this->fs->file('assets/scss/templates/_' . $template->name . '.scss');
            $js = $this->fs->file('assets/js/templates/' . $template->name . '.js');

            if ($template->type == 'template' && $template->fields) {
                $fieldGroup = new FieldGroup([
                    'fields' => $template->fields,
                    'id' => "template_{$template->id}",
                    'title' => '@todo',
                    'location' => [
                        [
                            [
                                'param' => 'page_template',
                                'operator' => '==',
                                'value' => $template->name == 'page' ? 'default' : $template->name . '.php',
                            ],
                        ],
                    ],
                    'label_placement' => 'left',
                ]);

                $fieldsContent = [
                    "<?php",
                    "",
                    "// Register ACF field group for page template: " . ($template->name == 'page' ? 'default' : $template->name . '.php'),
                    $fieldGroup->build(),
                ];

                $fields->setContent($fieldsContent)->saveWithMessages($this->messages);
            } else {
                $fields->deleteWithMessages($this->messages);
            }

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
                $viewContent = $template->viewRaw;
            } else {
                $elements = array_map(function ($args) use ($template) {
                    return (new Element($args, $this->data->domain, $template->templates, $template->parts, $template->blockGroups))->parse();
                }, $template->view);

                $viewContent = implode(PHP_EOL, $elements);
            }

            if ($template->type == 'template' && $template->name != 'page') {
                $viewContent = "<?php /* Template name: {$template->name} */ ?" . ">" . PHP_EOL . $viewContent;
            }

            $view->setContent($viewContent)->doubleSpacesToTabs()->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes all files associated to a template.
     *
     * This method does not delete TW functions code chunks, styles.scss and main.js.
     *
     * @param  string  $name
     * @return ThemeWright\Sync\Theme\Templates
     */
    public function deleteFiles(string $name)
    {
        $this->fs->file('includes/templates/fields-' . $name . '.php')->deleteWithMessages($this->messages);
        $this->fs->file($name . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/templates/_' . $name . '.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/templates/' . $name . '.js')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes templates and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks, styles.scss and main.js.
     *
     * @return ThemeWright\Sync\Theme\Templates
     */
    public function deleteExceptData()
    {
        $names = array_column($this->data->templates, 'name');

        // Don't delete the functions files
        $names[] = 'functions';
        $names[] = 'tw-functions';

        $fields = $this->fs->getThemeFiles('includes/templates');

        foreach ($fields as $field) {
            preg_match('/^fields-([a-z0-9-]+)\.php$/', $field->basename, $templateMatch);

            if ($templateMatch && !in_array($templateMatch[1], $names)) {
                $field->deleteWithMessages($this->messages);
            }
        }

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

        $views = $this->fs->getThemeFiles();

        foreach ($views as $view) {
            preg_match('/^([a-z0-9-]+)\.php$/', $view->basename, $templateMatch);

            if ($templateMatch && !in_array($templateMatch[1], $names)) {
                $view->deleteWithMessages($this->messages);
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

        foreach ($template->blockGroups as $blockGroup) {
            $chunk['code'][] = "TW_Block_Group::add_location(";
            $chunk['code'][] = "\t'" . $blockGroup->name . "',";
            $chunk['code'][] = "\tarray(";
            $chunk['code'][] = "\t\t'param'    => 'page_template',";
            $chunk['code'][] = "\t\t'operator' => '==',";
            $chunk['code'][] = "\t\t'value'    => '" . ($template->name == 'page' ? 'default' : $template->name . '.php') . "',";
            $chunk['code'][] = "\t)";
            $chunk['code'][] = ");";
        }

        if ($template->type == 'template' && $template->fields) {
            $chunk['code'][] = "include get_template_directory() . '/includes/templates/fields-{$template->name}.php';";
        }

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}