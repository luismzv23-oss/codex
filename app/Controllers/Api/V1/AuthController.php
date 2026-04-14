<?php

namespace App\Controllers\Api\V1;

class AuthController extends BaseApiController
{
    public function login()
    {
        $payload = $this->payload();
        $login = trim((string) ($payload['login'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($login === '' || $password === '') {
            return $this->fail('Login y password son obligatorios.', 422);
        }

        if (! $this->authService()->attempt($login, $password)) {
            return $this->fail('Credenciales invalidas.', 401);
        }

        return $this->success($this->apiUser());
    }

    public function me()
    {
        return $this->success($this->apiUser());
    }

    public function logout()
    {
        $this->authService()->logout();

        return $this->success(['message' => 'Sesion cerrada correctamente.']);
    }

    public function forgotPassword()
    {
        $payload = $this->payload();
        $email = trim((string) ($payload['email'] ?? ''));

        if ($email === '') {
            return $this->fail('El correo es obligatorio.', 422);
        }

        $reset = $this->authService()->createPasswordReset($email);

        if (! $reset) {
            return $this->fail('No encontramos un usuario activo con ese correo.', 404);
        }

        return $this->success([
            'selector' => $reset['selector'],
            'token' => $reset['token'],
            'reset_url' => site_url('reset-password/' . $reset['selector'] . '/' . $reset['token']),
        ]);
    }

    public function resetPassword()
    {
        $payload = $this->payload();
        $selector = (string) ($payload['selector'] ?? '');
        $token = (string) ($payload['token'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        if ($selector === '' || $token === '' || $password === '') {
            return $this->fail('Selector, token y password son obligatorios.', 422);
        }

        if (! $this->authService()->resetPassword($selector, $token, $password)) {
            return $this->fail('No fue posible restablecer la contrasena.', 400);
        }

        return $this->success(['message' => 'Contrasena actualizada correctamente.']);
    }
}
