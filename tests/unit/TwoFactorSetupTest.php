<?php

use App\Controllers\Api\V1\AuthController;
use App\Libraries\AuthService;
use App\Models\UserModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class TwoFactorSetupTest extends CIUnitTestCase
{
    public function testSessionSetupCannotReplaceAnActiveSecret(): void
    {
        $auth = $this->createMock(AuthService::class);
        $auth->method('user')->willReturn(['id' => 'user-test']);
        Services::injectMock('auth', $auth);

        $users = $this->createMock(UserModel::class);
        $users->expects($this->once())->method('findForAuthById')->with('user-test')
            ->willReturn(['id' => 'user-test', 'two_factor_enabled' => 1]);
        $users->expects($this->never())->method('update');
        Factories::injectMock('models', UserModel::class, $users);

        $controller = new AuthController();
        $controller->initController(service('request'), service('response'), service('logger'));
        $this->assertSame(409, $controller->twoFactorSetup()->getStatusCode());
    }
}
