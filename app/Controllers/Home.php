<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return view('home/index', [
            'pageTitle' => 'Codex',
            'apiHealthUrl' => site_url('api/v1/health'),
            'loginUrl' => site_url('login'),
            'dashboardUrl' => site_url('dashboard'),
            'isAuthenticated' => auth_check(),
        ]);
    }
}
