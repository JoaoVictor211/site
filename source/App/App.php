<?php

namespace Source\App;

use Source\Core\Controller;
use Source\Core\Session;
use Source\Models\Auth;
use Source\Support\Message;

class App extends Controller
{

    public function __construct()
    {

        parent::__construct(__DIR__ . "/../../themes/" . CONF_VIEW_APP);

        //RESTRIÇÃO
        if (!Auth::user()) {
            $this->message->warning("Efetue o login para acessar o App")->flash();
            redirect("/entrar");
        }
    }

    public function home()
    {
        echo flash();
        echo "<pre>";
        print_r(Auth::user());
        echo "</pre>";
        echo "<a title='Sair' href='" . url("/app/sair") . "'>Sair</a>";
    }

    public function logout()
    {
        (new Message)->info("Você saiu com sucesso " . Auth::user()->first_name . ". Volte logo :)")->flash();
        Auth::logout();
        redirect("/entrar");
    }
}
