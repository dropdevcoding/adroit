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

namespace bitExpert\Adroit\Responder;

use bitExpert\Adroit\Domain\Payload;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * A Responder is responsible for building a {@link \Psr\Http\Message\ResponseInterface}
 * using the data fed to it by the {@link \bitExpert\Adroit\Action\Action}. Unlike the "traditional"
 * view concept a responder is able to fully influence the HTTP response, e.g. can set custom headers
 * and such.
 *
 * This interface is primarily meant for documentation use. You MAY use it but a callable will be fine, too.
 * @api
 */
interface Responder
{
    /**
     * Build the response (e.g. render a view template, return a redirect response, ...).
     * Might throw a {@link RuntimeException} in case sth. goes wrong.
     *
     * @param Payload $payload
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws RuntimeException
     */
    public function __invoke(Payload $payload, ResponseInterface $response) : ResponseInterface;
}
