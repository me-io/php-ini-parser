<?php

namespace Ini;

use ArrayObject;
use LogicException;
use InvalidArgumentException;

/**
 * Class Parser
 *
 * @package Ini
 */
class Parser
{
    /**
     * Filename of our .ini file.
     *
     * @var string
     */
    protected $ini_content;

    /**
     * Enable/disable property nesting feature
     *
     * @var boolean
     */
    public $property_nesting = true;

    /**
     * Enable/disable parametric value parsing
     *
     * @var boolean
     */
    public $parametric_parsing = false;

    /**
     * Normal: 0
     * Raw: 1
     * Typed: 2
     */
    public $ini_parse_option = 0;

    /**
     * Separator in case of multiple values
     *
     * @var string
     */
    public $multi_value_separator = '|';

    /**
     * Use ArrayObject to allow array work as object (true) or use native arrays (false)
     *
     * @var boolean
     */
    public $use_array_object = true;

    /**
     * Include original sections (pre-inherit names) on the final output
     *
     * @var boolean
     */
    public $include_original_sections = false;

    /**
     * If set to true, it will consider the passed parameter as string
     *
     * @var bool
     */
    public $treat_ini_string = false;

    /**
     * Disable array literal parsing
     */
    const NO_PARSE = 0;

    /**
     * Parse simple arrays using regex (ex: [a,b,c,...])
     */
    const PARSE_SIMPLE = 1;

    /**
     * Parse array literals using JSON, allowing advanced features like
     * dictionaries, array nesting, etc.
     */
    const PARSE_JSON = 2;

    /**
     * Normal: 0
     * Raw: 1
     * Typed: 2
     */
    const INI_PARSE_OPTION = 0;

    /**
     * Array literals parse mode
     *
     * @var int
     */
    public $array_literals_behavior = self::PARSE_SIMPLE;

    /**
     * Parser constructor.
     *
     * @param string $iniContent File path or the ini string
     */
    public function __construct(string $iniContent = null)
    {
        if ($iniContent !== null) {
            $this->setIniContent($iniContent);
        }
    }

    /**
     * Parses an INI file
     *
     * @param string $iniContent
     *
     * @return array|self
     */
    public function parse(string $iniContent = null)
    {
        if ($iniContent !== null) {
            $this->setIniContent($iniContent);
        }

        if (empty($this->ini_content)) {
            throw new LogicException("Need ini content to parse.");
        }

        if ($this->treat_ini_string) {
            $simple_parsed = parse_ini_string($this->ini_content, true, $this->ini_parse_option);
        } else {
            $simple_parsed = parse_ini_file($this->ini_content, true, $this->ini_parse_option);
        }

        $inheritance_parsed = $this->parseSections($simple_parsed);

        return $this->parseKeys($inheritance_parsed);
    }

    /**
     * Parses a string with INI contents
     *
     * @param string $src
     *
     * @return array
     */
    public function process(string $src)
    {
        $simple_parsed = parse_ini_string($src, true, $this->ini_parse_option);
        $inheritance_parsed = $this->parseSections($simple_parsed);

        return $this->parseKeys($inheritance_parsed);
    }

    /**
     * @param string $ini_content
     *
     * @return \Ini\Parser
     * @throws \InvalidArgumentException
     */
    public function setIniContent(string $ini_content)
    {
        // If the parsed parameter is to be treated as string instead of file
        if ($this->treat_ini_string) {
            $this->ini_content = $ini_content;
        } else {
            if (!file_exists($ini_content) || !is_readable($ini_content)) {
                throw new InvalidArgumentException("The file '{$ini_content}' cannot be opened.");
            }

            $this->ini_content = $ini_content;
        }

        return $this;
    }

    /**
     * Parse sections and inheritance.
     *
     * @param  array $simple_parsed
     *
     * @return array  Parsed sections
     */
    private function parseSections(array $simple_parsed)
    {
        // do an initial pass to gather section names
        $sections = [];
        $globals = [];
        foreach ($simple_parsed as $k => $v) {
            if (is_array($v)) {
                // $k is a section name
                $sections[$k] = $v;
            } else {
                $globals[$k] = $v;
            }
        }

        // now for each section, see if it uses inheritance
        $output_sections = [];
        foreach ($sections as $k => $v) {
            $sects = array_map('trim', array_reverse(explode(':', $k)));
            $root = array_pop($sects);
            $arr = $v;
            foreach ($sects as $s) {
                if ($s === '^') {
                    $arr = array_merge($globals, $arr);
                } elseif (array_key_exists($s, $output_sections)) {
                    $arr = array_merge($output_sections[$s], $arr);
                } elseif (array_key_exists($s, $sections)) {
                    $arr = array_merge($sections[$s], $arr);
                } else {
                    throw new \UnexpectedValueException("IniParser: In file '{$this->ini_content}', section '{$root}': Cannot inherit from unknown section '{$s}'");
                }
            }

            if ($this->include_original_sections) {
                $output_sections[$k] = $v;
            }
            $output_sections[$root] = $arr;
        }


        return $globals + $output_sections;
    }

    /**
     * @param array $arr
     *
     * @return array|self
     */
    private function parseKeys(array $arr)
    {
        $output = $this->getArrayValue();
        $append_regex = '/\s*\+\s*$/';
        foreach ($arr as $k => $v) {
            if (is_array($v) && false === strpos($k, '.')) {
                // this element represents a section; recursively parse the value
                $output[$k] = $this->parseKeys($v);
            } else {
                // if the key ends in a +, it means we should append to the previous value, if applicable
                $append = false;
                if (preg_match($append_regex, $k)) {
                    $k = preg_replace($append_regex, '', $k);
                    $append = true;
                }

                // transform "a.b.c = x" into $output[a][b][c] = x
                $current = &$output;

                $path = $this->property_nesting ? explode('.', $k) : [$k];
                while (($current_key = array_shift($path)) !== null) {
                    if ('string' === gettype($current)) {
                        $current = [$current];
                    }

                    if (!array_key_exists($current_key, $current)) {
                        if (!empty($path)) {
                            $current[$current_key] = $this->getArrayValue();
                        } else {
                            $current[$current_key] = null;
                        }
                    }
                    $current = &$current[$current_key];
                }

                // parse value
                $value = $v;
                if (!is_array($v)) {
                    $value = $this->parseValue($v);
                }

                if ($append && $current !== null) {
                    if (is_array($value)) {
                        if (!is_array($current)) {
                            throw new LogicException("Cannot append array to inherited value '{$k}'");
                        }
                        $value = array_merge($current, $value);
                        $value = array_map([$this, 'parseParametricValue'], $value);
                    } else {
                        $value = $current . $value;
                    }
                }

                $current = $this->parseParametricValue($value);
            }
        }

        return $output;
    }

    /**
     * Parses the parametric value to multiple parameters
     *
     * @param $value
     * // todo is array or string?
     * @return array|string
     */
    protected function parseParametricValue($value)
    {
        // If parametric parsing isn't turned on or value has no parameters
        if (!$this->parametric_parsing || !is_string($value) || strpos($value, '=') === false) {
            return $value;
        }

        // As there could be multiple parameters separated by spaces
        $parameters = preg_split('/\s+/', $value);

        $parsedValue = [];
        foreach ($parameters as $parameter) {
            list($parameterKey, $parameterValue) = explode('=', $parameter);
            // todo simplify
            $parsedValue[$parameterKey] = strpos($parameterValue, $this->multi_value_separator) !== false ? explode($this->multi_value_separator, $parameterValue) : $parameterValue;
        }

        return $parsedValue;
    }

    /**
     * Parses and formats the value in a key-value pair
     *
     * @param string $value
     *
     * @return mixed
     */
    protected function parseValue(string $value)
    {
        switch ($this->array_literals_behavior) {
            case self::PARSE_JSON:
                if (in_array(substr($value, 0, 1), ['[', '{']) && in_array(substr($value, -1), [']', '}'])) {
                    if (defined('JSON_BIGINT_AS_STRING')) {
                        $output = json_decode($value, true, 512, JSON_BIGINT_AS_STRING);
                    } else {
                        $output = json_decode($value, true);
                    }

                    if ($output !== null) {
                        return $output;
                    }
                }
            // fallthrough
            // try regex parser for simple estructures not JSON-compatible (ex: colors = [blue, green, red])
            case self::PARSE_SIMPLE:
                // if the value looks like [a,b,c,...], interpret as array
                if (preg_match('/^\[\s*.*?(?:\s*,\s*.*?)*\s*\]$/', trim($value))) {
                    return array_map('trim', explode(',', trim(trim($value), '[]')));
                }
                break;
        }

        return $value;
    }

    /**
     * @param array $array
     *
     * @return array|\ArrayObject
     */
    protected function getArrayValue(array $array = [])
    {
        if ($this->use_array_object) {
            return new ArrayObject($array, ArrayObject::ARRAY_AS_PROPS);
        } else {
            return $array;
        }
    }
}
