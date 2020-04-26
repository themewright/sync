<?php

namespace ThemeWright\Sync\Http;

class Request
{
    /**
     * The JSON object from the request.
     *
     * @var mixed
     */
    protected $json;

    /**
     * Builds a HTTP request wrapper.
     *
     * @return void
     */
    public function __construct()
    {
        $hash = $_GET['hash'] ?? null;

        if (!ctype_alnum($hash)) {
            (new Response())->add('Invalid hash.')->send();
        }

        $jsonUrl = isset($_ENV['TW_JSON_URL']) ? $_ENV['TW_JSON_URL'] . "/{$hash}.json" : "https://json.themewright.com/{$hash}.json";
        $json = file_get_contents($jsonUrl);

        $this->json = json_decode($json);
    }

    /**
     * Returns the JSON parameters.
     *
     * @return array
     */
    public function all()
    {
        return $this->json;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array
     */
    public function keys()
    {
        return array_keys((array) $this->json);
    }

    /**
     * Returns a JSON parameter by name.
     *
     * @param  string  $name
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $name, string $default = null)
    {
        return $this->json->$name ?? $default;
    }

    /**
     * Returns the 'action' query parameter from the request.
     *
     * @return string
     */
    public function getAction()
    {
        return $_GET['action'] ?? null;
    }

    /**
     * Validates the request and returns errors if found.
     *
     * @return string[]
     */
    public function validate()
    {
        $errors = [];

        if (is_null($this->get('id'))) {
            $errors[] = "The 'id' parameter is required";
        }

        if (is_null($this->get('commit'))) {
            $errors[] = "The 'commit' parameter is required";
        }

        if (is_null($this->get('version'))) {
            $errors[] = "The 'version' parameter is required";
        }

        return $errors;
    }
}