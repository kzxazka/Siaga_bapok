<?php

use App\Core\Database;
use App\Models\User;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string  $abstract
     * @param  array   $parameters
     * @return mixed|\App\Core\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return \App\Core\Application::getInstance();
        }
        
        return \App\Core\Application::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed|\App\Core\Config
     */
    function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('config');
        }
        
        if (is_array($key)) {
            return app('config')->set($key);
        }
        
        return app('config')->get($key, $default);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the available auth instance.
     *
     * @return \App\Core\Auth
     */
    function auth()
    {
        return app('auth');
    }
}

if (!function_exists('bcrypt')) {
    /**
     * Hash the given value against the bcrypt algorithm.
     *
     * @param  string  $value
     * @param  array  $options
     * @return string
     */
    function bcrypt($value, $options = [])
    {
        return password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $options['rounds'] ?? 10,
        ]);
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a CSRF token form field.
     *
     * @return string
     */
    function csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the CSRF token value.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    function csrf_token()
    {
        $session = app('session');
        
        if (isset($session)) {
            return $session->token();
        }
        
        throw new RuntimeException('Application session store not set.');
    }
}

if (!function_exists('database')) {
    /**
     * Get the database connection instance.
     *
     * @param  string  $connection
     * @return \App\Core\Database\Connection
     */
    function database($connection = null)
    {
        return Database::connection($connection);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the passed variables and end the script.
     *
     * @param  mixed  $args
     * @return void
     */
    function dd(...$args)
    {
        foreach ($args as $x) {
            echo '<pre>';
            var_dump($x);
            echo '</pre>';
        }
        
        die(1);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters in a string.
     *
     * @param  string  $value
     * @param  bool  $doubleEncode
     * @return string
     */
    function e($value, $doubleEncode = true)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve an old input item.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function old($key = null, $default = null)
    {
        return app('request')->old($key, $default);
    }
}

if (!function_exists('redirect')) {
    /**
     * Get an instance of the redirector.
     *
     * @param  string|null  $to
     * @param  int  $status
     * @param  array  $headers
     * @param  bool  $secure
     * @return \App\Core\Http\RedirectResponse|\App\Core\Routing\Redirector
     */
    function redirect($to = null, $status = 302, $headers = [], $secure = null)
    {
        if (is_null($to)) {
            return app('redirect');
        }
        
        return app('redirect')->to($to, $status, $headers, $secure);
    }
}

if (!function_exists('request')) {
    /**
     * Get an instance of the current request or an input item from the request.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return \App\Core\Http\Request|string|array
     */
    function request($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('request');
        }
        
        if (is_array($key)) {
            return app('request')->only($key);
        }
        
        return app('request')->input($key, $default);
    }
}

if (!function_exists('response')) {
    /**
     * Return a new response from the application.
     *
     * @param  string  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \App\Core\Http\Response|\App\Core\Http\ResponseFactory
     */
    function response($content = '', $status = 200, array $headers = [])
    {
        $factory = app('response');
        
        if (func_num_args() === 0) {
            return $factory;
        }
        
        return $factory->make($content, $status, $headers);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a URL to a named route.
     *
     * @param  string  $name
     * @param  mixed  $parameters
     * @param  bool  $absolute
     * @return string
     */
    function route($name, $parameters = [], $absolute = true)
    {
        return app('url')->route($name, $parameters, $absolute);
    }
}

if (!function_exists('session')) {
    /**
     * Get / set the specified session value.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed|\App\Core\Session\Store
     */
    function session($key = null, $default = null)
    {
        if (is_null($key)) {
            return app('session');
        }
        
        if (is_array($key)) {
            return app('session')->put($key);
        }
        
        return app('session')->get($key, $default);
    }
}

if (!function_exists('url')) {
    /**
     * Generate a url for the application.
     *
     * @param  string  $path
     * @param  mixed  $parameters
     * @param  bool  $secure
     * @return string
     */
    function url($path = null, $parameters = [], $secure = null)
    {
        return app('url')->to($path, $parameters, $secure);
    }
}

if (!function_exists('validator')) {
    /**
     * Create a new validator instance.
     *
     * @param  array  $data
     * @param  array  $rules
     * @param  array  $messages
     * @param  array  $customAttributes
     * @return \App\Core\Validation\Validator
     */
    function validator(array $data = [], array $rules = [], array $messages = [], array $customAttributes = [])
    {
        $validator = app('validator');
        
        if (func_num_args() === 0) {
            return $validator;
        }
        
        return $validator->make($data, $rules, $messages, $customAttributes);
    }
}

if (!function_exists('view')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  array  $mergeData
     * @return \App\Core\View\View
     */
    function view($view = null, $data = [], $mergeData = [])
    {
        $factory = app('view');
        
        if (func_num_args() === 0) {
            return $factory;
        }
        
        return $factory->make($view, $data, $mergeData);
    }
}

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string  $locale
     * @return string|array|null
     */
    function __($key = null, $replace = [], $locale = null)
    {
        if (is_null($key)) {
            return $key;
        }
        
        return app('translator')->get($key, $replace, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * Translate the given message.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string  $locale
     * @return string|array|null
     */
    function trans($key = null, $replace = [], $locale = null)
    {
        return __($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translates the given message based on a count.
     *
     * @param  string  $key
     * @param  int|array|\Countable  $number
     * @param  array  $replace
     * @param  string  $locale
     * @return string
     */
    function trans_choice($key, $number, array $replace = [], $locale = null)
    {
        return app('translator')->choice($key, $number, $replace, $locale);
    }
}
