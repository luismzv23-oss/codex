<?php
namespace App\Commands;
final class IntegrationsWork extends IntegrationCommand
{
    protected $name='integrations:work';
    protected $description='Procesa un lote de entregas locales, acotado a una empresa.';
}
