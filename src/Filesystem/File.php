<?php

namespace ThemeWright\Sync\Filesystem;

class File
{
    /**
     * The file path.
     *
     * @var string
     */
    protected $path;

    /**
     * The relative file path in the theme directory.
     *
     * @var string
     */
    protected $pathInTheme;

    /**
     * The file directory name.
     *
     * @var string
     */
    protected $dir;

    /**
     * The filename component of the path.
     *
     * @var string
     */
    protected $basename;

    /**
     * The initial file content.
     *
     * @var string|false
     */
    protected $initialContent;

    /**
     * The new file content.
     *
     * @var string
     */
    protected $newContent;

    /**
     * File handling class.
     *
     * @param  string  $path
     * @param  string  $pathInTheme
     * @return void
     */
    public function __construct(string $path, string $pathInTheme = '')
    {
        $this->path = $path;
        $this->pathInTheme = trim($pathInTheme, '/');
        $this->dir = dirname($path);
        $this->basename = basename($path);
        $this->initialContent = $this->getContent();
        $this->newContent = '';
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
            case 'path':
                return $this->path;
            case 'pathInTheme':
                return $this->pathInTheme;
            case 'dir':
                return $this->dir;
            case 'basename':
                return $this->basename;
            case 'initialContent':
                return $this->initialContent;
            case 'newContent':
                return $this->newContent;
            default:
                return null;
        }
    }

    /**
     * Checks whether a file exists.
     *
     * @return boolean
     */
    public function exists()
    {
        return file_exists($this->path);
    }

    /**
     * Gets the file content.
     *
     * @return string|false
     */
    public function getContent()
    {
        return $this->exists() ? file_get_contents($this->path) : false;
    }

    /**
     * Sets the new file content.
     *
     * @param  string|string[]  $content
     * @return \ThemeWright\Sync\Filesystem\File
     */
    public function setContent($content)
    {
        $this->newContent = is_array($content) ? implode(PHP_EOL, $content) : $content;
        return $this;
    }

    /**
     * Sets the new file content from a template.
     *
     * @param  string  $template
     * @return \ThemeWright\Sync\Filesystem\File
     */
    public function setContentFromTemplate(string $template)
    {
        $templatePath = realpath(__DIR__ . '/../../templates/' . $template);

        if ($templatePath) {
            $this->newContent = (new File($templatePath))->getContent();
        }

        return $this;
    }

    /**
     * Converts the indents from double spaces to tabs.
     *
     * @return \ThemeWright\Sync\Filesystem\File
     */
    public function doubleSpacesToTabs()
    {
        $this->newContent = preg_replace('/^ {2}|\G {2}/Sm', "\t", $this->newContent);
        return $this;
    }

    /**
     * Creates a new or updates the existing file.
     *
     * @return int|bool
     */
    public function save()
    {
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        if ($this->newContent != $this->initialContent || !$this->exists()) {
            // File size in bytes or false on error
            return file_put_contents($this->path, $this->newContent);
        }

        // Nothing changed
        return true;
    }

    /**
     * Creates a new or updates the existing file and stores console messages.
     *
     * @param  &$messages  array
     * @return void
     */
    public function saveWithMessages(array &$messages)
    {
        $status = $this->save();

        if ($status === false) {
            $messages[] = 'Error: Cannot create the file "' . $this->pathInTheme . '"';
        } else if (is_int($status)) {
            $messages[] = 'Built: ' . $this->pathInTheme . ' (' . round($status / 1024, 2) . ' KB)';
        }
    }

    /**
     * Deletes the existing file.
     *
     * @return int
     */
    public function delete()
    {
        if ($this->exists()) {
            // 1 - Success, 0 - Failure
            return (int) unlink($this->path);
        }

        // Nothing changed
        return -1;
    }

    /**
     * Deletes the existing file and stores console messages.
     *
     * @param  &$messages  array
     * @return void
     */
    public function deleteWithMessages(array &$messages)
    {
        $status = $this->delete();

        if ($status === 0) {
            $messages[] = 'Error: Cannot delete the file "' . $this->pathInTheme . '"';
        } else if ($status === 1) {
            $messages[] = 'Deleted: ' . $this->pathInTheme;
        }
    }
}