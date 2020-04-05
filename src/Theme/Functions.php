<?php

namespace ThemeWright\Sync\Theme;

use ThemeWright\Sync\Filesystem\Filesystem;

class Functions
{
    /**
     * The default functions file.
     *
     * @var \ThemeWright\Sync\Filesystem\File
     */
    protected $wpFile;

    /**
     * The TW functions file.
     *
     * @var \ThemeWright\Sync\Filesystem\File
     */
    protected $twFile;

    /**
     * Code chunks of the TW functions file.
     *
     * @var array
     */
    protected $chunks = [];

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
     * Handles the (tw-)functions.php files of a theme.
     *
     * @param  string  $themeDir
     * @param  mixed  $data
     * @param  array  $messages
     * @return void
     */
    public function __construct(string $themeDir, &$data, &$messages = [])
    {
        $this->wpFile = (new Filesystem($themeDir))->file('functions.php');
        $this->twFile = (new Filesystem($themeDir))->file('tw-functions.php');
        $this->data = &$data;
        $this->messages = &$messages;

        $this->parseChunks();
    }

    /**
     * Parses the code chunks from the TW functions file.
     *
     * @return void
     */
    protected function parseChunks()
    {
        preg_match_all('/^(\/\/.+?)(?:\n\n|\z)/ms', $this->twFile->getContent(), $matches);

        foreach ($matches[1] as $code) {
            $this->chunks[] = [
                'type' => $this->identifyChunkType($code),
                'code' => $code,
            ];
        }
    }

    /**
     * Empties all code chunks from the TW functions file.
     *
     * @return void
     */
    public function emptyChunks()
    {
        $this->chunks = [];
    }

    /**
     * Gets TW functions code chunks by a type.
     *
     * @param  string  $type
     * @return array
     */
    public function getChunksByType(string $type)
    {
        return array_filter($this->chunks, function ($chunk) use ($type) {
            return $chunk['type'] == $type;
        });
    }

    /**
     * Deletes TW functions code chunks by their type.
     *
     * @param  string  $type
     * @return void
     */
    public function deleteChunksByType(string $type)
    {
        foreach ($this->chunks as $i => $chunk) {
            if ($chunk['type'] == $type) {
                array_splice($this->chunks, $i, 1);
            }
        }
    }

    /**
     * Adds a code chunk to the TW functions file.
     *
     * @param  array  $chunk
     * @return void
     */
    public function addChunk(array $chunk)
    {
        $this->chunks[] = $chunk;
    }

    /**
     * Adds a new or updates an existing TW functions code chunk.
     *
     * @param  array  $chunk
     * @return int
     */
    public function updateChunk(array $chunk)
    {
        $index = $this->getChunkIndex($chunk);

        if ($index !== false) {
            $this->chunks[$index] = $chunk;
        } else {
            $this->addChunk($chunk);
        }

        return $index !== false ? $index : count($this->chunks) - 1;
    }

    /**
     * Gets a TW functions code chunk by comparing the type and IDs from the new chunk and an
     * already existing chunk.
     *
     * @param  array  $chunk
     * @return array|false
     */
    public function getChunk(array $chunk)
    {
        $index = $this->getChunkIndex($chunk);

        return $index !== false ? $this->chunks[$index] : false;
    }

    /**
     * Gets the array index of a TW functions code chunk by comparing the type and IDs from the new
     * chunk and an already existing chunk.
     *
     * @param  array  $chunk
     * @return int|false
     */
    public function getChunkIndex(array $chunk)
    {
        foreach ($this->chunks as $index => $_chunk) {
            if ($_chunk['type'] === $chunk['type']) {
                switch ($chunk['type']) {
                    case 'includes':
                        $pattern = '/(\/\/ Include the ThemeWright files)/';
                        break;
                    case 'globals':
                        $pattern = '/(\/\/ Define global variables)/';
                        break;
                    case 'post-type':
                        $pattern = '/\/\/ Post type: [a-z0-9_-]+ \(#([0-9]+)\)/';
                        break;
                    case 'block':
                        $pattern = '/\/\/ Register block: [a-z0-9_]+ \(#([0-9]+)\)/';
                        break;
                    case 'block-group':
                        $pattern = '/\/\/ Register block group: [a-z0-9_]+ \(#([0-9]+)\)/';
                        break;
                    case 'menu-page':
                        $pattern = '/\/\/ Register a new menu page \(#([0-9]+)\)/';
                        break;
                    case 'template':
                        $pattern = '/\/\/ Template specific options: [a-z0-9-]+\.php \(#([0-9]+)\)/';
                        break;
                    case 'style':
                        $pattern = '/\/\/ Enqueue CSS stylesheet: [a-z0-9-]+ \(#([0-9]+)\)/';
                        break;
                    case 'script':
                        $pattern = '/\/\/ Enqueue script: [a-z0-9-]+ \(#([0-9]+)\)/';
                        break;
                    case 'part':
                        $pattern = '/\/\/ Register template part: [a-z0-9-]+\.php \(#([0-9]+)\)/';
                        break;
                    default:
                        $pattern = null;
                        break;
                }

                if ($pattern) {
                    preg_match($pattern, $_chunk['code'], $_matches);
                    preg_match($pattern, $chunk['code'], $matches);

                    if ($_matches && $matches && $_matches[1] == $matches[1]) {
                        return $index;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Identifies the type of a TW functions code chunk.
     *
     * @param  string  $code
     * @return string|false
     */
    protected function identifyChunkType(string $code)
    {
        if (strpos($code, '// Include the ThemeWright files') === 0) {
            return 'includes';
        } else if (strpos($code, '// Define global variables') === 0) {
            return 'globals';
        } else if (strpos($code, '// Post type') === 0) {
            return 'post-type';
        } else if (strpos($code, '// Register block:') === 0) {
            return 'block';
        } else if (strpos($code, '// Register block group') === 0) {
            return 'block-group';
        } else if (strpos($code, '// Register a new menu page') === 0) {
            return 'menu-page';
        } else if (strpos($code, '// Template specific options') === 0) {
            return 'template';
        } else if (strpos($code, '// Enqueue CSS stylesheet') === 0) {
            return 'style';
        } else if (strpos($code, '// Enqueue script') === 0) {
            return 'script';
        } else if (strpos($code, '// Register template part') === 0) {
            return 'part';
        } else {
            return false;
        }
    }

    /**
     * Creates new or updates existing (tw-)functions.php files.
     *
     * @return void
     */
    public function build()
    {
        $globalsChunk = [
            'type' => 'globals',
            'code' => [
                "// Define global variables",
                "\$postmeta = new TW_Field();",
                "\$_postmeta = \$field = new TW_Field( 'post' );",
                "\$option = new TW_Field( 'option' );",
            ],
        ];

        $globalsChunk['code'] = implode(PHP_EOL, $globalsChunk['code']);
        $globalsChunkIndex = $this->getChunkIndex($globalsChunk);

        if ($globalsChunkIndex !== false) {
            $this->chunks[$globalsChunkIndex] = $globalsChunk;
        } else {
            array_splice($this->chunks, 1, 0, [$globalsChunk]);
        }

        $this->buildWpFile();
        $this->buildTwFile();
    }

    /**
     * Creates a new default functions file only if it doesn't exist.
     *
     * @return void
     */
    protected function buildWpFile()
    {
        if (!$this->wpFile->exists()) {
            $this->wpFile->setContentFromTemplate('functions.php')->saveWithMessages($this->messages);
        }
    }

    /**
     * Creates a new or updates an existing tw-functions.php file.
     *
     * @return void
     */
    protected function buildTwFile()
    {
        $content = ['<?php'];

        foreach ($this->chunks as $chunk) {
            $content[] = PHP_EOL . $chunk['code'];
        }

        $this->twFile->setContent($content)->saveWithMessages($this->messages);
    }
}