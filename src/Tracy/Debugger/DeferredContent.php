<?php

/**
 * This file is part of the Tracy (https://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Tracy;


/**
 * @internal
 */
final class DeferredContent extends DeferredContentBase
{


	public function __construct(SessionStorage $sessionStorage)
	{
		$this->sessionStorage = $sessionStorage;
		$this->requestId = $_SERVER['HTTP_X_TRACY_AJAX'] ?? Helpers::createId();
	}


	public function isAvailable(): bool
	{
		return $this->useSession && $this->sessionStorage->isAvailable();
	}

	public function sendAssets(): bool
	{
		if (headers_sent($file, $line) || ob_get_length()) {
			throw new \LogicException(
				__METHOD__ . '() called after some output has been sent. '
				. ($file ? "Output started at $file:$line." : 'Try Tracy\OutputDebugger to find where output started.'),
			);
		}

		$asset = $_GET['_tracy_bar'] ?? null;
		if ($asset === 'js') {
			header('Content-Type: application/javascript; charset=UTF-8');
			header('Cache-Control: max-age=864000');
			header_remove('Pragma');
			header_remove('Set-Cookie');
			$str = $this->buildJsCss();
			header('Content-Length: ' . strlen($str));
			echo $str;
			flush();
			return true;
		}

		$this->useSession = $this->sessionStorage->isAvailable();
		if (!$this->useSession) {
			return false;
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
			header('Content-Length: ' . strlen($str));
			echo $str;
			flush();
			return true;
		}

		if (Helpers::isAjax()) {
			header('X-Tracy-Ajax: 1'); // session must be already locked
		}

		return false;
	}

}
