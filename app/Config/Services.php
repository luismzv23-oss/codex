<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function auth(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new \App\Libraries\AuthService();
    }

    public static function audit(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('audit');
        }

        return new \App\Libraries\AuditService();
    }

    public static function jwt(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('jwt');
        }

        return new \App\Libraries\JwtService();
    }

    public static function twoFactor(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('twoFactor');
        }

        return new \App\Libraries\TwoFactorService();
    }

    public static function accounting(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('accounting');
        }

        return new \App\Libraries\AccountingService();
    }

    public static function withholding(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('withholding');
        }

        return new \App\Libraries\WithholdingService();
    }

    public static function libroIva(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('libroIva');
        }

        return new \App\Libraries\LibroIvaDigitalService();
    }

    public static function notification(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('notification');
        }

        return new \App\Libraries\NotificationService();
    }

    public static function bi(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('bi');
        }

        return new \App\Libraries\BusinessIntelligenceService();
    }

    public static function codexAssist(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('codexAssist');
        }

        return new \App\Libraries\CodexAssistService();
    }
}
