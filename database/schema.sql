CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NULL,
    username VARCHAR(60) NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    transaction_pin_hash VARCHAR(255) NOT NULL,
    role ENUM('owner','admin','staff','customer') NOT NULL DEFAULT 'customer',
    package_id BIGINT UNSIGNED NULL,
    status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    blocked_until DATETIME NULL,
    blocked_reason VARCHAR(255) NULL,
    balance DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    referral_balance DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    address TEXT NULL,
    kyc_status ENUM('none','pending','verified','rejected') NOT NULL DEFAULT 'none',
    api_key VARCHAR(100) NULL,
    referral_code VARCHAR(40) NULL,
    referred_by BIGINT UNSIGNED NULL,
    last_active_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_username_unique (username),
    UNIQUE KEY users_email_unique (email),
    UNIQUE KEY users_phone_unique (phone),
    KEY users_referral_code_idx (referral_code),
    KEY users_status_balance_idx (status, balance),
    KEY users_last_active_idx (last_active_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    `key` VARCHAR(150) NOT NULL,
    `value` LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_webhook_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(80) NOT NULL,
    reference VARCHAR(191) NULL,
    payload LONGTEXT NOT NULL,
    signature_valid TINYINT(1) NOT NULL DEFAULT 0,
    http_status SMALLINT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'received',
    response_message VARCHAR(500) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY payment_webhook_provider_idx (provider, received_at),
    KEY payment_webhook_reference_idx (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    activation_key_hash VARCHAR(255) NOT NULL,
    installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY installations_owner_unique (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(50) NOT NULL,
    provider VARCHAR(80) NOT NULL,
    service_type VARCHAR(100) NULL,
    plan_id VARCHAR(100) NOT NULL,
    name VARCHAR(191) NOT NULL,
    duration VARCHAR(100) NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    cost_price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    metadata JSON NULL,
    list_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY service_plan_unique (service, provider, plan_id),
    KEY service_status_idx (service, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_plan_package_prices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_plan_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED NOT NULL,
    selling_price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY service_plan_package_unique (service_plan_id, package_id),
    KEY service_plan_package_plan_idx (service_plan_id),
    KEY service_plan_package_package_idx (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(50) NOT NULL,
    provider_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    base_url VARCHAR(255) NULL,
    credentials JSON NULL,
    mappings JSON NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY provider_service_unique (service, provider_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(191) NOT NULL,
    dob DATE NOT NULL,
    id_type ENUM('nin','bvn') NOT NULL,
    id_number VARCHAR(40) NOT NULL,
    address TEXT NULL,
    selfie_path VARCHAR(255) NOT NULL,
    id_document_path VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY kyc_user_status_idx (user_id, status),
    KEY kyc_status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_key VARCHAR(80) NOT NULL,
    label VARCHAR(150) NOT NULL,
    status ENUM('on','off') NOT NULL DEFAULT 'on',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY services_key_unique (service_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_type_controls (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(80) NOT NULL,
    provider VARCHAR(80) NOT NULL DEFAULT '*',
    service_type VARCHAR(100) NOT NULL DEFAULT '*',
    status ENUM('on','off') NOT NULL DEFAULT 'on',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY service_type_unique (service, provider, service_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_type_mappings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(80) NOT NULL,
    raw_keyword VARCHAR(120) NOT NULL,
    mapped_type VARCHAR(120) NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY service_type_mapping_unique (service, raw_keyword),
    KEY service_type_mapping_lookup_idx (service, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE internal_pins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service VARCHAR(80) NOT NULL,
    provider VARCHAR(80) NOT NULL,
    service_type VARCHAR(100) NOT NULL DEFAULT 'local',
    value VARCHAR(100) NOT NULL,
    price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    pin VARCHAR(255) NOT NULL,
    serial VARCHAR(255) NULL,
    status ENUM('unused','used','void') NOT NULL DEFAULT 'unused',
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY internal_pin_unique (pin),
    KEY internal_pin_lookup_idx (service, provider, value, service_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_packages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    upgrade_cost DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commerce_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    rule_type ENUM('coupon','package_discount','cashback','referral') NOT NULL,
    service VARCHAR(80) NULL,
    package_id BIGINT UNSIGNED NULL,
    coupon_code VARCHAR(80) NULL,
    value_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    value DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    max_discount DECIMAL(20,2) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY commerce_rule_lookup_idx (rule_type, service, status, coupon_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE homepage_sections (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(191) NOT NULL,
    subtitle TEXT NULL,
    content JSON NULL,
    button_label VARCHAR(80) NULL,
    button_url VARCHAR(255) NULL,
    image_url VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY homepage_status_order_idx (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    service VARCHAR(50) NOT NULL,
    recipient VARCHAR(120) NOT NULL,
    amount DECIMAL(20,2) NOT NULL,
    status ENUM('pending','success','failed','reversed') NOT NULL DEFAULT 'pending',
    reference VARCHAR(80) NOT NULL,
    response_message TEXT NULL,
    details JSON NULL,
    receipt_options JSON NULL,
    balance_before DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    balance_after DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY transactions_reference_unique (reference),
    KEY transactions_user_idx (user_id, created_at),
    KEY transactions_service_status_idx (service, status),
    CONSTRAINT transactions_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('credit','debit') NOT NULL,
    amount DECIMAL(20,2) NOT NULL,
    balance_before DECIMAL(20,2) NOT NULL,
    balance_after DECIMAL(20,2) NOT NULL,
    reference VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY wallet_user_idx (user_id, created_at),
    KEY wallet_reference_idx (reference),
    CONSTRAINT wallet_ledger_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wallet_funding_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(20,2) NOT NULL,
    charge_amount DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    credited_amount DECIMAL(20,2) NOT NULL DEFAULT 0.00,
    method VARCHAR(80) NOT NULL DEFAULT 'manual_bank',
    payment_reference VARCHAR(120) NULL,
    proof_note TEXT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY funding_user_status_idx (user_id, status),
    KEY funding_status_idx (status, created_at),
    CONSTRAINT funding_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dedicated_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(80) NOT NULL,
    account_name VARCHAR(191) NOT NULL,
    account_number VARCHAR(80) NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    reference VARCHAR(120) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    raw_response JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY dedicated_account_number_unique (account_number),
    KEY dedicated_account_user_idx (user_id, status),
    CONSTRAINT dedicated_accounts_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bulk_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_reference VARCHAR(80) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    service VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('queued','processing','success','failed') NOT NULL DEFAULT 'queued',
    transaction_id BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY bulk_batch_idx (batch_reference, status),
    CONSTRAINT bulk_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT bulk_transaction_fk FOREIGN KEY (transaction_id) REFERENCES transactions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaigns (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    subject VARCHAR(191) NOT NULL,
    body LONGTEXT NOT NULL,
    target_rule ENUM('funded_wallet','no_funds','active','inactive') NOT NULL,
    date_from DATE NULL,
    date_to DATE NULL,
    mail_driver ENUM('php','smtp') NOT NULL DEFAULT 'php',
    status ENUM('draft','active','paused','completed') NOT NULL DEFAULT 'draft',
    total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY campaign_status_idx (status, target_rule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaign_sends (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY campaign_user_unique (campaign_id, user_id),
    CONSTRAINT campaign_sends_campaign_fk FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
    CONSTRAINT campaign_sends_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    subject_type VARCHAR(80) NULL,
    subject_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    details JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY audit_actor_idx (actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(191) NOT NULL,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    resets_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY rate_bucket_unique (bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    device_hash VARCHAR(191) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    trusted TINYINT(1) NOT NULL DEFAULT 0,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_device_unique (user_id, device_hash),
    CONSTRAINT user_devices_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
('mail_driver', 'php'),
('site_title', 'Radiantlinks VTU'),
('site_logo_url', ''),
('public_nav_links', 'Home|/\nLogin|/login\nCreate Account|/register'),
('landing_template', 'template1'),
('session_timeout_minutes', '30'),
('two_factor_admin_enabled', 'off'),
('two_factor_customer_enabled', 'off'),
('security_lockout_enabled', 'on'),
('wrong_password_limit', '3'),
('wrong_password_block_minutes', '15'),
('wrong_pin_limit', '3'),
('wrong_pin_block_minutes', '15'),
('transaction_per_minute_limit', '5'),
('referral_bonus_times', '1'),
('funding_manual_enabled', 'on'),
('funding_gateway_enabled', 'off'),
('funding_dedicated_enabled', 'on'),
('funding_gateway_provider', 'demo'),
('payment_webhook_secret', ''),
('manual_bank_details', 'Bank: Your Bank\nAccount Name: Radiantlinks VTU\nAccount Number: 0000000000'),
('funding_manual_charge_type', 'fixed'),
('funding_manual_charge_value', '0'),
('funding_gateway_charge_type', 'fixed'),
('funding_gateway_charge_value', '0'),
('funding_dedicated_charge_type', 'fixed'),
('funding_dedicated_charge_value', '0'),
('admin_notify_registrations', 'on'),
('admin_notify_transactions', 'on'),
('admin_notify_blocks', 'on'),
('user_notify_registration', 'on'),
('user_notify_transactions', 'on'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('welcome_subject', 'Welcome to Radiantlinks VTU'),
('welcome_template', 'Hello {first_name}, welcome to {site_name}.'),
('electricity_receipt_footer', 'Thank you for your payment.');
