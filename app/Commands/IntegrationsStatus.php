<?php
namespace App\Commands;
final class IntegrationsStatus extends IntegrationCommand
{
    protected $name='integrations:status';
    protected $description='Consulta entregas de integración de una empresa, sin ejecutarlas.';
}
