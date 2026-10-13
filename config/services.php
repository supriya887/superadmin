<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'domaincheck' => [
        'base_url' => env('DOMAINCHECK_BASE_URL', 'https://domaincheck.httpapi.com'),
        'auth_userid' => env('DOMAINCHECK_AUTH_USERID', ''),
        'api_key' => env('DOMAINCHECK_API_KEY', ''),
    ],

    'jmd_sms' => [
        'base_url' => env('JMD_SMS_BASE_URL', 'http://msg.jmdinfotek.in/api/mt/SendSMS'),
        'user' => env('JMD_SMS_USER', 'demo'),
        'password' => env('JMD_SMS_PASSWORD', 'demo123'),
        'senderid' => env('JMD_SMS_SENDERID', 'WEBSMS'),
        'channel' => env('JMD_SMS_CHANNEL', 'Promo'),
        'dcs' => env('JMD_SMS_DCS', '0'),
        'flashsms' => env('JMD_SMS_FLASHSMS', '0'),
        'route' => env('JMD_SMS_ROUTE', '##'),
        'peid' => env('JMD_SMS_PEID', '##'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JMD Infotech WhatsApp Business API
    |--------------------------------------------------------------------------
    |
    | Template messages use POST /whatsapp/send with template_name + variableN.
    | Free-form text is only valid inside the 24h customer service window.
    |
    | Template variables follow the pattern: templateVariable-{Name}-{n}
    | where {Name} is the variable name from JMD's variable_map and {n}
    | is the position (1-based) in the template.
    |
    | Available templates:
    |   - order_confirmation: plan, Amount
    |   - renew_plan_confirmation: Date, Amount
    |   - upgrade_plan_confirm_v_two: plan, Amount, order
    |   - downgrade_plan_confirmation: plan, Amount, order
    |   - order_cancellation: order, reason
    |   - payment_confirmation: Amount, details, transactionID
    |   - partner_otp_v_two: otp, minutes
    |   - order_processing: order, status, Amount (NEW)
    */
    'jmd_whatsapp' => [
        'enabled' => filter_var(env('WHATSAPP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'base_url' => rtrim((string) env('WHATSAPP_BASE_URL', 'https://app.jmdinfotech.co.in/api/v1'), '/'),
        'api_token' => env('WHATSAPP_API_TOKEN', ''),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
        'language_code' => env('WHATSAPP_LANGUAGE_CODE', 'en_US'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 20),
        'templates' => [
            // New Order / Order Confirmation
            'new' => env('WHATSAPP_TEMPLATE_NEW_ORDER', 'order_confirmation'),
            'new_id' => env('WHATSAPP_TEMPLATE_NEW_ORDER_ID', '364275'),
            
            // Payment Confirmation
            'payment' => env('WHATSAPP_TEMPLATE_PAYMENT', 'payment_confirmation'),
            'payment_id' => env('WHATSAPP_TEMPLATE_PAYMENT_ID', '364275'),
            
            // Renew Plan
            'renew' => env('WHATSAPP_TEMPLATE_RENEW', 'renew_plan_confirmation'),
            'renew_id' => env('WHATSAPP_TEMPLATE_RENEW_ID', '364278'),
            
            // Upgrade Plan
            'upgrade' => env('WHATSAPP_TEMPLATE_UPGRADE', 'upgrade_plan_confirm_v_two'),
            'upgrade_id' => env('WHATSAPP_TEMPLATE_UPGRADE_ID', '408234'),
            
            // Downgrade Plan
            'downgrade' => env('WHATSAPP_TEMPLATE_DOWNGRADE', 'downgrade_plan_confirmation'),
            'downgrade_id' => env('WHATSAPP_TEMPLATE_DOWNGRADE_ID', '408235'),
            
            // Cancel Order
            'cancel' => env('WHATSAPP_TEMPLATE_CANCEL', 'order_cancellation'),
            'cancel_id' => env('WHATSAPP_TEMPLATE_CANCEL_ID', '408236'),
            
            // OTP Verification
            'otp' => env('WHATSAPP_TEMPLATE_OTP', 'partner_otp_v_two'),
            'otp_id' => env('WHATSAPP_TEMPLATE_OTP_ID', '408238'),
            
            // Order Processing Status (NEW)
            'processing' => env('WHATSAPP_TEMPLATE_PROCESSING', 'order_processing'),
            'processing_id' => env('WHATSAPP_TEMPLATE_PROCESSING_ID', ''),
            
            // Order Completed (NEW)
            'completed' => env('WHATSAPP_TEMPLATE_COMPLETED', 'order_completed'),
            'completed_id' => env('WHATSAPP_TEMPLATE_COMPLETED_ID', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox.co.in — GST E-Invoicing (IRN + QR) integration
    |--------------------------------------------------------------------------
    |
    | All credentials live in .env so they are never committed to source.
    | Required .env entries:
    |
    |   SANDBOX_API_KEY=key_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    |   SANDBOX_SECRET=secret_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
    |
    | Optional (sensible defaults are used otherwise):
    |
    |   SANDBOX_BASE_URL=https://api.sandbox.co.in
    |   SANDBOX_API_VERSION=1.0
    |   SANDBOX_GSTIN_API_VERSION=GST_EINV_1.03
    |   SANDBOX_AUTO_GENERATE=true       # auto-fire on order save
    |   SANDBOX_TIMEOUT=20
    */
    'sandbox' => [
        'base_url'           => env('SANDBOX_BASE_URL', 'https://api.sandbox.co.in'),
        'api_key'            => env('SANDBOX_API_KEY'),
        'api_secret'         => env('SANDBOX_SECRET'),
        'api_version'        => env('SANDBOX_API_VERSION', '1.0'),
        'gst_api_version'    => env('SANDBOX_GSTIN_API_VERSION', 'GST_EINV_1.03'),
        'timeout'            => (int) env('SANDBOX_TIMEOUT', 20),
        'auto_generate'      => filter_var(env('SANDBOX_AUTO_GENERATE', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aadhaar OTP (Sandbox.co.in OKYC)
    |--------------------------------------------------------------------------
    |
    | .env (Aadhaar app switch):
    |   AADHAAR_MODE=test|live
    |   AADHAAR_MOCK_OTPS=123456,000000   (used only when AADHAAR_MODE=test)
    |
    | .env (Sandbox API — read by SandboxAadhaarService when AADHAAR_MODE=live):
    |   SANDBOX_MODE=test|live
    |   SANDBOX_API_KEY_LIVE / SANDBOX_API_SECRET_LIVE / SANDBOX_BASE_URL_LIVE
    |   SANDBOX_API_KEY_TEST / SANDBOX_API_SECRET_TEST / SANDBOX_BASE_URL_TEST
    */
    'aadhaar' => [
        'mode' => in_array(
            strtolower(trim((string) env('AADHAAR_MODE', 'test'))),
            ['live'],
            true
        ) ? 'live' : 'test',
        'mock_otps' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AADHAAR_MOCK_OTPS', '123456,000000'))
        ))) ?: ['123456'],
    ],

    'einvoice' => [
        // Master switch
        'enabled' => env('EINVOICE_ENABLED', true),
        
        // Current mode: 'test' or 'live'
        'mode' => env('EINVOICE_MODE', 'test'),
        
        // TEST Environment (Development)
        'test' => [
            'base_url' => env('EINVOICE_TEST_BASE_URL', 'https://test-api.sandbox.co.in'),
            'api_key' => env('EINVOICE_TEST_API_KEY'),
            'api_secret' => env('EINVOICE_TEST_API_SECRET'),
        ],
        
        // LIVE Environment (Production)
        'live' => [
            'base_url' => env('EINVOICE_LIVE_BASE_URL', 'https://api.sandbox.co.in'),
            'api_key' => env('EINVOICE_LIVE_API_KEY'),
            'api_secret' => env('EINVOICE_LIVE_API_SECRET'),
        ],
        
        // Common API settings
        'api_version' => env('EINVOICE_API_VERSION', '1.0'),
        'gst_api_version' => env('EINVOICE_GST_API_VERSION', 'GST_EINV_1.03'),
        'timeout' => (int) env('EINVOICE_TIMEOUT', 30),
        'auto_generate' => filter_var(env('EINVOICE_AUTO_GENERATE', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'partner_credit' => [
        // Only deduct wallet when order status is completed/active.
        'deduct_on_completed_only' => filter_var(env('PARTNER_CREDIT_DEDUCT_ON_COMPLETED_ONLY', true), FILTER_VALIDATE_BOOLEAN),

        // Keep partners.wallet_balance mirrored from partner wallet account ledger.
        'sync_partner_wallet_snapshot' => filter_var(env('PARTNER_CREDIT_SYNC_PARTNER_WALLET_SNAPSHOT', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | Place the service-account JSON under storage/app/firebase/ and set
    | FIREBASE_CREDENTIALS to that absolute/relative path.
    */
    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
        'android_channel_id' => env('FIREBASE_ANDROID_CHANNEL_ID', 'high_importance'),
        'web_click_link' => env('FIREBASE_WEB_CLICK_LINK', env('APP_URL', '')),
        'web_icon' => env('FIREBASE_WEB_ICON', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Razorpay refund policy
    |--------------------------------------------------------------------------
    */
    'razorpay_refund' => [
        'enabled' => filter_var(env('RAZORPAY_REFUND_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'max_days' => (int) env('RAZORPAY_REFUND_MAX_DAYS', 30),
        'allow_partial' => filter_var(env('RAZORPAY_REFUND_ALLOW_PARTIAL', true), FILTER_VALIDATE_BOOLEAN),
        'min_reason_length' => (int) env('RAZORPAY_REFUND_MIN_REASON_LENGTH', 5),
        'speed' => env('RAZORPAY_REFUND_SPEED', 'normal'), // normal|optimum
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Play Store reviewer OTP login (static mobile + OTP)
    |--------------------------------------------------------------------------
    |
    | Used only when reviewers cannot receive SMS. Must be a real partner
    | mobile in pgsql_auth.partners. OTP must be exactly 6 digits.
    */
    'playstore_reviewer' => [
        'enabled' => filter_var(env('PLAYSTORE_REVIEW_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'mobile' => env('PLAYSTORE_REVIEW_MOBILE'),
        'otp' => env('PLAYSTORE_REVIEW_OTP'),
    ],

    'support' => [
        'agent_role_id' => (int) env('SUPPORT_AGENT_ROLE_ID', 3),
        'online_window_minutes' => (int) env('SUPPORT_AGENT_ONLINE_MINUTES', 5),
        'auto_close_days' => (int) env('SUPPORT_AUTO_CLOSE_DAYS', 5),
        'chat_hours' => env('SUPPORT_CHAT_HOURS', 'Monday to Saturday, 10:00 AM - 7:00 PM IST'),
        'chat_max_active' => (int) env('SUPPORT_CHAT_MAX_ACTIVE', 5),
        'reply_to' => env('SUPPORT_REPLY_TO'),
        'inbound_token' => env('SUPPORT_INBOUND_TOKEN'),
    ],

    // IP -> city lookup for partner user login sessions. {ip} is replaced with the client IP.
    'geoip' => [
        'url' => env('GEOIP_LOOKUP_URL', 'https://ipwho.is/{ip}?fields=success,city,region,country'),
        'timeout' => (int) env('GEOIP_LOOKUP_TIMEOUT', 3),
    ],

];