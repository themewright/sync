<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\FieldGroup;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\Helper\Str;

class Taxonomies
{
    /**
     * Array of reserved taxonomy keys.
     *
     * @var array
     */
    protected $reserved = ['category', 'post_tag', 'post_format', 'link_category'];

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
     * Handles the custom taxonomies.
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
     * Builds the custom taxonomy registration and field files.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->taxonomies as $taxonomy) {
            $chunk = $this->createChunk($taxonomy);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Taxonomy: ([a-z0-9_-]+) \(#[0-9]+\)/', $oldChunk['code'], $oldKeyMatch);

                // Delete old files if the taxonomy key changed
                if ($oldKeyMatch && $oldKeyMatch[1] != $taxonomy->taxonomy) {
                    $this->deleteFiles($oldKeyMatch[1]);
                }
            }

            $register = $this->fs->file('includes/taxonomies/register-' . $taxonomy->taxonomy . '.php');
            $fields = $this->fs->file('includes/taxonomies/fields-' . $taxonomy->taxonomy . '.php');

            if (!in_array($taxonomy->taxonomy, $this->reserved)) {
                $registerContent = $this->getRegisterContent($taxonomy);
                $register->setContent($registerContent)->saveWithMessages($this->messages);
            }

            if ($taxonomy->fields) {
                $fieldGroup = new FieldGroup([
                    'fields' => $taxonomy->fields,
                    'id' => "taxonomy_{$taxonomy->id}",
                    'title' => '@todo',
                    'location' => [
                        [
                            [
                                'param' => 'taxonomy',
                                'operator' => '==',
                                'value' => $taxonomy->taxonomy,
                            ],
                        ],
                    ],
                    'label_placement' => 'left',
                ], $taxonomy->fieldSets);

                $fieldsContent = [
                    "<?php",
                    "",
                    "// Register ACF field group for taxonomy: {$taxonomy->taxonomy}",
                    $fieldGroup->build(),
                ];

                $fields->setContent($fieldsContent)->saveWithMessages($this->messages);
            } else {
                $fields->deleteWithMessages($this->messages);
            }

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Builds PHP content for registering a custom taxonomy.
     *
     * @param  mixed  $taxonomy
     * @return string[]
     */
    protected function getRegisterContent($taxonomy)
    {
        $args = [];

        foreach ($taxonomy->args as $key => $value) {
            $key = Str::snake($key);

            switch ($key) {
                case 'label':
                    $args[$key] = "@php:__( '{$value}', '{$this->data->domain}' )";
                    break;
                case 'labels':
                    $labels = [];

                    foreach ($value as $labelKey => $labelValue) {
                        $labelKey = Str::snake($labelKey);

                        switch ($labelKey) {
                            case 'name':
                                $labelValue = "@php:_x( '{$labelValue}', 'taxonomy general name', '{$this->data->domain}' )";
                                break;
                            case 'singular_name':
                                $labelValue = "@php:_x( '{$labelValue}', 'taxonomy singular name', '{$this->data->domain}' )";
                                break;
                            case 'most_used':
                                $labelValue = "@php:_x( '{$labelValue}', '{$taxonomy->taxonomy}', '{$this->data->domain}' )";
                                break;
                            default:
                                $labelValue = "@php:__( '{$labelValue}', '{$this->data->domain}' )";
                                break;
                        }

                        $labels[$labelKey] = $labelValue;
                    }

                    $args[$key] = $labels;
                    break;
                case 'description':
                    if ($value) {
                        $args[$key] = "@php:__( '{$value}', '{$this->data->domain}' )";
                    }

                    break;
                default:
                    $args[$key] = $value;
                    break;
            }
        }

        if ($args['rewrite'] && $args['rewrite_args']) {
            $args['rewrite'] = $args['rewrite_args'];
        }

        if ($args['query_var'] && $args['query_var_string']) {
            $args['query_var'] = $args['query_var_string'];
        }

        unset($args['rewrite_args']);
        unset($args['query_var_string']);

        $content = [
            "<?php",
            "",
            "// Register custom taxonomy: {$taxonomy->taxonomy}",
            "function tw_register_taxonomy_{$taxonomy->id}() {",
            "\tregister_taxonomy(",
            "\t\t'{$taxonomy->taxonomy}',",
            count($taxonomy->postTypeKeys) > 1 ? "\t\tarray( '" . implode("', '", $taxonomy->postTypeKeys) . "' )," : "\t\t'{$taxonomy->postTypeKeys[0]}',",
            "\t\tarray(",
            implode(PHP_EOL, (new ArrayArgs($args))->asort(true)->format(3)),
            "\t\t)",
            "\t);",
            "}",
            "add_action( 'init', 'tw_register_taxonomy_{$taxonomy->id}' );",
        ];

        return $content;
    }

    /**
     * Deletes all files associated to a taxonomy.
     *
     * This method does not delete TW functions code chunks.
     *
     * @param  string  $key
     * @return ThemeWright\Sync\Theme\Taxonomies
     */
    public function deleteFiles(string $key)
    {
        $this->fs->file('includes/taxonomies/fields-' . $key . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('includes/taxonomies/register-' . $key . '.php')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes taxonomies and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\Taxonomies
     */
    public function deleteExceptData()
    {
        $keys = array_column($this->data->taxonomies, 'taxonomy');

        $files = $this->fs->getThemeFiles('includes/taxonomies');

        foreach ($files as $file) {
            preg_match('/^(?:fields|register)-([a-z0-9_-]+)\.php$/', $file->basename, $taxonomyMatch);

            if ($taxonomyMatch && !in_array($taxonomyMatch[1], $keys)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a taxonomy object.
     *
     * @param  mixed  $taxonomy
     * @return array
     */
    protected function createChunk($taxonomy)
    {
        $chunk = [
            'type' => 'taxonomy',
            'code' => [
                "// Taxonomy: {$taxonomy->taxonomy} (#{$taxonomy->id})",
            ],
        ];

        if (in_array($taxonomy->taxonomy, $this->reserved)) {
            if ($taxonomy->postTypeKeys != ['post']) {
                $chunk['code'][] = "function tw_change_taxonomy_post_type_relations_{$taxonomy->id}() {";

                if ($taxonomy->postTypeKeys) {
                    $postTypeKeys = array_diff($taxonomy->postTypeKeys, ['post']);

                    foreach ($postTypeKeys as $postTypeKey) {
                        $chunk['code'][] = "\tregister_taxonomy_for_object_type( '{$taxonomy->taxonomy}', '{$postTypeKey}' );";
                    }
                }

                if (!in_array('post', $taxonomy->postTypeKeys)) {
                    $chunk['code'][] = "\tunregister_taxonomy_for_object_type( '{$taxonomy->taxonomy}', 'post' );";
                }

                $chunk['code'][] = "}";
                $chunk['code'][] = "add_action( 'init', 'tw_change_taxonomy_post_type_relations_{$taxonomy->id}' );";
            }
        } else {
            $chunk['code'][] = "include get_template_directory() . '/includes/taxonomies/register-{$taxonomy->taxonomy}.php';";
        }

        if ($taxonomy->fields) {
            $chunk['code'][] = "include get_template_directory() . '/includes/taxonomies/fields-{$taxonomy->taxonomy}.php';";
        }

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}