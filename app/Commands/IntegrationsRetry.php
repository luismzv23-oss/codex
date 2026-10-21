<?php
namespace App\Commands;
final class IntegrationsRetry extends IntegrationCommand
{
    protected $name='integrations:retry';
    protected $description='Solicita y audita el reintento de una entrega agotada o bloqueada.';
}
