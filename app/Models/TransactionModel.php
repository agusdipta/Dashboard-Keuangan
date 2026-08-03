<?php

namespace App\Models;

use CodeIgniter\Model;

class TransactionModel extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id',
        'category_id',
        'transaction_date',
        'description',
        'amount',
        'type',
    ];
    protected $useTimestamps = true;
}
