<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\User;

class Controller
{
    /**
     * The authenticated user
     *
     * @var User|null
     */
    protected $user;

    /**
     * Create a new controller instance
     */
    public function __construct()
    {
        // Check if user is authenticated
        $this->middleware('auth');
        
        // Get the authenticated user
        $this->user = auth()->user();
        
        // Share user data with all views
        View::share('user', $this->user);
    }
    
    /**
     * Execute an action on the controller.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function callAction($method, $parameters)
    {
        return call_user_func_array([$this, $method], $parameters);
    }
    
    /**
     * Register middleware on the controller.
     *
     * @param  string|array  $middleware
     * @return void
     */
    protected function middleware($middleware)
    {
        if (is_array($middleware)) {
            foreach ($middleware as $m) {
                $this->middleware($m);
            }
            return;
        }
        
        $middleware = "App\\Http\\Middleware\\" . $middleware;
        
        if (class_exists($middleware)) {
            (new $middleware)->handle();
        }
    }
    
    /**
     * Authorize a given action for the user.
     *
     * @param  mixed  $ability
     * @param  array|mixed  $arguments
     * @return void
     *
     * @throws \App\Exceptions\UnauthorizedException
     */
    public function authorize($ability, $arguments = [])
    {
        if (! $this->user->can($ability, $arguments)) {
            throw new \App\Exceptions\UnauthorizedException('This action is unauthorized.');
        }
    }
    
    /**
     * Validate the given request with the given rules.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return array
     *
     * @throws \App\Exceptions\ValidationException
     */
    public function validate($data, array $rules, array $messages = [], array $customAttributes = [])
    {
        $validator = validator($data, $rules, $messages, $customAttributes);
        
        if ($validator->fails()) {
            throw new \App\Exceptions\ValidationException($validator);
        }
        
        return $validator->validated();
    }
    
    /**
     * Create a new JSON response instance.
     *
     * @param  mixed  $data
     * @param  int  $status
     * @param  array  $headers
     * @return \App\Http\JsonResponse
     */
    public function json($data = [], $status = 200, array $headers = [])
    {
        return new \App\Http\JsonResponse($data, $status, $headers);
    }
    
    /**
     * Create a new view instance.
     *
     * @param  string  $view
     * @param  array  $data
     * @return \App\Core\View
     */
    public function view($view, $data = [])
    {
        return new View($view, $data);
    }
    
    /**
     * Redirect to a named route.
     *
     * @param  string  $route
     * @param  array  $parameters
     * @param  int  $status
     * @param  array  $headers
     * @return \App\Http\RedirectResponse
     */
    public function redirect($route, $parameters = [], $status = 302, $headers = [])
    {
        return redirect()->route($route, $parameters, $status, $headers);
    }
    
    /**
     * Redirect back to the previous page.
     *
     * @param  int  $status
     * @param  array  $headers
     * @param  mixed  $fallback
     * @return \App\Http\RedirectResponse
     */
    public function back($status = 302, $headers = [], $fallback = false)
    {
        return back($status, $headers, $fallback);
    }
}
