<?php

namespace nova\init\ui\src\app;

use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\iApplication;
use nova\framework\request\Response;
use nova\framework\request\Route;
use nova\framework\request\RouteObject;
use nova\plugin\minify\NovaMinify;
use nova\plugin\task\Task;
use function nova\framework\route;
use const app\ROOT_PATH;

class Application implements iApplication
{


    function onAppEnd()
    {

    }

    function onRouteNotFound(?RouteObject $route, string $uri): ?Response
    {
        return null;
    }

    function onApplicationError(?RouteObject $route, string $uri): ?Response
    {
        return null;
    }

    function onRoute(RouteObject $route)
    {

    }

    function routeStatic(): void
    {
        EventManager::addListener("response.static.after", function ($event, $file) {
            $name = str_replace(ROOT_PATH . '/app', '', $file);
            if (!str_ends_with($name, ".js")) {
                return;
            }
            echo <<<EOF
;if(!window.novaFiles){window.novaFiles = {};}
window.novaFiles['$name'] = true;
EOF;

        });
        EventManager::addListener("route.before",function ($event, &$uri){
            if (str_starts_with($uri,"/static/")){
                $file = substr($uri,8);
                $file = str_replace("..","",$file);
                throw new AppExitException(Response::asStatic(ROOT_PATH.'/app/static/'.$file),"Send static file");
            }
        });
    }
    function onFrameworkStart(): void
    {
        $this->routeStatic();
    }

    function onFrameworkEnd()
    {

    }

    function onAppStart()
    {

    }
}