<?php

namespace Source\Core;

use Source\Support\Message;
use Source\Support\Seo;


class Controller
{
    protected $view;
    protected $seo;
    protected $message;

    public function __construct($pathToViews = null)
    {
        $this->view = new View($pathToViews);
        $this->seo = new Seo();
        $this->message = new Message();
    }
}
