<?php

namespace ThemeWright\Sync\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response
{
    /**
     * The Response instance.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    protected $response;

    /**
     * The response array.
     *
     * @var array
     */
    protected $data;

    /**
     * Builds a wrapper for the Symfony Response.
     *
     * @param  array  $data
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->response = new SymfonyResponse();

        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $this->response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $this->response->headers->set('Access-Control-Allow-Origin', '*'); // @todo limit

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
     * Sends the respond JSON and exits the script.
     *
     * @param  int  $status
     * @return string
     */
    public function send(int $status = 200)
    {
        $this->response->setContent(json_encode($this->data))->setStatusCode($status)->send();
        exit;
    }
}