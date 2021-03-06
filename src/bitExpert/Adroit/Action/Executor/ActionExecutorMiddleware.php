<?php

/**
 * This file is part of the Adroit package.
 *
 * (c) bitExpert AG
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types = 1);

namespace bitExpert\Adroit\Action\Executor;

use bitExpert\Adroit\Action\Resolver\ActionResolveException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use bitExpert\Adroit\Domain\Payload;

class ActionExecutorMiddleware
{
    /**
     * @var string
     */
    protected $actionAttribute;
    /**
     * @var string
     */
    protected $domainPayloadAttribute;

    /**
     * @param string $actionAttribute
     * @param string $domainPayloadAttribute
     * @throws \InvalidArgumentException
     */
    public function __construct(string $actionAttribute, string $domainPayloadAttribute)
    {
        $this->actionAttribute = $actionAttribute;
        $this->domainPayloadAttribute = $domainPayloadAttribute;
    }

    /**
     * @inheritdoc
     * @throws ActionResolveException
     * @throws ActionExecutionException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $action = $this->getAction($request);

        if (!$action) {
            throw new ActionExecutionException('Could not find action in request');
        }

        if (!is_callable($action)) {
            throw new ActionExecutionException(sprintf(
                'Could not execute action "%s" because it is not callable',
                is_object($action) ? get_class($action) : (string) $action
            ));
        }

        $responseOrPayload = $action($request, $response);

        if (!($responseOrPayload instanceof Payload) && !($responseOrPayload instanceof ResponseInterface)) {
            throw new ActionExecutionException(sprintf(
                'The action "%s" did neither return an instance of "%s" nor an instance of "%s"',
                is_object($action) ? get_class($action) : (string)$action,
                Payload::class,
                ResponseInterface::class
            ));
        }


        if ($next) {
            $response = $next($request->withAttribute($this->domainPayloadAttribute, $responseOrPayload), $response);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    protected function getAction(ServerRequestInterface $request)
    {
        return $request->getAttribute($this->actionAttribute);
    }
}
