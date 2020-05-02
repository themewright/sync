<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\FieldGroup;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;

class OptionsPages
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
     * Handles the ACF options pages.
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
     * Creates or updates the files for the ACF options pages and adds TW functions code chunks.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->optionsPages as $optionsPage) {
            $chunk = $this->createChunk($optionsPage);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Register options page: ([a-z0-9_-]+) \(#[0-9]+\)/', $oldChunk['code'], $oldMenuSlugMatch);

                // Delete old files if the menu slug changed
                if ($oldMenuSlugMatch && $oldMenuSlugMatch[1] != $optionsPage->menuSlug) {
                    $this->fs->file('includes/options-pages/fields-' . $oldMenuSlugMatch[1] . '.php')->deleteWithMessages($this->messages);
                }
            }

            $fields = $this->fs->file('includes/options-pages/fields-' . $optionsPage->menuSlug . '.php');

            $fieldGroup = new FieldGroup([
                'fields' => $optionsPage->fieldGroup->fields,
                'id' => "options_page_{$optionsPage->id}",
                'title' => $optionsPage->fieldGroup->settings->title ?: "@php:__( '{$optionsPage->pageTitle}', '{$this->data->domain}' )",
                'location' => [
                    [
                        [
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => $optionsPage->menuSlug,
                        ],
                    ],
                ],
                'menu_order' => $optionsPage->fieldGroup->settings->menuOrder,
                'position' => $optionsPage->fieldGroup->settings->position,
                'style' => $optionsPage->fieldGroup->settings->style,
                'label_placement' => $optionsPage->fieldGroup->settings->labelPlacement,
                'instruction_placement' => $optionsPage->fieldGroup->settings->instructionPlacement,
            ], $optionsPage->fieldSets);

            $fieldsContent = [
                "<?php",
                "",
                "// Register ACF field group for options page: {$optionsPage->menuSlug}",
                $fieldGroup->build(),
            ];

            $fields->setContent($fieldsContent)->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes ACF options pages and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\OptionsPages
     */
    public function deleteExceptData()
    {
        $menuSlugs = array_column($this->data->optionsPages, 'menuSlug');

        $fields = $this->fs->getThemeFiles('includes/options-pages');

        foreach ($fields as $field) {
            preg_match('/^fields-([a-z0-9_-]+)\.php$/', $field->basename, $menuSlugMatch);

            if ($menuSlugMatch && !in_array($menuSlugMatch[1], $menuSlugs)) {
                $field->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for an ACF options page object.
     *
     * @param  mixed  $optionsPage
     * @return array
     */
    protected function createChunk($optionsPage)
    {
        $args = new ArrayArgs();

        $chunk = [
            'type' => 'options-page',
            'code' => [
                "// Register options page: {$optionsPage->menuSlug} (#{$optionsPage->id})",
                "function tw_options_page_{$optionsPage->id}() {",
                "\tacf_add_options_page(",
                "\t\tarray(",
            ],
        ];

        $args->add('page_title', "@php:__( '{$optionsPage->pageTitle}', '{$this->data->domain}' )");
        $args->add('menu_title', "@php:__( '{$optionsPage->menuTitle}', '{$this->data->domain}' )");
        $args->add('menu_slug', $optionsPage->menuSlug);
        $args->add('capability', $optionsPage->capability);

        if ($optionsPage->parentSlug) {
            $args->add('parent_slug', $optionsPage->parentSlug);
        } else {
            $args->add('icon_url', $optionsPage->iconUrl);
        }

        $args->add('position', $optionsPage->position);
        $args->add('redirect', $optionsPage->redirect);
        $args->add('autoload', $optionsPage->autoload);
        $args->add('update_button', "@php:__( '{$optionsPage->updateButton}', '{$this->data->domain}' )");
        $args->add('updated_message', "@php:__( '{$optionsPage->updatedMessage}', '{$this->data->domain}' )");

        $chunk['code'] = array_merge($chunk['code'], $args->format(3));

        $chunk['code'][] = "\t\t)";
        $chunk['code'][] = "\t);";
        $chunk['code'][] = "}";
        $chunk['code'][] = "add_action( 'acf/init', 'tw_options_page_{$optionsPage->id}' );";
        $chunk['code'][] = "include get_template_directory() . '/includes/options-pages/fields-{$optionsPage->menuSlug}.php';";

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}