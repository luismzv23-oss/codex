<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        return view('home/index', [
            'pageTitle' => 'Codex ERP | Tu empresa conectada',
            'loginUrl' => site_url('login'),
            'dashboardUrl' => site_url('dashboard'),
            'isAuthenticated' => auth_check(),
        ]);
    }
}
