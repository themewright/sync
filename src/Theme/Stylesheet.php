<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;
use ThemeWright\Sync\Helper\Str;

class Stylesheet
{
    /**
     * The Filesystem instance.
     *
     * @var \ThemeWright\Sync\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * The stylesheet parameters.
     *
     * @var array
     */
    protected $details = [];

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
     * Handles the main stylesheet (style.css) and screenshot (screenshot.png).
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

        $this->parseDetails();
    }

    /**
     * Gets a "detail" value.
     *
     * @param  string  $name
     * @return mixed
     */
    public function get(string $name)
    {
        switch ($name) {
            case 'themeId':
                return (int) ($this->details['twid'] ?? false);
                break;
            case 'commit':
                return (int) ($this->details['twcid'] ?? false);
                break;
            case 'screenshot':
                return $this->details['twss'] ?? false;
                break;
            default:
                return $this->details[$name] ?? false;
                break;
        }
    }

    /**
     * Parses the details from the stylesheet comment block.
     *
     * @return void
     */
    protected function parseDetails()
    {
        if (preg_match('/(?:\/\*)(.+?)(?:\*\/)/s', $this->fs->file('style.css')->getContent(), $comment)) {
            preg_match_all('/^[\s\*]*(.+?): *(.+)$/m', $comment[1], $details);

            foreach ($details[1] as $i => $name) {
                $name = Str::camel(Str::slug($name));
                $this->details[$name] = $details[2][$i];
            }
        }
    }

    /**
     * Creates a new or updates an existing style.css file.
     *
     * @param  float  $time
     * @return void
     */
    public function build(float $time = null)
    {
        $screenshot = $this->fs->file('screenshot.png');

        if (!$screenshot->exists() || $this->data->screenshot != $this->get('screenshot')) {
            $screenshotContent = (string) @file_get_contents($this->data->screenshot);
            $screenshot->setContent($screenshotContent)->saveWithMessages($this->messages);
        }

        $newContent = [
            '/*',
            'Theme Name: ' . $this->data->name,
            'Text Domain: ' . $this->data->domain,
            'Version: ' . $this->data->version,
            'Requires at least: 4.7',
            'Requires PHP: 7.2',
        ];

        if ($this->data->description) {
            $newContent[] = 'Description: ' . $this->data->description;
        }

        if ($this->data->tags) {
            $newContent[] = 'Tags: ' . $this->data->tags;
        }

        $newContent[] = 'Author: ' . $this->data->tags;

        if ($this->data->authorUri) {
            $newContent[] = 'Author URI: ' . $this->data->authorUri;
        }

        if ($this->data->themeUri) {
            $newContent[] = 'Theme URI: ' . $this->data->themeUri;
        }

        $newContent[] = 'License: ' . $this->data->license;

        if ($this->data->licenseUri) {
            $newContent[] = 'License URI: ' . $this->data->licenseUri;
        }

        $newContent[] = 'TWID: ' . $this->data->id;
        $newContent[] = 'TWCID: ' . $this->data->commit;
        $newContent[] = 'TWSS: ' . $this->data->screenshot;
        $newContent[] = '*/';

        $this->fs->file('style.css')->setContent($newContent)->saveWithMessages($this->messages);

        $this->parseDetails();

        if (!is_null($time)) {
            $this->messages[] = 'Finished sync in ' . round(microtime(true) - $time, 2) . 's';
        }
    }
}