<?php

namespace ThemeWright\Sync\Filesystem;

use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class Filesystem
{
    /**
     * The Filesystem instance.
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * The WordPress root directory path.
     *
     * @var string
     */
    protected $wpDir;

    /**
     * The WordPress themes root directory path.
     *
     * @var string
     */
    protected $themesDir;

    /**
     * The TW WordPress theme directory path.
     *
     * @var string
     */
    protected $themeDir;

    /**
     * Builds a wrapper for the Symfony Filesystem.
     *
     * @param  string  $themeDir
     * @return void
     */
    public function __construct(string $themeDir = '')
    {
        $this->fs = new SymfonyFilesystem();
        $this->wpDir = isset($_ENV['TW_WP_DIR']) ? realpath($_ENV['TW_WP_DIR']) : realpath(__DIR__ . '/../../../../../');
        $this->themesDir = isset($_ENV['TW_THEMES_DIR']) ? realpath($_ENV['TW_THEMES_DIR']) : realpath($this->wpDir . '/wp-content/themes');
        $this->themeDir = $this->themesDir . '/' . $themeDir;

        if (!$this->fs->exists($this->themeDir)) {
            $this->fs->mkdir($this->themeDir, 0755);
        }
    }

    /**
     * Gets class properties.
     *
     * @param  string  $property
     * @return void
     */
    public function __get(string $property)
    {
        switch ($property) {
            case 'wpDir':
                return $this->wpDir;
            case 'themesDir':
                return $this->themesDir;
            case 'themeDir':
                return $this->themeDir;
            default:
                return null;
        }
    }

    /**
     * Gets a file from the theme directory.
     *
     * @param  string  $filename
     * @return false|\ThemeWright\Sync\Filesystem\File
     */
    public function getFile(string $filename)
    {
        if (is_file($this->themeDir . '/' . $filename)) {
            return new File($this->themeDir . '/' . $filename, $filename);
        }

        return false;
    }

    /**
     * Gets a File instance from the theme directory regardless if the file exists or not.
     *
     * @param  string  $filename
     * @return \ThemeWright\Sync\Filesystem\File
     */
    public function file(string $filename)
    {
        return new File($this->themeDir . '/' . $filename, $filename);
    }

    /**
     * Lists all theme directories (slugs).
     *
     * @return array
     */
    public function listThemes()
    {
        return is_dir($this->themesDir) ? static::listSubdirectories($this->themesDir) : [];
    }

    /**
     * Lists all subdirectories in a theme directory.
     *
     * @param  string  $path
     * @return array
     */
    public function listThemeSubdirectories(string $path)
    {
        return static::listSubdirectories($this->themeDir . '/' . $path);
    }

    /**
     * Removes empty subdirectories in a theme directory.
     *
     * @param  string  $path
     * @param  bool  $fullPath
     * @return boolean
     */
    public function removeEmptyThemeSubdirectories(string $path, bool $fullPath = false)
    {
        if (!$fullPath) {
            $path = $this->themeDir . '/' . $path;
        }

        if (!is_dir($path)) {
            return false;
        }

        $empty = true;

        foreach (scandir($path) as $file) {
            if ($file == '.DS_Store') {
                unlink($path . '/.DS_Store');
            } else if ($file != '.' && $file != '..' && (!is_dir($path . '/' . $file) || !$this->removeEmptyThemeSubdirectories($path . '/' . $file, true))) {
                $empty = false;
            }
        }

        return $empty && rmdir($path);
    }

    /**
     * Lists all files in a theme subdirectory.
     *
     * @param  string  $subdir
     * @return array
     */
    public function listThemeFiles(string $subdir)
    {
        return static::listFiles($this->themeDir . '/' . $subdir);
    }

    /**
     * Gets all files in a theme subdirectory as File objects.
     *
     * @param  string  $subdir
     * @return \ThemeWright\Sync\Filesystem\File[]
     */
    public function getThemeFiles(string $subdir = '')
    {
        $files = [];
        $filenames = static::listFiles($this->themeDir . '/' . $subdir);

        foreach ($filenames as $filename) {
            $files[] = $this->getFile($subdir . '/' . $filename);
        }

        return $files;
    }

    /**
     * Lists all subdirectories in a directory.
     *
     * @param  string  $path
     * @return array
     */
    public static function listSubdirectories(string $path)
    {
        if (!is_dir($path)) {
            return [];
        }

        return array_values(array_filter(scandir($path), function ($filename) use ($path) {
            return $filename != '.' && $filename != '..' && is_dir($path . '/' . $filename);
        }));
    }

    /**
     * Lists all files in a directory.
     *
     * @param  string  $path
     * @return array
     */
    public static function listFiles(string $path)
    {
        if (!is_dir($path)) {
            return [];
        }

        return array_values(array_filter(scandir($path), function ($filename) use ($path) {
            return $filename != '.' && $filename != '..' && is_file($path . '/' . $filename);
        }));
    }
}