<?php

namespace MsCodePL\LaravelReactEmail\Exceptions;

use Exception;

class NodeNotFoundException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message ?: 'Node.js binary could not be located. Set REACT_EMAIL_NODE_PATH in your environment.',
            0,
            null
        );
    }
}
