<?php

namespace MsCodePL\LaravelReactEmail;

use Illuminate\Mail\Mailable;

class ReactMailable extends Mailable
{
    use ReactMailView;

    public function __construct()
    {
        parent::__construct();
        $this->markdown = null;
    }

    protected function getTemplate(): string
    {
        return $this->view ?? class_basename(static::class);
    }
}
