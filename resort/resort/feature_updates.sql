-- ===================================================
-- FEATURE UPDATES: Post-booking amenities, forgot password,
--                  room amenity editing, manage rooms validation
-- ===================================================

USE wildnest_resort;

-- 1. Password reset tokens for both guests and admins
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    user_type ENUM('guest','admin') NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email_type (email, user_type)
);

-- 2. Post-booking amenity add-ons (linked to confirmed bookings)
CREATE TABLE IF NOT EXISTS booking_addon_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- 3. Track total add-on cost on billing (add column if not exists)
ALTER TABLE billing ADD COLUMN IF NOT EXISTS addon_total DECIMAL(10,2) DEFAULT 0 AFTER notes;
