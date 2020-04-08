<?php

namespace ThemeWright\Sync;

use Symfony\Component\Dotenv\Dotenv;
use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;
use ThemeWright\Sync\Http\Request;
use ThemeWright\Sync\Http\Response;
use ThemeWright\Sync\Theme\BlockGroups;
use ThemeWright\Sync\Theme\Blocks;
use ThemeWright\Sync\Theme\Bundlers;
use ThemeWright\Sync\Theme\Functions;
use ThemeWright\Sync\Theme\Includes;
use ThemeWright\Sync\Theme\JsModules;
use ThemeWright\Sync\Theme\MainJs;
use ThemeWright\Sync\Theme\MenuPages;
use ThemeWright\Sync\Theme\Parts;
use ThemeWright\Sync\Theme\PostTypes;
use ThemeWright\Sync\Theme\Scripts;
use ThemeWright\Sync\Theme\ScssPartials;
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
    public static $version = '0.0.1';

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
        // Get request parameters
        $this->request = new Request();

        // Load .env if it exists
        if (file_exists(__DIR__ . '/../../../../.env')) {
            (new Dotenv())->load(__DIR__ . '/../../../../.env');
        }

        // Log errors
        ini_set('error_log', __DIR__ . '/../error.log');
    }

    /**
     * Starts the application.
     *
     * @return void
     */
    public function run()
    {
        $time = microtime(true);

        // @todo compare versions of tw/sync from request and this version

        $errors = $this->request->validate();

        if ($errors) {
            (new Response())->addMany($errors)->send(400);
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
            (new Bundlers($themeDir, $data, $messages))->build();
            (new PostTypes($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Taxonomies($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Blocks($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new BlockGroups($data, $functions, $messages))->build();
            (new MenuPages($themeDir, $data, $functions, $messages))->deleteExceptData()->build();
            (new Templates($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new Parts($themeDir, $data, $functions, $stylesScss, $mainJs, $messages))->deleteExceptData()->build();
            (new ScssPartials($themeDir, $data, $stylesScss, $messages))->deleteExceptData()->build();
            (new JsModules($themeDir, $data, $mainJs, $messages))->deleteExceptData()->build();
            (new Styles($themeDir, $data, $functions))->build();
            (new Scripts($themeDir, $data, $functions))->build();
            $functions->build();
            $stylesScss->build();
            $mainJs->build();
            $stylesheet->build($time);
        } else if ($commit - 1 == $stylesheet->get('commit')) {
            // Doing partially only when 1 commit difference
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
                case 'bundlers':
                    (new Bundlers($themeDir, $data, $messages))->build();
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