<?php

/**
 * This file is part of the Tracy (https://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Tracy;


use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class DeferredContentPsr7 extends DeferredContentBase
{

    private ServerRequestInterface $request;

    public function __construct(SessionStorage $sessionStorage)
    {
        $this->sessionStorage = $sessionStorage;
        $this->requestId = $_SERVER['HTTP_X_TRACY_AJAX'] ?? Helpers::createId();
    }

    public function setRequest(ServerRequestInterface $request) : DeferredContentPsr7
    {
        $this->request = $request;
        return $this;
    }

    public function sendAssets() : ResponseInterface
    {
        if (!isset($this->request)) {
            throw new \LogicException('Missing request for PSR7');
        }
        if (headers_sent($file, $line) || ob_get_length()) {
            throw new \LogicException(
              __METHOD__.'() called after some output has been sent. '
              .($file ? "Output started at $file:$line." : 'Try Tracy\OutputDebugger to find where output started.'),
            );
        }

        $asset = $this->request->getQueryParams()['_tracy_bar'] ?? null;
        if ($asset === 'js') {
            $str = $this->buildJsCss();
            return new Response(
              200,
              [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'max-age=864000',
                'Content-Length' => strlen($str),
              ],
              $str
            );
        }

        $this->useSession = $this->sessionStorage->isAvailable();
        if (!$this->useSession) {
            return new Response(204);
        }

        $this->clean();

        if (is_string($asset) && preg_match('#^content(-ajax)?\.(\w+)$#', $asset, $m)) {
            [, $ajax, $requestId] = $m;
            header('Content-Type: application/javascript; charset=UTF-8');
            header('Cache-Control: max-age=60');
            header_remove('Set-Cookie');
            $str = $ajax ? '' : $this->buildJsCss();
            $data = &$this->getItems('setup');
            $str .= $data[$requestId]['code'] ?? '';
            unset($data[$requestId]);
            return new Response(
              200,
              [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'max-age=60',
                'Content-Length' => strlen($str),
              ],
              $str
            );
        }

        $response = new Response(204);

        if (Helpers::isAjaxPsr7($this->request)) {
            return $response->withHeader('X-Tracy-Ajax','1'); // session must be already locked
        }
        return $response;
    }

    public function isAvailable() : bool
    {
        return $this->useSession && $this->sessionStorage->isAvailable();
    }

}
