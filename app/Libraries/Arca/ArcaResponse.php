<?php

namespace App\Libraries\Arca;

class ArcaResponse
{
    public function __construct(
        public string  $status,           // 'authorized', 'rejected', 'error', 'partial', 'not_applicable'
        public ?string $cae = null,
        public ?string $caeDueDate = null,
        public ?string $resultCode = null,
        public ?string $message = null,
        public array   $observations = [],
        public array   $errors = [],
        public array   $requestPayload = [],
        public array   $responsePayload = [],
        public ?string $serviceSlug = null,
        public ?string $environment = null,
        public ?string $authorizedAt = null,
    ) {}

    public function isAuthorized(): bool
    {
        return $this->status === 'authorized';
    }

    public function toArray(): array
    {
        return [
            'status'           => $this->status,
            'cae'              => $this->cae,
            'cae_due_date'     => $this->caeDueDate,
            'result_code'      => $this->resultCode,
            'message'          => $this->message,
            'observations'     => $this->observations,
            'errors'           => $this->errors,
            'service_slug'     => $this->serviceSlug,
            'environment'      => $this->environment,
            'authorized_at'    => $this->authorizedAt,
            'request_payload'  => $this->requestPayload,
            'response_payload' => $this->responsePayload,
        ];
    }
}
