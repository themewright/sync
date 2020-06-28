<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;

class Editor
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
     * Handles the TinyMCE editor settings.
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
     * Builds the TinyMCE editor settings file.
     *
     * @return void
     */
    public function build()
    {
        $this->fs->file('assets/scss/editor.scss')->setContent($this->data->editor->scss)->spacesToTabs()->saveWithMessages($this->messages);

        $this->buildSettingsFile();

        $chunk = $this->createChunk();

        $this->functions->updateChunk($chunk);
    }

    /**
     * Builds the editor-settings.php file.
     *
     * @return void
     */
    protected function buildSettingsFile()
    {
        $file = $this->fs->file('includes/editor/editor-settings.php');

        $content = ["<?php"];

        $styleFormats = $this->parseStyleFormats($this->data->editor->styleFormats);

        $blockFormats = implode(';', array_map(function ($blockFormat) {
            return str_replace('=', '&equals;', $blockFormat->text) . '=' . $blockFormat->value;
        }, $this->data->editor->blockFormats));

        $wordpressAdvHidden = $this->data->editor->buttonRowsExpanded ? 'false' : 'true';
        $mce_buttons = $this->data->editor->buttons1 ? "array( '" . implode("', '", $this->data->editor->buttons1) . "' )" : 'array()';
        $mce_buttons_2 = $this->data->editor->buttons2 ? "array( '" . implode("', '", $this->data->editor->buttons2) . "' )" : 'array()';
        $mce_buttons_3 = $this->data->editor->buttons3 ? "array( '" . implode("', '", $this->data->editor->buttons3) . "' )" : 'array()';
        $mce_buttons_4 = $this->data->editor->buttons4 ? "array( '" . implode("', '", $this->data->editor->buttons4) . "' )" : 'array()';

        $textcolorMap = [];

        foreach ($this->data->editor->colors as $color) {
            $textcolorMap[] = $color->value;
            $textcolorMap[] = $color->text;
        }

        $previewStyles = implode(' ', $this->data->editor->previewStyles);
        $fontsizeFormats = implode(' ', $this->data->editor->fontSizeFormats);

        $content[] = "";
        $content[] = "/**";
        $content[] = " * Filters the TinyMCE config before init.";
        $content[] = " */";
        $content[] = "function tw_tiny_mce_before_init( \$mce_settings ) {";
        $content[] = "\t\$mce_settings['style_formats']        = '" . json_encode($styleFormats) . "';";
        $content[] = "\t\$mce_settings['block_formats']        = '{$blockFormats}';";
        $content[] = "\t\$mce_settings['wordpress_adv_hidden'] = {$wordpressAdvHidden};";
        $content[] = "\t\$mce_settings['preview_styles']       = '{$previewStyles}';";
        $content[] = "\t\$mce_settings['fontsize_formats']     = '{$fontsizeFormats}';";

        if ($textcolorMap) {
            $content[] = "\t\$mce_settings['textcolor_map']        = '" . json_encode($textcolorMap) . "';";
        }

        $content[] = "";
        $content[] = "\treturn \$mce_settings;";
        $content[] = "}";
        $content[] = "add_filter( 'tiny_mce_before_init', 'tw_tiny_mce_before_init' );";

        $content[] = "";
        $content[] = "/**";
        $content[] = " * Filters the fourth-row list of TinyMCE buttons (Visual tab).";
        $content[] = " */";
        $content[] = "function tw_add_editor_style() {";
        $content[] = "\tadd_editor_style( get_template_directory_uri() . '/assets/css/editor.css?ver={$this->data->version}' );";
        $content[] = "}";
        $content[] = "add_filter( 'admin_init', 'tw_add_editor_style' );";

        $content[] = "";
        $content[] = "/**";
        $content[] = " * Filters the first-row list of TinyMCE buttons (Visual tab).";
        $content[] = " */";
        $content[] = "function tw_mce_buttons( \$mce_buttons ) {";
        $content[] = "\t\$mce_buttons = {$mce_buttons};";
        $content[] = "";
        $content[] = "\treturn \$mce_buttons;";
        $content[] = "}";
        $content[] = "add_filter( 'mce_buttons', 'tw_mce_buttons' );";

        $content[] = "";
        $content[] = "/**";
        $content[] = " * Filters the second-row list of TinyMCE buttons (Visual tab).";
        $content[] = " */";
        $content[] = "function tw_mce_buttons_2( \$mce_buttons_2 ) {";
        $content[] = "\t\$mce_buttons_2 = {$mce_buttons_2};";
        $content[] = "";
        $content[] = "\treturn \$mce_buttons_2;";
        $content[] = "}";
        $content[] = "add_filter( 'mce_buttons_2', 'tw_mce_buttons_2' );";

        $content[] = "";
        $content[] = "/**";
        $content[] = " * Filters the third-row list of TinyMCE buttons (Visual tab).";
        $content[] = " */";
        $content[] = "function tw_mce_buttons_3( \$mce_buttons_3 ) {";
        $content[] = "\t\$mce_buttons_3 = {$mce_buttons_3};";
        $content[] = "";
        $content[] = "\treturn \$mce_buttons_3;";
        $content[] = "}";
        $content[] = "add_filter( 'mce_buttons_3', 'tw_mce_buttons_3' );";

        $content[] = "";
        $content[] = "/**";
        $content[] = " * Filters the fourth-row list of TinyMCE buttons (Visual tab).";
        $content[] = " */";
        $content[] = "function tw_mce_buttons_4( \$mce_buttons_4 ) {";
        $content[] = "\t\$mce_buttons_4 = {$mce_buttons_4};";
        $content[] = "";
        $content[] = "\treturn \$mce_buttons_4;";
        $content[] = "}";
        $content[] = "add_filter( 'mce_buttons_4', 'tw_mce_buttons_4' );";

        $file->setContent($content)->saveWithMessages($this->messages);
    }

    /**
     * Parses the style formats configuration into TinyMCE format.
     *
     * @param  array  $styleFormats
     * @return array
     */
    protected function parseStyleFormats($styleFormats)
    {
        $formats = [];

        foreach ($styleFormats as $styleFormat) {
            if ($styleFormat->children) {
                $format = array(
                    'title' => $styleFormat->title,
                    'items' => $this->parseStyleFormats($styleFormat->children),
                );
            } else {
                $format = array(
                    'title' => $styleFormat->title,
                    $styleFormat->mode => $styleFormat->selector,
                    'attributes' => array(
                        'class' => implode(' ', $styleFormat->class),
                    ),
                    'wrapper' => $styleFormat->wrapper,
                );
            }

            $formats[] = $format;
        }

        return $formats;
    }

    /**
     * Creates a TW functions code chunk for the editor settings.
     *
     * @return array
     */
    protected function createChunk()
    {
        $chunk = [
            'type' => 'editor',
            'code' => [
                "// TinyMCE editor settings",
                "include get_template_directory() . '/includes/editor/editor-settings.php';",
            ],
        ];

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}