<?php

namespace App\Controllers;

class AuthController extends BaseController
{
    public function login()
    {
        return view('auth/login', ['pageTitle' => 'Iniciar sesion']);
    }

    public function attemptLogin()
    {
        $rules = [
            'login' => 'required|min_length[3]|max_length[150]',
            'password' => 'required|min_length[8]|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $login = (string) $this->request->getPost('login');

        if (! auth()->attempt($login, (string) $this->request->getPost('password'))) {
            service('audit')->logLogin('', false, 'Failed login attempt for: ' . $login);
            return redirect()->back()->withInput()->with('error', 'Credenciales invalidas o usuario inactivo.');
        }

        $user = auth()->user();
        service('audit')->logLogin($user['id'] ?? '', true);

        return redirect()->to('/dashboard');
    }

    public function forgotPassword()
    {
        return view('auth/forgot_password', ['pageTitle' => 'Recuperar contrasena']);
    }

    public function sendResetLink()
    {
        if (! $this->validate(['email' => 'required|valid_email|max_length[150]'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $reset = auth()->createPasswordReset((string) $this->request->getPost('email'));

        if (! $reset) {
            return redirect()->back()->with('error', 'No encontramos un usuario activo con ese correo.');
        }

        $url = site_url('reset-password/' . $reset['selector'] . '/' . $reset['token']);

        return redirect()->back()->with('message', 'En entorno local puedes restablecer desde este enlace: ' . $url);
    }

    public function resetPassword(string $selector, string $token)
    {
        if (! auth()->validatePasswordReset($selector, $token)) {
            return redirect()->to('/forgot-password')->with('error', 'El enlace de recuperacion no es valido o ya vencio.');
        }

        return view('auth/reset_password', [
            'pageTitle' => 'Restablecer contrasena',
            'selector' => $selector,
            'token' => $token,
        ]);
    }

    public function updatePassword(string $selector, string $token)
    {
        $rules = [
            'password'         => 'required|min_length[8]|max_length[255]|strong_password|not_common_password',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if (! auth()->resetPassword($selector, $token, (string) $this->request->getPost('password'))) {
            return redirect()->to('/forgot-password')->with('error', 'No fue posible restablecer la contrasena.');
        }

        return redirect()->to('/login')->with('message', 'Contrasena actualizada correctamente.');
    }

    public function logout()
    {
        auth()->logout();

        return redirect()->to('/login')->with('message', 'Sesion cerrada correctamente.');
    }
}
