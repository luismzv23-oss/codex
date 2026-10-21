<?php
namespace App\Libraries\Core;

final class AccessException extends \RuntimeException
{
    public function __construct(string $message, int $status = 403)
    {
        parent::__construct($message, $status);
    }
}
