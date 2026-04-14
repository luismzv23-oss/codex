<?php

namespace App\Models;

use CodeIgniter\Model;

abstract class BaseUuidModel extends Model
{
    protected $returnType       = 'array';
    protected $useAutoIncrement = false;
    protected $primaryKey       = 'id';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $beforeInsert     = ['assignUuid'];

    protected function assignUuid(array $data): array
    {
        if (! isset($data['data']['id']) || $data['data']['id'] === '') {
            $data['data']['id'] = app_uuid();
        }

        return $data;
    }
}
