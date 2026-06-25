-- ===================================================
-- DATABASE UPDATES: New features added
-- Run these if upgrading an existing database
-- ===================================================

USE wildnest_resort;

-- 1. Add cancel_reason and payment_option columns to bookings
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS cancel_reason TEXT NULL AFTER special_requests,
    ADD COLUMN IF NOT EXISTS payment_option ENUM('full','downpayment') DEFAULT 'full' AFTER cancel_reason;

-- 2. Update booking status ENUM to include pending_cancellation
ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','checked_in','checked_out','cancelled','pending_cancellation') DEFAULT 'pending';

-- 3. Update billing payment_status to include refunded
ALTER TABLE billing MODIFY COLUMN payment_status ENUM('unpaid','partial','paid','refunded') DEFAULT 'unpaid';

-- 4. Add new Water Sports & Land Activities services
INSERT INTO services (name, description, price, category, icon, available) VALUES
('Banana Boat Ride', 'Thrilling 20-minute banana boat ride on the resort lake. Hold on tight! Perfect for groups of 4-8 riders. All safety equipment provided.', 450.00, 'Water Sports', 'fa-ship', 1),
('Jet Ski Rental', 'Feel the wind in your hair on our resort lake. 30-minute jet ski rental with full safety briefing and life vest. No experience needed.', 1200.00, 'Water Sports', 'fa-water', 1),
('ATV Adventure Trail', 'Conquer the rugged mountain trails on our all-terrain vehicles. 1-hour guided ATV ride through forest paths and scenic viewpoints.', 1500.00, 'Land Activities', 'fa-truck-monster', 1),
('Paintball Battle', 'Team vs. team paintball in our forest arena. Equipment, paint rounds, and protective gear included. Great for groups!', 800.00, 'Land Activities', 'fa-crosshairs', 1),
('Wall Climbing', 'Test your limits on our 12-meter outdoor climbing wall with 6 different routes from beginner to expert. Safety harness and guide included.', 600.00, 'Land Activities', 'fa-mountain', 1),
('Bamboo Rafting', 'Peaceful rafting along the scenic river on traditional bamboo rafts guided by local boatmen. Approximately 2 hours of floating adventure.', 550.00, 'Water Sports', 'fa-anchor', 1),
('Mountain Biking', 'Explore forest trails on our premium mountain bikes. Choose your own pace — scenic tour or challenging trail ride. Helmets & pads included.', 700.00, 'Land Activities', 'fa-biking', 1),
('Horseback Riding', 'Scenic horseback trail rides through the resort grounds and lower forest trail. 45-minute guided ride for all skill levels.', 900.00, 'Land Activities', 'fa-horse', 1),
('Archery', 'Traditional archery lessons and target practice in our dedicated range. Beginner-friendly with experienced instructors. Per-session (30 min).', 350.00, 'Land Activities', 'fa-bullseye', 1),
('Fishing Trip', 'Freshwater fishing at our stocked resort lake. Equipment, bait, and a local guide provided. Cook your catch at our restaurant!', 650.00, 'Experience', 'fa-fish', 1);

-- 5. Ensure booking_services table exists (should already from original schema)
CREATE TABLE IF NOT EXISTS booking_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id)
);


-- ===================================================
-- UPDATES: Double-booking prevention, pending status,
--          payment docs, billing notes
-- ===================================================

-- Ensure billing has a notes column for payment metadata
ALTER TABLE billing ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER payment_status;

-- Ensure bookings default to 'pending' (not 'confirmed')
ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','checked_in','checked_out','cancelled','pending_cancellation') DEFAULT 'pending';

-- Index for fast overlap check
CREATE INDEX IF NOT EXISTS idx_bookings_room_dates ON bookings (room_id, check_in, check_out, status);
