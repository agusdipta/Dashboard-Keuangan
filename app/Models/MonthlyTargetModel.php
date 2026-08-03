<?php

namespace App\Models;

use CodeIgniter\Model;

class MonthlyTargetModel extends Model
{
    protected $table = 'monthly_targets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id',
        'period',
        'income_target',
        'expense_limit',
        'savings_target',
    ];
    protected $useTimestamps = true;
}
