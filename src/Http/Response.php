<?php

namespace ThemeWright\Sync\Http;

class Response
{
    /**
     * The response array.
     *
     * @var array
     */
    protected $data;

    /**
     * Builds a JSONP response wrapper.
     *
     * @param  array  $data
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->data = array_merge($data, ['messages' => []]);
    }

    /**
     * Appends a new message to the response array.
     *
     * @param  string  $message
     * @return Response
     */
    public function add(string $message)
    {
        $this->data['messages'][] = $message;
        return $this;
    }

    /**
     * Appends new messages to the response array.
     *
     * @param  array  $messages
     * @return Response
     */
    public function addMany(array $messages)
    {
        $this->data['messages'] = array_merge($this->data['messages'], $messages);
        return $this;
    }

    /**
     * Sends the respond JSONP and exits the script.
     *
     * @return string
     */
    public function send()
    {
        $callback = $_GET['callback'] ?? 'callback';
        $json = json_encode($this->data);

        echo "{$callback}($json);";

        exit;
    }
}