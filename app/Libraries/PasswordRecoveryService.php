<?php
namespace App\Libraries;

/** Recovery tokens are delivered privately; they never enter an HTTP response or log. */
class PasswordRecoveryService
{
    private $sender;

    public function __construct(?callable $sender = null)
    {
        $this->sender = $sender;
    }

    public function request(string $email): void
    {
        $config = config('Email');
        if ($this->sender === null && (
            !filter_var($config->fromEmail, FILTER_VALIDATE_EMAIL)
            || ($config->protocol === 'smtp' && trim($config->SMTPHost) === '')
        )) {
            throw new \RuntimeException('La recuperación por correo no está configurada.', 503);
        }

        $reset = service('auth')->createPasswordReset($email);
        if ($reset === null) return;

        $url = site_url('reset-password/' . $reset['selector'] . '/' . $reset['token']);
        try {
            if ($this->sender !== null) {
                $sent = ($this->sender)($email, $url);
            } else {
                $mail = service('email', null, false);
                $mail->setFrom($config->fromEmail, $config->fromName);
                $mail->setTo($email);
                $mail->setSubject('Restablecer contraseña de Codex');
                $mail->setMailType('text');
                $mail->setMessage("Para restablecer tu contraseña, abrí este enlace:\n" . $url . "\nEl enlace vence en una hora. Si no lo solicitaste, ignorá este mensaje.");
                $sent = $mail->send();
            }
            if (!$sent) log_message('error', 'Password recovery delivery failed.');
        } catch (\Throwable $e) {
            // Never expose transport diagnostics, recipient existence or recovery tokens.
            log_message('error', 'Password recovery delivery failed.');
        }
    }
}
