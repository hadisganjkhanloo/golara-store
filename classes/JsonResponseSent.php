<?php

/** فقط برای تست‌های خودکار: جای exit تو JsonResponse پرتاب می‌شه (JSON_RESPONSE_THROW) */
class JsonResponseSent extends Exception
{
    public function __construct(public array $payload, public int $status)
    {
        parent::__construct('JSON response ' . $status);
    }
}
