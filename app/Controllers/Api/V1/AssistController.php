<?php

namespace App\Controllers\Api\V1;

use App\Libraries\CodexAssistService;

class AssistController extends BaseApiController
{
    public function ask()
    {
        $payload = $this->payload();
        $question = trim((string)($payload['question'] ?? ''));

        if ($question === '') {
            return $this->fail('La pregunta es obligatoria.', 422);
        }

        $user = $this->apiUser();
        $context = [
            'company_id' => $user['company_id'] ?? null,
            'company'    => $user['company_name'] ?? null,
            'role'       => $user['role_slug'] ?? null,
            'module'     => $payload['module'] ?? null,
        ];

        $assist = new CodexAssistService();
        return $this->success($assist->ask($question, $context));
    }

    public function guide(string $topic)
    {
        $assist = new CodexAssistService();
        return $this->success($assist->getGuide($topic));
    }

    public function alerts()
    {
        $assist = new CodexAssistService();
        return $this->success($assist->analyzeAlerts($this->apiCompanyId()));
    }
}
