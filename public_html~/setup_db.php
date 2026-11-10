<?php
$pdo = new PDO('mysql:host=localhost', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Create database
$pdo->exec("CREATE DATABASE IF NOT EXISTS `u245440271_Luxuryescaperp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `u245440271_Luxuryescaperp`");

echo "Database created.\n";

$queries = [

// Users
"CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin','manager','accountant','operations','viewer') DEFAULT 'admin',
    `permissions` JSON NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

// Auth Tokens
"CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Clients
"CREATE TABLE IF NOT EXISTS `clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `email` VARCHAR(255) NULL,
    `cnic_passport` VARCHAR(50) NULL,
    `address` TEXT NULL,
    `default_currency` VARCHAR(3) DEFAULT 'SAR',
    `opening_balance` DECIMAL(15,2) DEFAULT 0,
    `opening_balance_date` DATE NULL,
    `linked_vendor_id` INT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

// Vendors
"CREATE TABLE IF NOT EXISTS `vendors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `vendor_type` VARCHAR(50) DEFAULT 'multi',
    `contact_person` VARCHAR(255) NULL,
    `phone` VARCHAR(50) NULL,
    `email` VARCHAR(255) NULL,
    `address` TEXT NULL,
    `vat_number` VARCHAR(50) NULL,
    `bank_name` VARCHAR(255) NULL,
    `bank_account_name` VARCHAR(255) NULL,
    `bank_account_no` VARCHAR(100) NULL,
    `bank_iban` VARCHAR(100) NULL,
    `bank_swift` VARCHAR(50) NULL,
    `default_currency` VARCHAR(3) DEFAULT 'SAR',
    `linked_client_id` INT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

// Hotel Invoices
"CREATE TABLE IF NOT EXISTS `hotel_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NULL,
    `client_id` INT NOT NULL,
    `transaction_type` ENUM('purchase','sale') DEFAULT 'purchase',
    `booking_ref` VARCHAR(20) NOT NULL,
    `booking_status` VARCHAR(20) DEFAULT 'confirm',
    `season` VARCHAR(50) NULL,
    `hotel_name` VARCHAR(255) NOT NULL,
    `hotel_city` VARCHAR(100) NULL,
    `hotel_address` TEXT NULL,
    `hotel_star_rating` VARCHAR(10) NULL,
    `room_type` VARCHAR(100) NOT NULL,
    `room_view` VARCHAR(100) NULL,
    `pax` INT DEFAULT 1,
    `meal_plan` VARCHAR(50) NULL,
    `check_in` DATE NOT NULL,
    `check_out` DATE NOT NULL,
    `nights` INT NOT NULL,
    `price_per_night` DECIMAL(15,2) NOT NULL,
    `sub_total` DECIMAL(15,2) NOT NULL,
    `municipality_fee_pct` DECIMAL(5,2) DEFAULT 0,
    `vat_pct` DECIMAL(5,2) DEFAULT 15,
    `vat_amount` DECIMAL(15,2) DEFAULT 0,
    `total_amount` DECIMAL(15,2) NOT NULL,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `checkin_time` VARCHAR(20) DEFAULT '1600 HRS',
    `checkout_time` VARCHAR(20) DEFAULT '1200 NOON',
    `group_visa_no` VARCHAR(50) NULL,
    `visa_executive` VARCHAR(100) NULL,
    `printed_by` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB",

// Hotel Invoice Passengers
"CREATE TABLE IF NOT EXISTS `hotel_invoice_passengers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hotel_invoice_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `passport_no` VARCHAR(50) NULL,
    `pax_type` ENUM('adult_male','adult_female','child','infant') DEFAULT 'adult_male',
    `room_allocation` VARCHAR(50) NULL,
    FOREIGN KEY (`hotel_invoice_id`) REFERENCES `hotel_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Hotel Invoice Flights
"CREATE TABLE IF NOT EXISTS `hotel_invoice_flights` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hotel_invoice_id` INT NOT NULL,
    `pnr` VARCHAR(20) NULL,
    `flight_no` VARCHAR(20) NULL,
    `from_airport` VARCHAR(50) NULL,
    `to_airport` VARCHAR(50) NULL,
    `flight_date` DATE NULL,
    `departure_time` VARCHAR(20) NULL,
    `arrival_time` VARCHAR(20) NULL,
    `leg_order` INT DEFAULT 0,
    FOREIGN KEY (`hotel_invoice_id`) REFERENCES `hotel_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Hotel Vouchers (Client-facing)
"CREATE TABLE IF NOT EXISTS `hotel_vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_ref` VARCHAR(20) NOT NULL,
    `hotel_invoice_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `guest_name` VARCHAR(255) NULL,
    `hotel_name` VARCHAR(255) NOT NULL,
    `hotel_cnf_no` VARCHAR(50) NULL,
    `city` VARCHAR(100) NULL,
    `hotel_address` TEXT NULL,
    `hotel_star_rating` VARCHAR(10) NULL,
    `checkin_time` VARCHAR(20) DEFAULT '1600 HRS',
    `checkout_time` VARCHAR(20) DEFAULT '1200 NOON',
    `group_visa_no` VARCHAR(50) NULL,
    `transport_ref` VARCHAR(50) NULL,
    `mazarat` TEXT NULL,
    `room_type` VARCHAR(100) NULL,
    `check_in` DATE NOT NULL,
    `check_out` DATE NOT NULL,
    `nights` INT NOT NULL,
    `pax` INT DEFAULT 1,
    `meal_plan` VARCHAR(50) NULL,
    `booking_status` VARCHAR(20) DEFAULT 'confirm',
    `terms_conditions` TEXT NULL,
    `created_by_name` VARCHAR(100) NULL,
    `printed_by_name` VARCHAR(100) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`hotel_invoice_id`) REFERENCES `hotel_invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
) ENGINE=InnoDB",

// Transport Vouchers (Internal)
"CREATE TABLE IF NOT EXISTS `transport_vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_no` VARCHAR(20) NOT NULL,
    `vendor_id` INT NULL,
    `client_id` INT NOT NULL,
    `guest_id` INT NULL,
    `transaction_type` ENUM('purchase','sale') DEFAULT 'purchase',
    `route` VARCHAR(255) NOT NULL,
    `vehicle_type` VARCHAR(100) NULL,
    `pickup_date` DATE NOT NULL,
    `pickup_time` VARCHAR(20) NULL,
    `flight_details` VARCHAR(255) NULL,
    `contact_person` VARCHAR(255) NULL,
    `total_pax` INT DEFAULT 1,
    `amount` DECIMAL(15,2) NOT NULL,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `booking_notes` TEXT NULL,
    `status` VARCHAR(20) DEFAULT 'confirmed',
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB",

// Transport Client Vouchers
"CREATE TABLE IF NOT EXISTS `transport_client_vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_ref` VARCHAR(20) NOT NULL,
    `transport_voucher_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `guest_name` VARCHAR(255) NULL,
    `route` VARCHAR(255) NOT NULL,
    `vehicle_type` VARCHAR(100) NULL,
    `pickup_date` DATE NOT NULL,
    `pickup_time` VARCHAR(20) NULL,
    `flight_details` VARCHAR(255) NULL,
    `contact_person` VARCHAR(255) NULL,
    `total_pax` INT DEFAULT 1,
    `ref_no` VARCHAR(50) NULL,
    `booking_notes` TEXT NULL,
    `status` VARCHAR(20) DEFAULT 'confirmed',
    `created_by_name` VARCHAR(100) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transport_voucher_id`) REFERENCES `transport_vouchers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
) ENGINE=InnoDB",

// Visa Invoices
"CREATE TABLE IF NOT EXISTS `visa_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NULL,
    `client_id` INT NOT NULL,
    `guest_id` INT NULL,
    `voucher_no` VARCHAR(20) NOT NULL,
    `transaction_type` ENUM('purchase','sale') DEFAULT 'purchase',
    `visa_type` VARCHAR(100) NOT NULL,
    `country` VARCHAR(100) NULL,
    `guest_name` VARCHAR(255) NOT NULL,
    `issue_date` DATE NOT NULL,
    `expiry_date` DATE NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `currency_code` VARCHAR(3) DEFAULT 'PKR',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB",

// Visa Client Vouchers
"CREATE TABLE IF NOT EXISTS `visa_client_vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_ref` VARCHAR(20) NOT NULL,
    `visa_invoice_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `guest_name` VARCHAR(255) NULL,
    `visa_type` VARCHAR(100) NULL,
    `country` VARCHAR(100) NULL,
    `issue_date` DATE NULL,
    `expiry_date` DATE NULL,
    `notes` TEXT NULL,
    `created_by_name` VARCHAR(100) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`visa_invoice_id`) REFERENCES `visa_invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
) ENGINE=InnoDB",

// Packages
"CREATE TABLE IF NOT EXISTS `packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `destination` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `visa_type` VARCHAR(100) NULL,
    `visa_country` VARCHAR(100) NULL,
    `visa_price_per_person` DECIMAL(15,2) DEFAULT 0,
    `num_visas` INT DEFAULT 0,
    `visa_vendor_id` INT NULL,
    `num_rooms` INT DEFAULT 0,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB",

// Package Hotels
"CREATE TABLE IF NOT EXISTS `package_hotels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `hotel_name` VARCHAR(255) NOT NULL,
    `hotel_city` VARCHAR(100) NULL,
    `hotel_address` TEXT NULL,
    `hotel_star_rating` VARCHAR(10) NULL,
    `room_type` VARCHAR(100) DEFAULT 'Triple',
    `num_rooms` INT DEFAULT 1,
    `nights` INT DEFAULT 1,
    `meal_plan` VARCHAR(50) DEFAULT 'Room Only',
    `price_per_night` DECIMAL(15,2) DEFAULT 0,
    `checkin_time` VARCHAR(20) DEFAULT '1600 HRS',
    `checkout_time` VARCHAR(20) DEFAULT '1200 NOON',
    `hotel_vendor_id` INT NULL,
    `hotel_contact` VARCHAR(255) NULL,
    `sort_order` INT DEFAULT 0,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Package Transports
"CREATE TABLE IF NOT EXISTS `package_transports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `route` VARCHAR(255) NOT NULL,
    `vehicle` VARCHAR(100) NULL,
    `price_per_person` DECIMAL(15,2) DEFAULT 0,
    `transport_vendor_id` INT NULL,
    `sort_order` INT DEFAULT 0,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Package Bookings
"CREATE TABLE IF NOT EXISTS `package_bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `group_ref` VARCHAR(20) NOT NULL,
    `num_travelers` INT NOT NULL,
    `check_in` DATE NOT NULL,
    `pickup_date` DATE NULL,
    `pickup_time` VARCHAR(20) NULL,
    `hotel_invoice_id` INT NULL,
    `transport_voucher_id` INT NULL,
    `transaction_type` VARCHAR(20) DEFAULT 'purchase',
    `group_visa_no` VARCHAR(50) NULL,
    `visa_executive` VARCHAR(100) NULL,
    `transport_ref_no` VARCHAR(50) NULL,
    `agent_ref_no` VARCHAR(50) NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
) ENGINE=InnoDB",

// Package Booking Travelers
"CREATE TABLE IF NOT EXISTS `package_booking_travelers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_booking_id` INT NOT NULL,
    `guest_name` VARCHAR(255) NOT NULL,
    `visa_invoice_id` INT NULL,
    `passport_no` VARCHAR(50) NULL,
    `pax_type` VARCHAR(20) DEFAULT 'adult_male',
    `room_allocation` VARCHAR(50) NULL,
    FOREIGN KEY (`package_booking_id`) REFERENCES `package_bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Package Booking Flights
"CREATE TABLE IF NOT EXISTS `package_booking_flights` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `package_booking_id` INT NOT NULL,
    `pnr` VARCHAR(20) NULL,
    `flight_no` VARCHAR(20) NULL,
    `from_airport` VARCHAR(50) NULL,
    `to_airport` VARCHAR(50) NULL,
    `flight_date` DATE NULL,
    `departure_time` VARCHAR(20) NULL,
    `arrival_time` VARCHAR(20) NULL,
    `leg_order` INT DEFAULT 0,
    FOREIGN KEY (`package_booking_id`) REFERENCES `package_bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Ledger Entries (Client Shajrah)
"CREATE TABLE IF NOT EXISTS `ledger_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `entry_date` DATE NOT NULL,
    `voucher_type` VARCHAR(5) NOT NULL,
    `voucher_no` VARCHAR(20) NOT NULL,
    `reference` VARCHAR(100) NULL,
    `narration` TEXT NOT NULL,
    `linked_hotel_invoice_id` INT NULL,
    `linked_transport_voucher_id` INT NULL,
    `linked_visa_invoice_id` INT NULL,
    `debit` DECIMAL(15,2) DEFAULT 0,
    `credit` DECIMAL(15,2) DEFAULT 0,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `exchange_rate` DECIMAL(10,4) NULL,
    `running_balance` DECIMAL(15,2) DEFAULT 0,
    `is_edited` TINYINT(1) DEFAULT 0,
    `created_by` INT NULL,
    `updated_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
) ENGINE=InnoDB",

// Vendor Ledger Entries
"CREATE TABLE IF NOT EXISTS `vendor_ledger_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NOT NULL,
    `entry_date` DATE NOT NULL,
    `voucher_type` VARCHAR(5) NOT NULL,
    `voucher_no` VARCHAR(20) NOT NULL,
    `narration` TEXT NOT NULL,
    `linked_hotel_invoice_id` INT NULL,
    `linked_transport_voucher_id` INT NULL,
    `linked_visa_invoice_id` INT NULL,
    `debit` DECIMAL(15,2) DEFAULT 0,
    `credit` DECIMAL(15,2) DEFAULT 0,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `running_balance` DECIMAL(15,2) DEFAULT 0,
    `dispute_status` VARCHAR(50) NULL,
    `dispute_note` TEXT NULL,
    `is_edited` TINYINT(1) DEFAULT 0,
    `created_by` INT NULL,
    `updated_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`)
) ENGINE=InnoDB",

// Payments (Client)
"CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ledger_entry_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `paid_to` VARCHAR(255) NULL,
    `payment_method` VARCHAR(50) DEFAULT 'bank_transfer',
    `amount` DECIMAL(15,2) NOT NULL,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `exchange_rate` DECIMAL(10,4) NULL,
    `converted_amount` DECIMAL(15,2) NULL,
    `proof_path` VARCHAR(500) NOT NULL,
    `payment_date` DATE NOT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ledger_entry_id`) REFERENCES `ledger_entries`(`id`),
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`)
) ENGINE=InnoDB",

// Vendor Payments
"CREATE TABLE IF NOT EXISTS `vendor_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ledger_entry_id` INT NOT NULL,
    `vendor_id` INT NOT NULL,
    `paid_via` VARCHAR(255) NULL,
    `payment_method` VARCHAR(50) DEFAULT 'bank_transfer',
    `amount` DECIMAL(15,2) NOT NULL,
    `currency_code` VARCHAR(3) DEFAULT 'SAR',
    `exchange_rate` DECIMAL(10,4) NULL,
    `converted_amount` DECIMAL(15,2) NULL,
    `proof_path` VARCHAR(500) NOT NULL,
    `payment_date` DATE NOT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ledger_entry_id`) REFERENCES `vendor_ledger_entries`(`id`),
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`)
) ENGINE=InnoDB",

// Voucher Sequences
"CREATE TABLE IF NOT EXISTS `voucher_sequences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_type` VARCHAR(5) NOT NULL,
    `year` INT NOT NULL,
    `last_number` INT DEFAULT 0,
    UNIQUE KEY `type_year` (`voucher_type`, `year`)
) ENGINE=InnoDB",

// Ledger Entry Edits (Audit)
"CREATE TABLE IF NOT EXISTS `ledger_entry_edits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ledger_entry_id` INT NOT NULL,
    `edited_by` INT NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ledger_entry_id`) REFERENCES `ledger_entries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

// Reminder Acknowledgements
"CREATE TABLE IF NOT EXISTS `reminder_acknowledgements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ref_type` VARCHAR(20) NOT NULL,
    `ref_id` INT NOT NULL,
    `acknowledged_by` INT NOT NULL,
    `acknowledged_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `remark` TEXT NULL,
    UNIQUE KEY `unique_ack` (`ref_type`, `ref_id`, `acknowledged_by`),
    FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB",

// Guests
"CREATE TABLE IF NOT EXISTS `guests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `passport_no` VARCHAR(50) NULL,
    `phone` VARCHAR(50) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB",

];

$count = 0;
foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        $count++;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Created $count tables.\n";

// Seed admin user
$hash = password_hash('ChangeThisPassword123!', PASSWORD_BCRYPT);
$pdo->exec("INSERT IGNORE INTO `users` (`name`, `email`, `password_hash`, `role`, `is_active`) VALUES ('Admin', 'admin@yourstechhub.com', '$hash', 'admin', 1)");
echo "Admin user seeded.\n";

// Verify
$stmt = $pdo->query("SELECT id, name, email, role FROM users");
$users = $stmt->fetchAll();
foreach ($users as $u) {
    echo "User: {$u['name']} ({$u['email']}) - {$u['role']}\n";
}

echo "\nAll done! Database ready.\n";
