<?php

namespace app;

use nova\framework\iApplication;
use nova\framework\request\Response;
use nova\framework\request\Route;
use nova\framework\request\RouteObject;
use nova\plugin\task\Task;
use function nova\framework\route;

class Application implements iApplication
{


    function onAppEnd()
    {
        // TODO: Implement onAppEnd() method.
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
    function onFrameworkStart(): void
    {
        Task::register();
        Route::get("/",route("index","main","hello"));
        Route::get("/active",route("index","active","index"));
        Route::get("/license",route("index","active","index"));
        Route::get("/favicon.ico",route("index","main","favicon"));
        Route::get("/static/{file}",route("index","main","static"));
    }

    function onFrameworkEnd()
    {
        // TODO: Implement onFrameworkEnd() method.
    }

    function onAppStart()
    {
        // TODO: Implement onAppStart() method.
    }
}