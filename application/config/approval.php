<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['approval_roles'] = [
    1 => ['Manager'],        // Level 1 approval
    2 => ['Financer'],       // Level 2 approval
    3 => ['Admin'],          // Final approval
];

$config['auto_approve_rules'] = [
    'discount' => [
        'max_amount' => 100, // auto approve <= 100
    ],
    'refund' => [
        'max_amount' => 200,
    ],
];
