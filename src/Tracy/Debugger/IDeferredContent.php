<?php

namespace Tracy;

interface IDeferredContent
{
    public function isAvailable(): bool;
    public function getRequestId(): string;
    public function &getItems(string $key): array;
    public function addSetup(string $method, mixed $argument): void;
    public function sendAssets();
    public function clean(): void;
}