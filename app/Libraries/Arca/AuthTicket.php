<?php

namespace App\Libraries\Arca;

class AuthTicket
{
    public function __construct(
        public string    $token,
        public string    $sign,
        public string    $service,
        public \DateTime $expiresAt,
        public string    $environment = 'homologacion'
    ) {}

    public function isValid(): bool
    {
        return $this->expiresAt > new \DateTime();
    }

    public function toArray(): array
    {
        return [
            'token'       => $this->token,
            'sign'        => $this->sign,
            'service'     => $this->service,
            'expires_at'  => $this->expiresAt->format('Y-m-d H:i:s'),
            'environment' => $this->environment,
        ];
    }
}
