<?php

/**
 * This file is part of the Adroit package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bitExpert\Adroit;

use bitExpert\Adroit\Routing\PathfinderRoutingMiddleware;
use bitExpert\Adroit\Routing\RoutingMiddleware;
use bitExpert\Adroit\Action\Resolver\ActionResolverMiddleware;
use bitExpert\Adroit\Action\Resolver\DirectActionResolver;
use bitExpert\Adroit\Action\ActionMiddleware;
use bitExpert\Adroit\Domain\DomainPayload;
use bitExpert\Adroit\Responder\Resolver\ResponderResolverMiddleware;
use bitExpert\Adroit\Responder\ResponderMiddleware;
use bitExpert\Pathfinder\Psr7Router;
use bitExpert\Pathfinder\Router;
use bitExpert\Pathfinder\Route;
use bitExpert\Pathfinder\RoutingResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\EmitterInterface;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Stratigility\MiddlewarePipe;

/**
 * MiddlewarePipe implementation for an Adroit web application.
 *
 * @api
 */
class WebApplication extends MiddlewarePipe
{
    /**
     * @var EmitterInterface
     */
    protected $emitter;
    /**
     * @var callable[]
     */
    protected $beforeRoutingMiddlewares;
    /**
     * @var callable[]
     */
    protected $beforeActionMiddlewares;
    /**
     * @var callable[]
     */
    protected $beforeResponderMiddlewares;
    /**
     * @var bool
     */
    protected $initialized;
    /**
     * @var callable
     */
    protected $errorHandler;
    /**
     * @var callable
     */
    protected $routingMiddleware;
    /**
     * @var callable
     */
    protected $targetMiddleware;
    /**
     * @var callable
     */
    protected $responderMiddleware;
    /**
     * @var string
     */
    protected $defaultRouteClass;

    /**
     * Creates a new {\bitExpert\Adroit\WebApplication}.
     *
     * @param RoutingMiddleware $routingMiddleware
     * @param ActionMiddleware $targetMiddleware
     * @param ResponderMiddleware $responderMiddleware
     * @param EmitterInterface $emitter
     */
    public function __construct(
        RoutingMiddleware $routingMiddleware,
        ActionMiddleware $targetMiddleware,
        ResponderMiddleware $responderMiddleware,
        EmitterInterface $emitter = null)
    {
        parent::__construct();

        $this->defaultRouteClass = null;
        $this->errorHandler = null;
        $this->initialized = false;

        $this->routingMiddleware = $routingMiddleware;
        $this->actionMiddleware = $targetMiddleware;
        $this->responderMiddleware = $responderMiddleware;

        if (null === $emitter) {
            $emitter = new SapiEmitter();
        }

        $this->emitter = $emitter;

        $this->beforeRoutingMiddlewares = [];
        $this->beforeActionMiddlewares = [];
        $this->beforeResponderMiddlewares = [];
    }

    /**
     * Sets the exception handler
     * (chainable)
     *
     * @param callable $errorHandler
     * @return WebApplication
     */
    public function setErrorHandler(callable $errorHandler)
    {
        $this->errorHandler = $errorHandler;
        return $this;
    }

    /**
     * Adds the given middleware to the pipe before the routing middleware
     * (chainable)
     *
     * @param callable $middleware
     * @return $this
     */
    public function beforeRouting(callable $middleware)
    {
        $this->beforeRoutingMiddlewares[] = $middleware;
        return $this;
    }

    /**
     * Adds the given middleware to the pipe before the action middleware
     * (chainable)
     *
     * @param callable $middleware
     * @return $this
     */
    public function beforeAction(callable $middleware)
    {
        $this->beforeActionMiddlewares[] = $middleware;
        return $this;
    }

    /**
     * Adds the given middleware to the pipe after the action middleware and before the responder middleware
     * (chainable)
     *
     * @param callable $middleware
     * @return $this
     */
    public function beforeResponder(callable $middleware)
    {
        $this->beforeResponderMiddlewares[] = $middleware;
        return $this;
    }

    /**
     * Runs the application by invoking itself with the request and response, and emitting the returned response.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->initialize();
        $response = parent::__invoke($request, $response, $this->errorHandler);
        $this->emitter->emit($response);
    }

    /**
     * Pipes all given middlewares
     * @param callable[] $middlewares
     */
    protected function pipeAll(array $middlewares)
    {
        foreach ($middlewares as $middleware) {
            $this->pipe($middleware);
        }
    }

    /**
     * Initializes the application by piping the fixed middlewares (routing, action, responder)
     * and the configured middlewares in the right order
     */
    protected function initialize()
    {
        if ($this->initialized) {
            return;
        }

        $this->pipeAll($this->beforeRoutingMiddlewares);
        $this->pipe($this->routingMiddleware);
        $this->pipeAll($this->beforeActionMiddlewares);
        $this->pipe($this->actionMiddleware);
        $this->pipeAll($this->beforeResponderMiddlewares);
        $this->pipe($this->responderMiddleware);

        $this->initialized = true;
    }

    /**
     * Sets the default route class to use for implicit
     * route creation
     *
     * @param $defaultRouteClass
     * @throws \ConfigurationException
     */
    public function setDefaultRouteClass($defaultRouteClass)
    {
        if ($defaultRouteClass === Route::class) {
            $this->defaultRouteClass = $defaultRouteClass;
        } else {
            while ($parent = get_parent_class($defaultRouteClass)) {
                if ($parent === Route::class) {
                    $this->defaultRouteClass = $defaultRouteClass;
                    break;
                }
            }

            if ($this->defaultRouteClass !== $defaultRouteClass) {
                throw new \RuntimeException(sprintf(
                    'You tried to set %s as default route class which does not inherit %s',
                    $defaultRouteClass,
                    Route::class
                ));
            }
        }
    }

    /**
     * Creates a route using given params
     *
     * @param string $path
     * @param mixed $methods
     * @param string $name
     * @param mixed $target
     * @param \bitExpert\Pathfinder\Matcher\Matcher[] $matchers
     * @return Route
     */
    protected function createRoute($path, $methods, $name, $target, array $matchers = [])
    {
        $routeClass = $this->defaultRouteClass ? $this->defaultRouteClass : Route::class;
        $route = forward_static_call([$routeClass, 'create'], $methods, $path, $target);
        $route = $route->named($name);

        foreach ($matchers as $param => $paramMatchers) {
            $route = $route->ifMatches($param, $paramMatchers);
        }

        return $route;
    }

    /**
     * Adds a GET route
     *
     * @param $name
     * @param $path
     * @param $target
     * @param $matchers
     * @return WebApplication
     */
    public function get($name, $path, $target, array $matchers = [])
    {
        $route = $this->createRoute($path, 'GET', $name, $target, $matchers);
        $this->addRoute($route);
        return $this;
    }

    /**
     * Adds a POST route
     *
     * @param $name
     * @param $path
     * @param $target
     * @param $matchers
     * @return WebApplication
     */
    public function post($name, $path, $target, array $matchers = [])
    {
        $route = $this->createRoute($path, 'POST', $name, $target, $matchers);
        $this->addRoute($route);
        return $this;
    }

    /**
     * Adds a PUT route
     *
     * @param $name
     * @param $path
     * @param $target
     * @param $matchers
     * @return WebApplication
     */
    public function put($name, $path, $target, array $matchers = [])
    {
        $route = $this->createRoute($path, 'PUT', $name, $target, $matchers);
        $this->addRoute($route);
        return $this;
    }

    /**
     * Adds a DELETE route
     *
     * @param $name
     * @param $path
     * @param $target
     * @param $matchers
     * @return WebApplication
     */
    public function delete($path, $target, $name, array $matchers = [])
    {
        $route = $this->createRoute($path, 'DELETE', $name, $target, $matchers);
        $this->addRoute($route);
        return $this;
    }

    /**
     * Adds an OPTIONS route
     *
     * @param $name
     * @param $path
     * @param $target
     * @param $matchers
     * @return WebApplication
     */
    public function options($path, $target, $name, array $matchers = [])
    {
        $route = $this->createRoute($path, 'OPTIONS', $name, $target, $matchers);
        $this->addRoute($route);
        return $this;
    }

    /**
     * Adds given route to router
     *
     * @param Route $route
     * @return WebApplication
     */
    public function addRoute(Route $route)
    {
        $this->routingMiddleware->getRouter()->addRoute($route);
        return $this;
    }

    /**
     * Creates a WebApplication instance using the default configuration
     *
     * @param Router|null $router
     * @param array $targetResolvers
     * @param array $responderResolvers
     * @param EmitterInterface|null $emitter
     * @return WebApplication
     */
    public static function createDefault(
        Router $router = null,
        $targetResolvers = [],
        $responderResolvers = [],
        EmitterInterface $emitter = null
    ) {
        $router = ($router !== null) ? $router : new Psr7Router('');
        $targetResolvers = (count($targetResolvers) > 0) ? $targetResolvers : [new DirectActionResolver()];
        $routingMiddleware = new PathfinderRoutingMiddleware($router, RoutingResult::class);
        $targetMiddleware = new ActionResolverMiddleware($targetResolvers, RoutingResult::class, DomainPayload::class);
        $responderMiddleware = new ResponderResolverMiddleware($responderResolvers, DomainPayload::class);

        return new self($routingMiddleware, $targetMiddleware, $responderMiddleware, $emitter);
    }
}
