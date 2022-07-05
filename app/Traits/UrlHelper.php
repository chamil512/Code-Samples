<?php
namespace App\Traits;

trait UrlHelper
{
    /**
     * Get correct route URI which matched with the system routes
     * @param string $urlPath
     * @return string
     */
    public function getRouteUri(string $urlPath): string
    {
        //extract URL
        $route = collect(\Route::getRoutes())->first(function($route) use($urlPath){

            $method = request()->method();
            return $route->matches(request()->create($urlPath, $method));
        });

        if($route!=null)
        {
            $urlPath = $route->uri;

            //check for url params
            $paramStart = strpos($urlPath, "{");
            if($paramStart)
            {
                //get rest of the URL without URL params
                $urlPath = substr($urlPath, 0, $paramStart);
            }
        }

        $slash = "/";
        $urlPath = $slash.ltrim($urlPath, $slash);
        $urlPath = rtrim($urlPath, $slash);

        return $urlPath;
    }

    /**
     * Check permissions if this user have permission to current route
     * @return string
     */
    public function getCurrentRouteUri(): string
    {
        $urlPath = request()->getPathInfo();

        return $this->getRouteUri($urlPath);
    }

    /**
     * Check permissions if this user have permission to current route
     * @return string
     */
    public function getCurrentModule(): string
    {
        $routeUri= $this->getCurrentRouteUri();

        return $this->getModuleFromUri($routeUri);
    }

    /**
     * Get module name if current controller belongs to a module
     * @param string $url
     * @return string
     */
    public function getModuleFromUri($url): string
    {
        $controller = $this->getControllerFromRoute($url);

        $del = '\\';
        $controllerExp = @explode($del, $controller);

        $module = "";
        if($controllerExp[0] == "Modules")
        {
            $module = strtolower($controllerExp[1]);
        }

        return $module;
    }

    /**
     * Get controller of the specific URI
     * @param string $urlPath
     * @return string
     */
    public function getControllerFromRoute(string $urlPath): string
    {
        $route = collect(\Route::getRoutes())->first(function($route) use($urlPath){

            return $route->matches(request()->create($urlPath));
        });

        $controller = "";
        if($route)
        {
            $del = "@";
            $controller = $route->action["controller"];
            $controller = @explode($del, $controller);
            $controller = $controller[0];
        }

        return $controller;
    }

    public function getClassName($model): string
    {
        return get_class($model);
    }

    public function generateClassNameHash($className): string
    {
        return md5($className);
    }
}
