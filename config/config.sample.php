<?php
/**
 * JUSTI NUMBER ONE ERP — sample configuration.
 * The installer (install/index.php) generates config/config.php from this template.
 * NEVER commit config/config.php (it contains database credentials).
 */
return [
    // Database — Hostinger Premium MySQL (see README §4)
    'db' => [
        'host'    => 'localhost',            // e.g. localhost (Hostinger) or 127.0.0.1
        'name'    => 'PASTE_DB_NAME',        // e.g. u123456789_ctc_erp
        'user'    => 'PASTE_DB_USER',        // e.g. u123456789_ctcadmin
        'pass'    => 'PASTE_DB_PASSWORD',
        'charset' => 'utf8mb4',
        'port'    => 3306,
    ],

    'app' => [
        'name'      => 'JUSTI NUMBER ONE ERP',
        'company'   => 'Chishti Tobacco Company',
        'env'       => 'production',        // production | development
        'debug'     => false,               // true only on staging — NEVER on live site
        'timezone'  => 'Asia/Karachi',
        'currency'  => 'PKR',
        'language'  => 'en',                // en | ur
        'base_url'  => 'https://software.chishtitobaccocompany.com',
        'https'     => true,
    ],

    // Inventory costing: 'weighted_average' (default) or 'fifo'
    'costing_method' => 'weighted_average',

    // Allow stock to go negative only for authorized roles/users (default: no)
    'allow_negative_stock' => false,

    // Default output tax rate (%) for new products/invoices — adjust per FBR rules
    'default_tax_rate' => 18.0,

    'session' => [
        'name'     => 'CTC_ERP_SESS',
        'lifetime' => 120,                  // minutes of inactivity before logout
    ],

    'security' => [
        'login_max_attempts' => 5,          // per 15 minutes per IP+username
        'login_lockout_minutes' => 15,
    ],

    'uploads' => [
        'max_mb'        => 5,
        'allowed_types' => ['jpg','jpeg','png','gif','webp','pdf','csv','xls','xlsx','doc','docx'],
    ],
];
