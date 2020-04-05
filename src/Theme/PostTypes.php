<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Component\FieldGroup;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\Helper\Str;

class PostTypes
{
    /**
     * Array of reserved pos type keys.
     *
     * @var array
     */
    protected $reserved = ['post', 'page', 'attachment', 'revision', 'nav_menu_item'];

    /**
     * Array of default support features for the 'post' post type.
     *
     * @var array
     */
    protected $defaultPostSupports = ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'customFields', 'comments', 'revisions', 'postFormats'];

    /**
     * Array of default support features for the 'page' post type.
     *
     * @var array
     */
    protected $defaultPageSupports = ['title', 'editor', 'author', 'thumbnail', 'pageAttributes', 'customFields', 'comments', 'revisions'];

    /**
     * Array of default support features for the 'attachment' post type.
     *
     * @var array
     */
    protected $defaultAttachmentSupports = ['title', 'author', 'comments'];

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
     * Handles the custom post types.
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
     * Builds the custom post types registration and field files.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->postTypes as $postType) {
            $chunk = $this->createChunk($postType);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\/\/ Post type: ([a-z0-9_-]+) \(#[0-9]+\)/', $oldChunk['code'], $oldNameMatch);

                // Delete old files if the post type key changed
                if ($oldNameMatch && $oldNameMatch[1] != $postType->postType) {
                    $this->deleteFiles($oldNameMatch[1]);
                }
            }

            $register = $this->fs->file('includes/post-types/register-' . $postType->postType . '.php');
            $fields = $this->fs->file('includes/post-types/fields-' . $postType->postType . '.php');

            if (!in_array($postType->postType, $this->reserved)) {
                $registerContent = $this->getRegisterContent($postType);
                $register->setContent($registerContent)->saveWithMessages($this->messages);
            }

            if ($postType->fields) {
                $fieldGroup = new FieldGroup([
                    'fields' => $postType->fields,
                    'id' => "post_type_{$postType->id}",
                    'title' => '@todo',
                    'location' => [
                        [
                            [
                                'param' => 'post_type',
                                'operator' => '==',
                                'value' => $postType->postType,
                            ],
                        ],
                    ],
                    'label_placement' => 'left',
                ]);

                $fieldsContent = [
                    "<?php",
                    "",
                    "// Register ACF field group for post type: {$postType->postType}",
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
     * Builds PHP content for registering a custom post type.
     *
     * @param  mixed  $postType
     * @return string[]
     */
    protected function getRegisterContent($postType)
    {
        $args = [];

        foreach ($postType->args as $key => $value) {
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
                                $labelValue = "@php:_x( '{$labelValue}', 'post type general name', '{$this->data->domain}' )";
                                break;
                            case 'singular_name':
                                $labelValue = "@php:_x( '{$labelValue}', 'post type singular name', '{$this->data->domain}' )";
                                break;
                            case 'add_new':
                                $labelValue = "@php:_x( '{$labelValue}', 'post', '{$this->data->domain}' )";
                                break;
                            case 'featured_image':
                                $labelValue = "@php:_x('{$labelValue}', 'post', '{$this->data->domain}')";
                                break;
                            case 'set_featured_image':
                                $labelValue = "@php:_x('{$labelValue}', 'post', '{$this->data->domain}')";
                                break;
                            case 'remove_featured_image':
                                $labelValue = "@php:_x('{$labelValue}', 'post', '{$this->data->domain}')";
                                break;
                            case 'use_featured_image':
                                $labelValue = "@php:_x('{$labelValue}', 'post', '{$this->data->domain}')";
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
                case 'supports':
                    $supports = [];

                    foreach ($value as $supportKey => $supportValue) {
                        if ($supportValue) {
                            $supports[] = Str::kebab($supportKey);
                        }
                    }

                    $args[$key] = $supports;
                    break;
                default:
                    $args[$key] = $value;
                    break;
            }
        }

        if (isset($args['show_in_menu']) && $args['show_in_menu'] === 'string') {
            $args['show_in_menu'] = $args['show_in_menu_string'];
        }

        if ($args['has_archive'] && $args['has_archive_string']) {
            $args['has_archive'] = $args['has_archive_string'];
        }

        if ($args['rewrite'] && $args['rewrite_args']) {
            $args['rewrite'] = $args['rewrite_args'];
        }

        if ($args['query_var'] && $args['query_var_string']) {
            $args['query_var'] = $args['query_var_string'];
        }

        unset($args['show_in_menu_string']);
        unset($args['has_archive_string']);
        unset($args['rewrite_args']);
        unset($args['query_var_string']);

        $content = [
            "<?php",
            "",
            "// Register custom post type: {$postType->postType}",
            "function tw_register_post_type_{$postType->id}() {",
            "\tregister_post_type(",
            "\t\t'{$postType->postType}',",
            "\t\tarray(",
            implode(PHP_EOL, (new ArrayArgs($args))->asort()->format(3)),
            "\t\t)",
            "\t);",
            "}",
            "add_action( 'init', 'tw_register_post_type_{$postType->id}' );",
        ];

        return $content;
    }

    /**
     * Deletes all files associated to a post type.
     *
     * This method does not delete TW functions code chunks.
     *
     * @param  string  $key
     * @return ThemeWright\Sync\Theme\PostTypes
     */
    public function deleteFiles(string $key)
    {
        $this->fs->file('includes/post-types/fields-' . $key . '.php')->deleteWithMessages($this->messages);
        $this->fs->file('includes/post-types/register-' . $key . '.php')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes post types and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\PostTypes
     */
    public function deleteExceptData()
    {
        $keys = array_column($this->data->postTypes, 'postType');

        $files = $this->fs->getThemeFiles('includes/post-types');

        foreach ($files as $file) {
            preg_match('/^(?:fields|register)-([a-z0-9_-]+)\.php$/', $file->basename, $postTypeMatch);

            if ($postTypeMatch && !in_array($postTypeMatch[1], $keys)) {
                $file->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a post type object.
     *
     * @param  mixed  $postType
     * @return array
     */
    protected function createChunk($postType)
    {
        $chunk = [
            'type' => 'post-type',
            'code' => [
                "// Post type: {$postType->postType} (#{$postType->id})",
            ],
        ];

        if (in_array($postType->postType, $this->reserved)) {
            $removeSupports = [];

            if ($postType->postType == 'post') {
                $defaultSupports = $this->defaultPostSupports;
            } else if ($postType->postType == 'page') {
                $defaultSupports = $this->defaultPageSupports;
            } else if ($postType->postType == 'attachment') {
                $defaultSupports = $this->defaultAttachmentSupports;
            } else {
                $defaultSupports = [];
            }

            $removeSupports = array_filter($defaultSupports, function ($feature) use ($postType) {
                return !$postType->args->supports->$feature;
            });

            if ($removeSupports) {
                $chunk['code'][] = "function tw_remove_post_type_supports_{$postType->id}() {";

                foreach ($removeSupports as $feature) {
                    $chunk['code'][] = "\tremove_post_type_support( '{$postType->postType}', '{$feature}' );";
                }

                $chunk['code'][] = "}";
                $chunk['code'][] = "add_action( 'init', 'tw_remove_post_type_supports_{$postType->id}' );";
            }
        } else {
            $chunk['code'][] = "include get_template_directory() . '/includes/post-types/register-{$postType->postType}.php';";
        }

        if ($postType->fields) {
            $chunk['code'][] = "include get_template_directory() . '/includes/post-types/fields-{$postType->postType}.php';";
        }

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}