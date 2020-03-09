<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\ArrayArgs;
use ThemeWright\Sync\View\Element;

class MenuPages
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
     * Handles the WP admin menu pages.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data = false, &$functions, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->functions = &$functions;
        $this->messages = &$messages;
    }

    /**
     * Creates or updates the files for the WP admin menu pages and adds TW functions code chunks.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->menuPages as $menuPage) {
            $chunk = $this->createChunk($menuPage);
            $oldChunk = $this->functions->getChunk($chunk);

            if ($oldChunk) {
                preg_match('/\'menu_slug\'\s*=>\s*\'([a-z-]+)\'/', $oldChunk['code'], $oldMenuSlugMatch);

                // Delete old files if the menu slug changed
                if ($oldMenuSlugMatch && $oldMenuSlugMatch[1] != $menuPage->menuSlug) {
                    $this->deleteFiles($oldMenuSlugMatch[1]);
                }
            }

            $css = $this->fs->file('assets/css/' . $menuPage->menuSlug . '.menu-page.css');
            $cssMap = $this->fs->file('assets/css/' . $menuPage->menuSlug . '.menu-page.css.map');
            $scss = $this->fs->file('assets/scss/' . $menuPage->menuSlug . '.menu-page.scss');
            $mjs = $this->fs->file('assets/js/' . $menuPage->menuSlug . '.menu-page.js');
            $js = $this->fs->file('assets/js/dist/' . $menuPage->menuSlug . '.menu-page.js');
            $jsMap = $this->fs->file('assets/js/dist/' . $menuPage->menuSlug . '.menu-page.js.map');
            $view = $this->fs->file('views/menu-pages/' . $menuPage->menuSlug . '.php');

            if ($menuPage->scss) {
                $scss->setContent($menuPage->scss)->doubleSpacesToTabs()->saveWithMessages($this->messages);
            } else {
                $css->deleteWithMessages($this->messages);
                $cssMap->deleteWithMessages($this->messages);
                $scss->deleteWithMessages($this->messages);
            }

            if ($menuPage->js) {
                $mjs->setContent($menuPage->js)->doubleSpacesToTabs()->saveWithMessages($this->messages);
            } else {
                $mjs->deleteWithMessages($this->messages);
                $js->deleteWithMessages($this->messages);
                $jsMap->deleteWithMessages($this->messages);
            }

            if ($menuPage->viewRaw) {
                $viewContent = $menuPage->viewRaw;
            } else {
                $elements = array_map(function ($args) {
                    return (new Element($args))->parse();
                }, $menuPage->view);

                $viewContent = implode(PHP_EOL, $elements);
            }

            $view->setContent($viewContent)->saveWithMessages($this->messages);

            $this->functions->updateChunk($chunk);
        }
    }

    /**
     * Deletes all files associated to a menu page.
     *
     * This method does not delete TW functions code chunks.
     *
     * @param  string  $menuSlug
     * @return ThemeWright\Sync\Theme\MenuPages
     */
    public function deleteFiles(string $menuSlug)
    {
        $this->fs->file('assets/css/' . $menuSlug . '.menu-page.css')->deleteWithMessages($this->messages);
        $this->fs->file('assets/css/' . $menuSlug . '.menu-page.css.map')->deleteWithMessages($this->messages);
        $this->fs->file('assets/scss/' . $menuSlug . '.menu-page.scss')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/' . $menuSlug . '.menu-page.js')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/dist/' . $menuSlug . '.menu-page.js')->deleteWithMessages($this->messages);
        $this->fs->file('assets/js/dist/' . $menuSlug . '.menu-page.js.map')->deleteWithMessages($this->messages);
        $this->fs->file('assets/views/menu-pages/' . $menuSlug . '.php')->deleteWithMessages($this->messages);

        return $this;
    }

    /**
     * Deletes menu pages and associated files which are not included in the current $data object.
     *
     * This method does not delete TW functions code chunks.
     *
     * @return ThemeWright\Sync\Theme\MenuPages
     */
    public function deleteExceptData()
    {
        $menuSlugs = array_column($this->data->menuPages, 'menuSlug');

        $assets = array_merge(
            $this->fs->getThemeFiles('assets/css'),
            $this->fs->getThemeFiles('assets/scss'),
            $this->fs->getThemeFiles('assets/js'),
            $this->fs->getThemeFiles('assets/js/dist')
        );

        foreach ($assets as $asset) {
            preg_match('/^([a-z0-9-]+)\.menu-page\.(?:css|css\.map|scss|js|js\.map)$/', $asset->basename, $menuSlugMatch);

            if ($menuSlugMatch && !in_array($menuSlugMatch[1], $menuSlugs)) {
                $asset->deleteWithMessages($this->messages);
            }
        }

        $views = $this->fs->getThemeFiles('views/menu-pages');

        foreach ($views as $view) {
            preg_match('/^([a-z0-9-]+)\.php$/', $view->basename, $menuSlugMatch);

            if ($menuSlugMatch && !in_array($menuSlugMatch[1], $menuSlugs)) {
                $view->deleteWithMessages($this->messages);
            }
        }

        return $this;
    }

    /**
     * Creates a TW functions code chunk for a menu page object.
     *
     * @param  mixed  $menuPage
     * @return array
     */
    protected function createChunk($menuPage)
    {
        $args = new ArrayArgs();

        $chunk = [
            'type' => 'menu-page',
            'code' => [
                "// Register a new menu page (#{$menuPage->id})",
                "new TW_Menu_Page( (object) array(",
            ],
        ];

        if ($menuPage->parentSlug) {
            $args->add('parent_slug', $menuPage->parentSlug);
        }

        $args->add('page_title', $menuPage->pageTitle);
        $args->add('menu_title', $menuPage->menuTitle);
        $args->add('capability', $menuPage->capability);
        $args->add('menu_slug', $menuPage->menuSlug);

        if (!$menuPage->parentSlug) {
            $args->add('icon_url', $menuPage->iconUrl);
        }

        $args->add('position', $menuPage->position);
        $args->add('scss', !!$menuPage->scss);
        $args->add('js', !!$menuPage->js);

        $chunk['code'] = array_merge($chunk['code'], $args->format());

        $chunk['code'][] = ") );";

        $chunk['code'] = implode(PHP_EOL, $chunk['code']);

        return $chunk;
    }
}