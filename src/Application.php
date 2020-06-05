<?php

namespace ThemeWright\Sync;

use Symfony\Component\Dotenv\Dotenv;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;
use ThemeWright\Sync\Http\Request;
use ThemeWright\Sync\Http\Response;
use ThemeWright\Sync\Theme\Actions;
use ThemeWright\Sync\Theme\Ajaxes;
use ThemeWright\Sync\Theme\Assets;
use ThemeWright\Sync\Theme\BlockGroups;
use ThemeWright\Sync\Theme\Blocks;
use ThemeWright\Sync\Theme\ConfigurationFiles;
use ThemeWright\Sync\Theme\Editor;
use ThemeWright\Sync\Theme\Filters;
use ThemeWright\Sync\Theme\Functions;
use ThemeWright\Sync\Theme\Includes;
use ThemeWright\Sync\Theme\JsModules;
use ThemeWright\Sync\Theme\MainJs;
use ThemeWright\Sync\Theme\MenuPages;
use ThemeWright\Sync\Theme\OptionsPages;
use ThemeWright\Sync\Theme\Parts;
use ThemeWright\Sync\Theme\PhpFiles;
use ThemeWright\Sync\Theme\PostTypes;
use ThemeWright\Sync\Theme\Scripts;
use ThemeWright\Sync\Theme\ScssPartials;
use ThemeWright\Sync\Theme\Shortcodes;
use ThemeWright\Sync\Theme\Styles;
use ThemeWright\Sync\Theme\Stylesheet;
use ThemeWright\Sync\Theme\StylesScss;
use ThemeWright\Sync\Theme\Taxonomies;
use ThemeWright\Sync\Theme\Templates;

class Application
{
    /**
     * The app version.
     *
     * @var string
     */
    public static $version = '0.9.10';

    /**
     * The Request instance.
     *
     * @var \ThemeWright\Sync\Http\Request
     */
    protected $request;

    /**
     * Creates a new ThemeWright Sync application instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Load .env if it exists
        if (file_exists(__DIR__ . '/../../../../.env')) {
            (new Dotenv())->load(__DIR__ . '/../../../../.env');
        }

        // Log errors
        ini_set('error_log', __DIR__ . '/../error.log');

        // Check version
        $this->checkVersion();

        // Get request parameters
        $this->request = new Request();
    }

    /**
     * Checks if there a newer version of the package.
     *
     * @return void
     */
    private function checkVersion()
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: PHP',
                ],
            ],
        ]);

        $version = static::$version;
        $info = json_decode(file_get_contents('https://api.themewright.com', false, $context));

        if ($info->syncVersion > $version) {
            (new Response())->addMany([
                "Error: Outdated client version ({$version} &rarr; {$info->syncVersion})",
                "#Run `composer update` to get the latest version.#",
            ])->send();
        }
    }

    /**
     * Starts the application.
     *
     * @return void
     */
    public function run()
    {
        $time = microtime(true);

        $errors = $this->request->validate();

        if ($errors) {
            (new Response())->addMany($errors)->send();
        }

        $action = $this->request->getAction();

        $themeId = $this->request->get('id');
        $commit = $this->request->get('commit');
        $data = $this->request->all();

        $themeDir = $this->getThemeDirById($themeId);

        if ($themeDir === false && $action != 'ping') {
            $themeDir = Str::slug($data->name, '');
        }

        $messages = [];

        $stylesheet = new Stylesheet($themeDir, $data, $messages);
        $functions = new Functions($themeDir, $data, $messages);
        $stylesScss = new StylesScss($themeDir, $data, $messages);
        $mainJs = new MainJs($themeDir, $data, $messages);

        if ($action == 'all') {
            $functions->emptyChunks();
            $stylesScss->emptyPartials();
            $mainJs->emptyModules();
            (new Includes($themeDir, $functions, $messages))->build();
            (new PhpFiles($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new ConfigurationFiles($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new PostTypes($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Taxonomies($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Blocks($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new BlockGroups($data, $functions, $messages))->build();
            (new MenuPages($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new OptionsPages($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Templates($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new Parts($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new ScssPartials($themeDir, $data, $stylesScss, $messages))->deleteExceptData()->build();
            (new JsModules($themeDir, $data, $mainJs, $messages))->deleteExceptData()->build();
            (new Filters($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Actions($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Ajaxes($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Shortcodes($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new Assets($themeDir, $data, $messages))->build();
            (new Styles($themeDir, $data, $functions))->build();
            (new Scripts($themeDir, $data, $functions))->build();
            (new Editor($themeDir, $data, $functions, $messages))->build();
            $functions->build();
            $stylesScss->build();
            $mainJs->build();
            $stylesheet->build($time);
        } else if ($commit - 1 == $stylesheet->get('commit')) {
            switch ($action) {
                case 'post-type':
                    (new PostTypes($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'taxonomy':
                    (new Taxonomies($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'block':
                    (new Blocks($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->build();
                    $functions->build();
                    $stylesScss->build();
                    $mainJs->build();
                    $stylesheet->build($time);
                    break;
                case 'block-group':
                    (new BlockGroups($data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'menu-page':
                    (new MenuPages($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'template':
                    (new Templates($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->build();
                    $functions->build();
                    $stylesScss->build();
                    $mainJs->build();
                    $stylesheet->build($time);
                    break;
                case 'style':
                    (new Styles($themeDir, $data, $functions))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'script':
                    (new Scripts($themeDir, $data, $functions))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'part':
                    (new Parts($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->build();
                    $functions->build();
                    $stylesScss->build();
                    $mainJs->build();
                    $stylesheet->build($time);
                    break;
                case 'field-set':
                    (new Templates($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->build();
                    (new Blocks($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->build();
                    (new PostTypes($themeDir, $data, $functions, $messages))->build();
                    (new Taxonomies($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesScss->build();
                    $mainJs->build();
                    $stylesheet->build($time);
                    break;
                case 'scss-partial':
                    (new ScssPartials($themeDir, $data, $stylesScss, $messages))->build();
                    $stylesScss->build();
                    $stylesheet->build($time);
                    break;
                case 'js-module':
                    (new JsModules($themeDir, $data, $mainJs, $messages))->build();
                    $mainJs->build();
                    $stylesheet->build($time);
                    break;
                case 'filter':
                    (new Filters($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'action':
                    (new Actions($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'ajax':
                    (new Ajaxes($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'shortcode':
                    (new Shortcodes($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->build();
                    $functions->build();
                    $stylesScss->build();
                    $mainJs->build();
                    $stylesheet->build($time);
                    break;
                case 'options-page':
                    (new OptionsPages($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'php-file':
                    (new PhpFiles($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'configuration-file':
                    (new ConfigurationFiles($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                case 'editor':
                    (new Editor($themeDir, $data, $functions, $messages))->build();
                    $functions->build();
                    $stylesheet->build($time);
                    break;
                default:
                    break;
            }
        }

        $response = new Response([
            'themeId' => (int) $stylesheet->get('twid'),
            'commit' => (int) $stylesheet->get('twcid'),
            'wpDir' => (new Filesystem())->wpDir,
            'messages' => $messages,
        ]);

        $response->addMany($messages)->send();
    }

    /**
     * Gets a theme directory by its TW ID.
     *
     * @param  int  $themeId
     * @return string|false
     */
    protected function getThemeDirById($themeId)
    {
        $allDirs = (new Filesystem())->listThemes();

        foreach ($allDirs as $dir) {
            $data = false;

            if ((int) (new Stylesheet($dir, $data))->get('twid') === $themeId) {
                return $dir;
            }
        }

        return false;
    }
}