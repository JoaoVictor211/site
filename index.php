<?php
ob_start();
/* error_reporting(0);

ini_set(“display_errors”, 0); */
require __DIR__ . "/vendor/autoload.php";
/** 
 * BOOTSTRAP
 */

use Source\Core\Session;
use CoffeeCode\Router\Router;

$session = new Session();
$router = new Router(url(), ":");

/** 
 * WEB ROUTES
 */

$router->namespace("Source\App");
$router->get("/", "Web:home");
$router->get("/sobre", "Web:about");


$router->group("/blog");
$router->get("/", "Web:blog");
$router->get("/p/{page}", "Web:blog");
$router->get("/{uri}", "Web:blogPost");
$router->post("/buscar", "Web:blogSearch");
$router->get("/buscar/{terms}/{page}", "Web:blogSearch");
$router->get("/em/{category}", "Web:blogCategory");
$router->get("/em/{category}/{page}", "Web:blogCategory");

//Auth
$router->group(null);
$router->get("/entrar", "Web:login");
$router->post("/entrar", "Web:login");


$router->get("/cadastrar", "Web:register");
$router->post("/cadastrar", "Web:register");

$router->get("/recuperar", "Web:forget");
$router->post("/recuperar", "Web:forget");

$router->get("/recuperar/{code}", "Web:reset");
$router->post("/recuperar/resetar", "Web:reset");

//Optin
$router->get("/confirmar", "Web:confirm");
$router->get("/obrigado/{email}", "Web:success");

//services
$router->get("/termos", "Web:terms");

/** 
 * APP
 */

$router->group("/app");
$router->get("/", "App:home");
$router->get("/sair", "App:logout");
/** 
 * ERROR ROUTES
 */

$router->namespace("Source\App")->group("/ops");
$router->get("/{errcode}", "Web:error");
/** 
 * ROUTES
 */

$router->dispatch();
/** 
 * ERROR REDIRECT
 */


if ($router->error()) {
    $router->redirect("/ops/{$router->error()}");
}
ob_end_flush();
