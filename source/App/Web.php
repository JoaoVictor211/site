<?php

namespace Source\App;

use Source\Core\Controller;
use Source\Models\Auth;
use Source\Models\Category;
use Source\Models\Faq\Question;
use Source\Models\Post;
use Source\Models\Report\Access;
use Source\Models\Report\Online;
use Source\Models\User;
use Source\Support\Pager;

class Web extends Controller
{
    public function __construct()
    {
        parent::__construct(__DIR__ . "/../../themes/" . CONF_VIEW_THEME . "/");
        (new Access())->report();
        (new Online())->report();

        /* 
           $online = new Online();
        var_dump($online->findByActive(true), $online->findByActive());
        echo "<pre>";
        print_r($online->findByActive());
        echo "</pre>"; */
    }

    public function home(): void
    {

        $head = $this->seo->render(
            CONF_SITE_NAME . " - " . CONF_SITE_TITLE,
            CONF_SITE_DESC,
            url(),
            theme("/assets/images/share.jpg")
        );
        echo ($this->view->render("home", [
            "head" => $head,
            "video" => CONF_SOCIAL_YOUTUBE_VIDEO,
            "blog" => (new Post)->find()->order("post_at DESC")->limit(6)->fetch(true)
        ]));
    }

    public function about(): void
    {
        $head = $this->seo->render(
            "Descubra o " . CONF_SITE_NAME . " - " . CONF_SITE_TITLE,
            CONF_SITE_DESC,
            url("/sobre"),
            theme("/assets/images/share.jpg")
        );
        echo ($this->view->render("about", [
            "head" => $head,
            "video" => CONF_SOCIAL_YOUTUBE_VIDEO,
            "faq" => (new Question)
                ->find("channel_id = :id", "id=1", "question, response")
                ->order("order_by")
                ->fetch(true)
        ]));
    }
    public function blog(?array $data): void
    {


        $head = ($this->seo->render(
            "Blog - " . CONF_SITE_NAME,
            "Confira nossas dicas para controlar melhor suas contas",
            url("/blog"),
            theme("/assets/images/share.jpg")
        ));
        $blog = (new Post())->find();


        $pager = new Pager(url("/blog/p/"));
        $pager->pager($blog->count(), 9, ($data["page"] ?? 1));
        echo ($this->view->render("blog", [
            "head" => $head,
            "blog" => $blog->limit($pager->limit())->offset($pager->offset())->fetch(true),

            "paginator" => $pager->render()
        ]));
    }

    public function blogCategory(array $data): void
    {
        $categoryUri = filter_var($data["category"], FILTER_SANITIZE_SPECIAL_CHARS);
        $category = (new Category)->findByUri($categoryUri);

        if (!$category) {
            redirect("/blog");
        }

        $blogCategory = (new Post())->find("category = :c", "c={$category->id}");
        $page = (!empty($data['page']) && filter_var($data['page'], FILTER_VALIDATE_INT) >= 1 ? $data['page'] : 1);
        $pager = new Pager(url("/blog/em/{$category->uri}/"));
        $pager->pager($blogCategory->count(), 9, $page);
        $head = $this->seo->render(
            "Artigos em {$category->title} - " . CONF_SITE_NAME,
            $category->description,
            url("/blog/em/{$category->uri}/{$page}"),
            ($category->cover ? image($category->cover, 1200, 628) : theme("/assets/images/share.jpg"))
        );

        echo $this->view->render("blog", [
            "head" => $head,
            "title" => "Artigos em {$category->title}",
            "desc" => $category->description,
            "blog" => $blogCategory
                ->limit($pager->limit())
                ->offset($pager->offset())
                ->order("post_at desc")
                ->fetch(true),
            "paginator" => $pager->render()
        ]);
    }
    public function blogSearch(array $data): void
    {


        if (!empty($data['s'])) {
            $search = filter_var($data['s'], FILTER_SANITIZE_SPECIAL_CHARS);
            echo json_encode(["redirect" => url("/blog/buscar/{$search}/1")]);
            return;
        }

        if (empty($data['terms'])) {
            redirect("/blog");
        }

        $search = filter_var($data['terms'], FILTER_SANITIZE_SPECIAL_CHARS);
        $page = (filter_var($data['page'], FILTER_VALIDATE_INT) >= 1 ? $data['page'] : 1);
        $string = trim($search);
        $string = preg_replace('/\s+/', ' ', $string);
        $terms = explode(" ", $string);


        $head = $this->seo->render(
            "Pesquisa por {$search} - " . CONF_SITE_NAME,
            "Confira os resultados de sua pesquisa para {$search}",
            url("/blog/buscar/{$search}/{$page}"),
            theme("assets/images/share.jpg")
        );

        $queries = [];

        $queries[] = (new Post())->find("MATCH(title, subtitle) AGAINST(:term)", "term={$search}");

        $index = 0;
        $initialQuery = array_shift($queries);

        foreach ($terms as $term) {
            $category = (new Category)->findByUri($term);
            if ($category) {
                $queries[] = (new Post())->find("category = :categoryId{$index}", "categoryId{$index}={$category->id}");
                $index++;
            }
        }

        foreach ($queries as $query) {

            $initialQuery->union($query);
        }


        $allResults = $initialQuery->fetch(true);


        if (!$initialQuery->count()) {
            echo $this->view->render("blog", [
                "head" => $head,
                "title" => "PESQUISA POR:",
                "search" => $search
            ]);
            return;
        }

        $pager = new Pager(url("/blog/buscar/{$search}/"));
        $pager->pager($initialQuery->count(), 9, $page);

        $paginatedResults = $initialQuery->limit(9)->offset($pager->offset())->fetch(true);

        echo $this->view->render("blog", [
            "head" => $head,
            "title" => "PESQUISA POR:",
            "search" => $search,
            "blog" => $paginatedResults,
            "paginator" => $pager->render()
        ]);
    }

    public function blogPost(?array $data): void
    {
        $post = (new Post)->findByUri($data["uri"]);


        if (!$post) {
            redirect("/404");
        }

        $post->views += 1;
        $post->save();

        $head = ($this->seo->render(
            "{$post->title} = " . CONF_SITE_NAME,
            $post->subtitle,
            url("/blog/{$post->uri}"),
            image($post->cover, 1200, 628)
        ));

        echo ($this->view->render("blog-post", [
            "head" => $head,
            "post" => $post,
            "related" => (new Post)
                ->find("category = :category AND id != :id", "category={$post->category}&id={$post->id}")
                ->order("rand()")
                ->limit(3)
                ->fetch(true)
        ]));
    }

    public function login(?array $data): void
    {
        if (!empty($data["csrf"])) {
            if (!csrf_verify($data)) {
                $json["message"] = $this->message->error("Error ao enviar, favor use o formulário")->render();
                echo json_encode($json);
                return;
            }

            if (request_limit("weblogin", 3, 60 * 5)) {
                $json["message"] = $this->message->error("Você já efetuou 3 tentativas, esse é o limite; Por favor. aguarde por 5 minutos para tentar novamente!")->render();
                echo json_encode($json);
                return;
            }

            if (empty($data["email"] || empty($data["password"]))) {
                $json["message"] = $this->message->warning("Informe seu e-mail e senha para entrar")->render();
                echo json_encode($json);
                return;
            }

            $save = (!empty($data["save"]) ? true : false);
            $auth = new Auth();
            $login = $auth->login($data["email"], $data["password"], $save);

            if ($login) {
                $json["redirect"] = url("/app");
            } else {
                $json["message"] = $auth->message()->before("Oppss! ")->render();
            }
            echo json_encode($json);
            return;
        }
        $head = ($this->seo->render(
            "Entrar - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url("/entrar"),
            theme("/assets/images/share.jpg")
        ));

        echo ($this->view->render("auth-login", [
            "head" => $head,
            "cookie" => filter_input(INPUT_COOKIE, "authEmail")
        ]));
    }

    public function forget(?array $data): void
    {

        if (!empty($data["csrf"])) {
            if (!csrf_verify($data)) {
                $json["message"] = $this->message->error("Erro ao enviar, favor use o formulário")->render();
                echo json_encode($json);
                return;
            }

            if (empty($data["email"])) {
                $json["message"] = $this->message->info("Informe seu e-mail para continuar")->render();
                echo json_encode($json);
                return;
            }

            if (request_repeat("webforget", $data["email"])) {
                $json["message"] = $this->message->error("Opsss! Você já tentou este e-mail antes")->render();
                echo json_encode($json);
                return;
            }
            $auth = new Auth();

            if ($auth->forget($data["email"])) {
                $json["message"] =  $this->message->success("Acesse seu e-mail para recuperar a senha")->render();
            } else {
                $json["message"] = $auth->message()->before("Oppss! ")->render();
            }


            echo json_encode($json);
            return;
        }



        $head = ($this->seo->render(
            "Recuperar - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url("/recuperar"),
            theme("/assets/images/share.jpg")
        ));

        echo ($this->view->render("auth-forget", [
            "head" => $head
        ]));
    }

    public function reset(array $data): void
    {
        if (!empty($data['csrf'])) {
            if (!csrf_verify($data)) {
                $json["message"] = $this->message->error("Erro ao enviar, favor use o formulário")->render();
                json_encode($json);
                return;
            }

            if (empty($data["password"]) || empty($data["password_re"])) {
                $json["message"] = $this->message->info("Informe e repita a senha para continuar")->render();
                echo json_encode($json);
                return;
            }

            list($email, $code) = explode("|", $data["code"]);
            $auth = new Auth();

            if ($auth->reset($email, $code, $data["password"], $data["password_re"])) {
                $this->message->success("Senha alterada com sucesso. Vamos controlar")->flash();
                $json["redirect"] = url("/entrar");
            } else {
                $json["message"] = $auth->message()->before("Oppss! ")->render();
            }
            echo json_encode($json);
            return;
        }
        $head = $this->seo->render(
            "Crie sua nova senha no " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url("/recuperar"),
            theme("/assets/images/share.jpg")
        );

        echo ($this->view->render("auth-reset", [
            "head" => $head,
            "code" => $data['code']
        ]));
    }

    public function register(?array $data): void
    {

        if (!empty($data["csrf"])) {
            if (!csrf_verify($data)) {
                $json["message"] = $this->message->error("Erro ao enviar, favor use o formulário")->render();
                echo json_encode($json);
                return;
            }

            if (in_array("", $data)) {
                $json["message"] = $this->message->info("Informe seus dados para criar sua conta")->render();
                echo json_encode($json);
                return;
            }

            $auth = new Auth();
            $user = new User();

            $data = (filter_var_array($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $user->bootstrap(
                $data["first_name"],
                $data["last_name"],
                $data["email"],
                $data["password"],
            );

            if ($auth->register($user)) {
                $json["redirect"] = url("/confirmar");
            } else {
                $json["message"] = $auth->message()->before("Oppss! ")->render();
            }


            echo json_encode($json);
            return;
        }

        $head = ($this->seo->render(
            "Criar conta - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url("/cadastrar"),
            theme("/assets/images/share.jpg")
        ));

        echo ($this->view->render("auth-register", [
            "head" => $head
        ]));
    }

    public function success(array $data): void
    {
        $email = base64_decode($data['email']);
        $user = (new User)->findByEmail($email);
        if ($user && $user->status != "confirmed") {
            $user->status = "confirmed";
            $user->save();
        }

        $head = ($this->seo->render(
            "Bem vindo(a) ao " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url("/obrigado"),
            theme("/assets/images/share.jpg")

        ));

        echo ($this->view->render("optin", [
            "head" => $head,
            "data" => (object)[
                "title" => "Tudo pronto. Você já pode controlar",
                "desc" => "Bem-vindo(a) ao seu controle de contas, vamos tomar um café?",
                "image" => theme("/assets/images/optin-success.jpg"),
                "link" => url("/entrar"),
                "linkTitle" => "Fazer Login"
            ]
        ]));
    }

    public function confirm(): void
    {

        $head = ($this->seo->render(
            "Confirme seu cadastro - " . CONF_SITE_NAME,
            CONF_SITE_DESC,
            url("/confirmar"),
            theme("/assets/images/share.jpg")
        ));

        echo ($this->view->render("optin", [
            "head" => $head,
            "data" => (object)[
                "title" => "Falta pouco! Confirme seu cadastro.",
                "desc" => "Enviamos um link de confirmação para seu e-mail. Acesse e siga as instruções para concluir seu cadastro
                e comece a controlar com o CaféControl",
                "image" => theme("/assets/images/optin-confirm.jpg")
            ]
        ]));
    }
    public function terms(): void
    {
        $head = $this->seo->render(
            CONF_SITE_NAME . " - termos de uso",
            CONF_SITE_DESC,
            url("/termos"),
            theme("/assets/images/share.jpg")
        );

        echo ($this->view->render("terms", [
            "head" => $head
        ]));
    }



    public function error(array $data): void
    {
        $error = new \stdClass();

        switch ($data["errcode"]) {
            case "problemas":
                $error->code = "OPS";
                $error->title = "Estamos enfrentado problemas!";
                $error->message = "Parce que nosso serviços não está disponível no momento. Já estamos vendo isso mas caso precise, envie um e-mail :)";
                $error->linkTitle = "ENVIAR E-MAIL";
                $error->link = "mailto:" . CONF_MAIL_SUPPORT;
                break;
            case "manutencao":
                $error->code = "OPS";
                $error->title = "Desculpe estamos em manuteção!";
                $error->message = "Voltamos logo! Por hora estamos trabalhando para melhorar nosso conteúdo para você controlar melhor as suas contas :p";
                $error->linkTitle = null;
                $error->link = null;
                break;
            default:
                $error->code = $data['errcode'];
                $error->title = "Oops. Conteúdo indisponível :/";
                $error->message = "Sentimos muito, mas o conteúdo que você tentou acessar não existe, está indisponível ou foi removido :/";
                $error->linkTitle = "Continue navegando!";
                $error->link = url_back();
                break;
        };


        $head = $this->seo->render(
            "{$error->code} | {$error->title}",
            $error->message,
            url("/ops/{$error->code}"),
            theme("/assets/images/share.jpg"),
            false
        );
        echo $this->view->render("error", [
            "head" => $head,
            "error" => $error
        ]);
    }
}
