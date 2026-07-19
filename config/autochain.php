<?php

return [

    'name' => 'Autochain Emma+',

    'author' => 'Lass',

    /*
    |--------------------------------------------------------------------------
    | Alertes automatiques
    |--------------------------------------------------------------------------
    */
    'alerts' => [
        'insurance_days_before' => (int) env('AUTOCHAIN_INSURANCE_ALERT_DAYS', 30),
        'technical_control_days_before' => (int) env('AUTOCHAIN_TECH_CONTROL_ALERT_DAYS', 30),
        'service_mileage_threshold' => (int) env('AUTOCHAIN_SERVICE_MILEAGE_THRESHOLD', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Blockchain / Web3
    |--------------------------------------------------------------------------
    | Aucune donnée nominative on-chain : uniquement technical_id + hashes.
    */
    'blockchain' => [
        'network' => env('AUTOCHAIN_BLOCKCHAIN_NETWORK', 'localhost'),
        'rpc_url' => env('AUTOCHAIN_RPC_URL', 'http://127.0.0.1:8545'),
        'chain_id' => (int) env('AUTOCHAIN_CHAIN_ID', 31337),
        'contract_address' => env('AUTOCHAIN_CONTRACT_ADDRESS'),
        'operator_private_key' => env('AUTOCHAIN_OPERATOR_PRIVATE_KEY'),
        'default_buyer_address' => env('AUTOCHAIN_DEFAULT_BUYER_ADDRESS'),
        'require_double_signature' => (bool) env('AUTOCHAIN_REQUIRE_DOUBLE_SIGNATURE', true),
        // true = simulation si le nœud est indisponible
        'allow_simulate_fallback' => (bool) env('AUTOCHAIN_ALLOW_SIMULATE_FALLBACK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stockage documents
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'disk' => env('AUTOCHAIN_DOCUMENTS_DISK', 'local'),
        'path' => 'documents/vehicules',
        'max_size_kb' => (int) env('AUTOCHAIN_DOCUMENT_MAX_KB', 10240),
    ],

];
