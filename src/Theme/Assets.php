<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class Assets
{
    /**
     * The index.tw file.
     *
     * @var \ThemeWright\Sync\Filesystem\File
     */
    protected $index;

    /**
     * The base URL for the assets.
     *
     * @var string
     */
    protected $assetsUrl;

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
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles the theme assets.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$messages = [])
    {
        $this->fs = new Filesystem($themeDir);
        $this->data = &$data;
        $this->messages = &$messages;
        $this->index = $this->fs->file('assets/index.tw');
        $this->assetsUrl = isset($_ENV['TW_ASSETS_URL']) ? $_ENV['TW_ASSETS_URL'] : 'https://assets.themewright.com';
    }

    /**
     * Handles the asset files.
     *
     * @return void
     */
    public function build()
    {
        $currentAssets = $this->parseIndex();
        $assetIds = array_column($this->data->assets, 'id');

        foreach ($currentAssets as $currentAsset) {
            if (!in_array($currentAsset->id, $assetIds)) {
                $this->fs->file('assets/' . $currentAsset->path)->deleteWithMessages($this->messages);
            }
        }

        foreach ($this->data->assets as $asset) {
            if (isset($currentAssets[$asset->id])) {
                $currentAsset = $currentAssets[$asset->id];
                $currentFile = $this->fs->file('assets/' . $currentAsset->path);
                $file = $this->fs->file('assets/' . $asset->path);
                $content = false;

                if ($currentFile->exists() && $currentAsset->filemtime == $asset->filemtime && $currentAsset->size == $asset->size && $currentAsset->path == $asset->path) {
                    continue;
                } else if ($currentFile->exists() && $currentFile->getContent() && $currentAsset->filemtime == $asset->filemtime && $currentAsset->size == $asset->size) {
                    $content = $currentFile->getContent();
                } else {
                    $url = str_replace(' ', '%20', "{$this->assetsUrl}/{$this->data->id}/{$asset->hash}/{$asset->path}");
                    $content = @file_get_contents($url);
                    $content = $content !== false ? $content : '';
                }

                $currentFile->deleteWithMessages($this->messages);
                $file->setContent($content)->saveWithMessages($this->messages);
            } else {
                $url = str_replace(' ', '%20', "{$this->assetsUrl}/{$this->data->id}/{$asset->hash}/{$asset->path}");
                $content = @file_get_contents($url);
                $content = $content !== false ? $content : '';
                $this->fs->file('assets/' . $asset->path)->setContent($content)->saveWithMessages($this->messages);
            }
        }

        $this->updateIndex();
        $this->fs->removeEmptyThemeSubdirectories('assets');
    }

    /**
     * Parses the file index.
     *
     * @return array
     */
    protected function parseIndex()
    {
        $assets = [];
        $raw = $this->index->getContent();
        $lines = preg_split('/\R/', $raw);

        foreach ($lines as $line) {
            $parts = explode(',', $line);

            if (count($parts) == 4) {
                $assets[$parts[0]] = (object) [
                    'id' => $parts[0],
                    'path' => $parts[1],
                    'filemtime' => $parts[2],
                    'size' => $parts[3],
                ];
            }
        }

        return $assets;
    }

    /**
     * Updates the file index.
     *
     * @return void
     */
    protected function updateIndex()
    {
        $content = [];

        foreach ($this->data->assets as $asset) {
            $content[] = "{$asset->id},{$asset->path},{$asset->filemtime},{$asset->size}";
        }

        $this->index->setContent($content)->saveWithMessages($this->messages);
    }
}