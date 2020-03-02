<?php

namespace ThemeWright\Sync\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request
{
    /**
     * The Request instance.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * The JSON object from the request.
     *
     * @var mixed
     */
    protected $json;

    /**
     * Builds a wrapper for the Symfony Requests to handle JSON data more efficient.
     *
     * @return void
     */
    public function __construct()
    {
        $this->request = new SymfonyRequest(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER
        );

        if ($this->request->server->get('REQUEST_METHOD') == 'OPTIONS') {
            (new Response())->send();
        }

        $this->json = json_decode($this->request->getContent());
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
        return $this->request->query->get('action');
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

        if (is_null($this->get('bundlers'))) {
            $errors[] = "The 'bundlers' parameter is required";
        }

        // @todo
        // if (is_null($this->get('version'))) {
        //     $errors[] = "The 'version' parameter is required";
        // }

        return $errors;
    }
}