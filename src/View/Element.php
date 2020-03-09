<?php

namespace ThemeWright\Sync\View;

use ThemeWright\Sync\Helper\Str;

class Element
{
    /**
     * The element node name.
     *
     * @var string
     */
    protected $node;

    /**
     * The element type.
     *
     * @var string
     */
    protected $type;

    /**
     * The condition PHP code.
     *
     * @var string[]
     */
    protected $condition;

    /**
     * The loop PHP code.
     *
     * @var string[]
     */
    protected $loop;

    /**
     * The inner text content of the element (mixed HTML and PHP).
     *
     * @var string[]
     */
    protected $text;

    /**
     * The CSS classes PHP code.
     *
     * @var string[]
     */
    protected $classes;

    /**
     * The element attributes PHP code.
     *
     * @var string[]
     */
    protected $attributes;

    /**
     * The child elements array.
     *
     * @var \ThemeWright\Sync\View\Element[]
     */
    protected $children;

    /**
     * The class constructor arguments.
     *
     * @var mixed
     */
    protected $args;

    /**
     * A list of self-closing HTML tags.
     *
     * @var array
     */
    public static $selfClosingTags = ['area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /**
     * Handles a view element and its properties.
     *
     * @param  mixed  $args
     * @return void
     */
    public function __construct($args)
    {
        $this->args = $args;
        $this->node = $args->node;
        $this->type = $args->type;
        $this->condition = $this->formatArgsPhp('conditionGroupsPhp');
        $this->loop = $this->formatArgsPhp('loopPhp');
        $this->text = $this->formatArgsPhp('twsPhp');
        $this->classes = $this->formatArgsPhp('cssClassesPhp');
        $this->attributes = $this->formatArgsPhp('attributesPhp');

        $this->children = array_map(function ($args) {
            return new Element($args);
        }, $args->children);
    }

    /**
     * Builds the content of a view file.
     *
     * @param  int  $indent
     * @return string
     */
    public function parse(int $indent = 0)
    {
        $lines = [];

        if ($this->condition) {
            $lines = $this->parseCondition($indent);
        } else if ($this->loop) {
            $lines = $this->parseLoop($indent);
        } else {
            $lines = $this->parseElement($indent);
        }

        // Remove trailing spaces
        foreach ($lines as $i => $line) {
            if (!trim($line)) {
                $lines[$i] = '';
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Parses the element conditions.
     *
     * @param  int  $indent
     * @return string[]
     */
    protected function parseCondition($indent = 0)
    {
        $lines = $this->condition;

        if (count($lines) == 3 && preg_match('/^if\s*\(\s*(.+?)\s*\)\s*{$/', $lines[0], $expr) && preg_match('/^\t*#TW...$/', $lines[1]) !== false && $lines[2] == '}') {
            $lines[0] = str_repeat("\t", $indent) . '<?php if ( ' . $expr[1] . ' ) : ?' . '>';
            $lines[2] = str_repeat("\t", $indent) . '<?php endif ?' . '>';
            array_splice($lines, 1, 1, $this->loop ? $this->parseLoop($indent + 1) : $this->parseElement($indent + 1));
        } else {
            foreach ($lines as $i => $line) {
                $lines[$i] = str_repeat("\t", $indent + 1) . $line;
            }

            array_unshift($lines, str_repeat("\t", $indent) . '<?php');
            array_push($lines, str_repeat("\t", $indent) . '?' . '>');

            foreach ($lines as $i => $line) {
                if (preg_match('/^\t*#TW...$/', $line)) {
                    $tabs = Str::countIndents($line);
                    $innerLines = $this->loop ? $this->parseLoop($tabs - 1) : $this->parseElement($tabs - 1);
                    array_unshift($innerLines, str_repeat("\t", $indent) . '?' . '>');
                    array_push($innerLines, str_repeat("\t", $indent) . '<?php');
                    array_splice($lines, $i, 1, $innerLines);
                }
            }
        }

        return $lines;
    }

    /**
     * Parses the element loop.
     *
     * @param  int  $indent
     * @return string[]
     */
    protected function parseLoop($indent = 0)
    {
        $lines = $this->loop;

        if (count($lines) == 3 && preg_match('/^(for|foreach|while)\s*\(\s*(.+?)\s*\)\s*{$/', $lines[0], $expr) && preg_match('/^\t*#TW...$/', $lines[1]) !== false && $lines[2] == '}') {
            $lines[0] = str_repeat("\t", $indent) . '<?php ' . $expr[1] . ' ( ' . $expr[2] . ' ) : ?' . '>';
            $lines[2] = str_repeat("\t", $indent) . '<?php end' . $expr[1] . ' ?' . '>';
            array_splice($lines, 1, 1, $this->parseElement($indent + 1));
        } else {
            foreach ($lines as $i => $line) {
                $lines[$i] = str_repeat("\t", $indent + 1) . $line;
            }

            array_unshift($lines, str_repeat("\t", $indent) . '<?php');
            array_push($lines, str_repeat("\t", $indent) . '?' . '>');

            foreach ($lines as $i => $line) {
                if (preg_match('/^\t*#TW...$/', $line)) {
                    $tabs = Str::countIndents($line);
                    $innerLines = $this->parseElement($tabs - 1);
                    array_unshift($innerLines, str_repeat("\t", $indent) . '?' . '>');
                    array_push($innerLines, str_repeat("\t", $indent) . '<?php');
                    array_splice($lines, $i, 1, $innerLines);
                }
            }
        }

        return $lines;
    }

    /**
     * Parses the element based on its type.
     *
     * @param  int  $indent
     * @return string[]
     */
    protected function parseElement($indent = 0)
    {
        switch ($this->type) {
            case 'html':
                return $this->parseHtmlElement($indent);
            case 'template':
                return $this->parseTemplateElement($indent);
            case 'part':
                return []; // @todo
            case 'text':
                return $this->parseTextElement($indent);
            default:
                return [];
        }
    }

    /**
     * Parses the HTML element (classes, attributes, inner text and child elements).
     *
     * @param  int  $indent
     * @return string[]
     */
    protected function parseHtmlElement($indent = 0)
    {
        $lines = [];

        $classesAttr = '';
        $attributesAttr = '';
        $classesInline = preg_match('/\A\$classes = array\([a-zA-Z0-9_\-\'\s,]+?\);\Z/m', implode(PHP_EOL, $this->classes));
        $attributesInline = preg_match('/\A\$atts = array\([a-zA-Z0-9_\-\'\s=>\,]+?\);\Z/m', implode(PHP_EOL, $this->attributes));

        // CSS classess
        if ($this->classes) {
            if ($classesInline) {
                $classesAttr = [];

                foreach ($this->classes as $line) {
                    if (preg_match('/^\s*\'([a-zA-Z0-9_-]+)\',?$/', $line, $match)) {
                        $classesAttr[] = $match[1];
                    }
                }

                $classesAttr = ' classes="' . implode(' ', $classesAttr) . '"';
            } else {
                $classesAttr = ' classes="<?php echo implode( \' \', $classes ); ?' . '>"';

                $lines[] = str_repeat("\t", $indent) . '<?php';

                foreach ($this->classes as $line) {
                    $lines[] = str_repeat("\t", $indent + 1) . $line;
                }

                if ($this->attributes && !$attributesInline) {
                    $lines[] = '';
                } else {
                    $lines[] = str_repeat("\t", $indent) . '?' . '>';
                }
            }
        }

        // Element attributes
        if ($this->attributes) {
            if ($attributesInline) {
                $attributesAttr = [];

                foreach ($this->attributes as $line) {
                    if (preg_match('/^\s*\'([a-zA-Z0-9_-]+)\'\s*=>\s*\'?([a-zA-Z0-9_-]+?|)\'?,?$/', $line, $match)) {
                        $attributesAttr[] = $match[2] ? $match[1] . '="' . $match[2] . '"' : $match[1];
                    }
                }

                $attributesAttr = ' ' . implode(' ', $attributesAttr);
            } else {
                $attributesAttr = ' <?php echo apply_filters( \'tw_key_value_to_attributes\', $atts ); ?' . '>';

                if (!$this->classes || $classesInline) {
                    $lines[] = str_repeat("\t", $indent) . '<?php';
                }

                foreach ($this->attributes as $line) {
                    $lines[] = str_repeat("\t", $indent + 1) . $line;
                }

                $lines[] = str_repeat("\t", $indent) . '?' . '>';
            }
        }

        // Opening tag
        $lines[] = str_repeat("\t", $indent) . '<' . $this->node . $classesAttr . $attributesAttr . '>';

        // Inner text
        if (count($this->text) > 1) {
            $lines[] = str_repeat("\t", $indent + 1) . '<?php';

            foreach ($this->text as $textLine) {
                $lines[] = str_repeat("\t", $indent + 2) . $textLine;
            }

            $lines[] = str_repeat("\t", $indent + 1) . '?' . '>';
        } else if ($this->text) {
            $lines[] = str_repeat("\t", $indent + 1) . '<?php ' . $this->text[0] . ' ?' . '>';
        }

        // Nested elements
        foreach ($this->children as $child) {
            $lines[] = $child->parse($indent + 1);
        }

        // Ending tag
        if (!in_array($this->node, static::$selfClosingTags)) {
            $lines[] = str_repeat("\t", $indent) . '</' . $this->node . '>';
        }

        // One line formatting
        if (count($this->text) == 1) {
            $lines = array_map(function ($line) {
                return trim($line);
            }, $lines);

            $lines = [str_repeat("\t", $indent) . implode('', $lines)];
        }

        return $lines;
    }

    /**
     * Parses the template element.
     *
     * @param  int  $indent
     * @return string[]
     */
    protected function parseTemplateElement($indent = 0)
    {
        if ($this->node == 'header') {
            $php = 'get_header()';
        } else if (preg_match('/^header-([a-z0-9-]+)$/', $this->node, $match)) {
            $php = "get_header( '{$match[1]}' )";
        } else if ($this->node == 'footer') {
            $php = 'get_footer()';
        } else if (preg_match('/^footer-([a-z0-9-]+)$/', $this->node, $match)) {
            $php = "get_footer( '{$match[1]}' )";
        } else if ($this->node == 'sidebar') {
            $php = 'get_sidebar()';
        } else if (preg_match('/^sidebar-([a-z0-9-]+)$/', $this->node, $match)) {
            $php = "get_sidebar( '{$match[1]}' )";
        } else if ($this->node == 'searchform') {
            $php = "get_search_form()";
        } else {
            $php = '';
        }

        return [
            str_repeat("\t", $indent) . '<?php ' . $php . '; ?' . '>',
        ];
    }

    /**
     * Parses the text element.
     *
     * @param  int  $indent
     * @return string[]
     */
    protected function parseTextElement($indent = 0)
    {
        $lines = [];

        if (count($this->text) > 1) {
            $lines[] = str_repeat("\t", $indent) . '<?php';

            foreach ($this->text as $textLine) {
                $lines[] = str_repeat("\t", $indent + 1) . $textLine;
            }

            $lines[] = str_repeat("\t", $indent) . '?' . '>';
        } else if ($this->text) {
            $lines[] = str_repeat("\t", $indent) . '<?php ' . $this->text[0] . ' ?' . '>';
        }

        return $lines;
    }

    /**
     * Converts and indents PHP code from the class arguments to an array of strings.
     *
     * @param string $argKey
     * @return string[]
     */
    protected function formatArgsPhp(string $argKey)
    {
        $lines = isset($this->args->$argKey->php) ? explode(PHP_EOL, $this->args->$argKey->php) : [];

        return array_map(function ($line) {
            $line = preg_replace('/^(\s*) {2}(\s*)/', "$1\t$2", $line);
            $line = preg_replace('/^(\t*)[ ]+/', "$1", $line);

            return $line;
        }, $lines);
    }
}