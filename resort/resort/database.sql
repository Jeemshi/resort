-- WildNest Resort Database Setup
-- Run this SQL in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS wildnest_resort CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wildnest_resort;

-- Guests Table
CREATE TABLE IF NOT EXISTS guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    address TEXT,
    id_type VARCHAR(50),
    id_number VARCHAR(100),
    profile_photo VARCHAR(255),
    status ENUM('active','inactive','banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin','manager','staff') DEFAULT 'staff',
    avatar VARCHAR(255),
    status ENUM('active','inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room Categories
CREATE TABLE IF NOT EXISTS room_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    max_occupancy INT DEFAULT 2,
    icon VARCHAR(100)
);

-- Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price_per_night DECIMAL(10,2) NOT NULL,
    max_occupancy INT DEFAULT 2,
    floor INT DEFAULT 1,
    size_sqm DECIMAL(6,2),
    view_type VARCHAR(50),
    status ENUM('available','occupied','maintenance','reserved') DEFAULT 'available',
    featured TINYINT(1) DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES room_categories(id)
);

-- Room Images
CREATE TABLE IF NOT EXISTS room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Room Amenities
CREATE TABLE IF NOT EXISTS amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(100),
    category VARCHAR(50),
    description TEXT
);

-- Room Amenity Mapping
CREATE TABLE IF NOT EXISTS room_amenities (
    room_id INT NOT NULL,
    amenity_id INT NOT NULL,
    PRIMARY KEY (room_id, amenity_id),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
);

-- Services Table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50),
    icon VARCHAR(100),
    image VARCHAR(255),
    available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) NOT NULL UNIQUE,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    nights INT NOT NULL,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    room_rate DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    special_requests TEXT,
    status ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);

-- Booking Services (add-ons)
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

-- Billing / Payments
CREATE TABLE IF NOT EXISTS billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2),
    payment_method ENUM('cash','credit_card','debit_card','gcash','bank_transfer') DEFAULT 'cash',
    payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    payment_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- Contact Messages
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('unread','read','replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general'
);

-- =============================================
-- SEED DATA
-- =============================================

-- Default Admin
INSERT INTO admins (name, email, password, role) VALUES
('Resort Manager', 'admin@wildnest.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');
-- Default admin password: password

-- Room Categories
INSERT INTO room_categories (name, description, base_price, max_occupancy, icon) VALUES
('Jungle Canopy Villa', 'Elevated treehouse-style villas nestled among ancient forest canopies', 8500.00, 2, 'fa-tree'),
('Riverside Cottage', 'Rustic-chic cottages perched along the rushing mountain river', 5500.00, 4, 'fa-water'),
('Summit Suite', 'Premium suites with panoramic mountain and valley views', 12000.00, 2, 'fa-mountain'),
('Explorer Bungalow', 'Cozy bungalows perfect for adventure-seeking solo travelers and pairs', 3200.00, 2, 'fa-compass'),
('Basecamp Family Lodge', 'Spacious lodges designed for families and group adventures', 9800.00, 8, 'fa-campground');

-- Rooms
INSERT INTO rooms (room_number, category_id, name, description, price_per_night, max_occupancy, floor, size_sqm, view_type, status, featured) VALUES
('JCV-01', 1, 'Emerald Canopy', 'Suspended 15 feet above the forest floor, this villa wraps around a living mahogany tree. Wake to birdsong and drift off under a canopy of stars through the skylight ceiling.', 8500.00, 2, 2, 55.00, 'Forest Canopy', 'available', 1),
('JCV-02', 1, 'Mossy Ridge Villa', 'Draped in climbing ferns and jungle vines, this private villa offers the ultimate rainforest immersion experience with a private rain shower deck.', 9200.00, 2, 2, 60.00, 'Forest & River', 'available', 0),
('RC-01', 2, 'Whitewater Cottage', 'The sound of rushing water is your alarm clock here. A wraparound deck extends over the river — dip your feet in from your private porch.', 5500.00, 4, 1, 45.00, 'River & Rapids', 'available', 1),
('RC-02', 2, 'Bamboo Creek Cottage', 'Crafted entirely from local bamboo and reclaimed hardwood, this eco-cottage blends seamlessly into the riverbank.', 5200.00, 4, 1, 42.00, 'River View', 'available', 0),
('SS-01', 1, 'Pinnacle Suite', 'The crown jewel of WildNest. Perched at the highest point of the resort, this suite commands 270° views of the valley, volcano, and sea on clear days.', 15000.00, 2, 3, 90.00, 'Panoramic Summit', 'available', 1),
('SS-02', 1, 'Horizon Suite', 'Floor-to-ceiling glass walls, a plunge pool, and a telescope for stargazing. Sunsets here are legendary.', 12000.00, 2, 3, 75.00, 'Valley & Sunset', 'available', 0),
('EB-01', 2, 'Trail Blazer Bungalow', 'Compact, smart, and perfectly positioned near the trailhead. A proper base camp for serious hikers.', 3200.00, 2, 1, 28.00, 'Garden', 'available', 0),
('EB-02', 2, 'Pathfinder Bungalow', 'A tidy bungalow with hammock porch, gear locker, and drying rack — built for adventurers who travel light.', 3200.00, 2, 1, 28.00, 'Forest Edge', 'available', 0),
('FL-01', 3, 'Summit Base Lodge', 'Our largest accommodation, able to house up to 8 guests. Communal fire pit, full kitchen, and multiple sleeping arrangements for the whole crew.', 9800.00, 8, 1, 130.00, 'Meadow & Mountains', 'available', 1);

-- Amenities
INSERT INTO amenities (name, icon, category, description) VALUES
('Free Wi-Fi', 'fa-wifi', 'Connectivity', 'High-speed fiber internet throughout'),
('Air Conditioning', 'fa-snowflake', 'Comfort', 'Individually controlled climate'),
('Private Deck', 'fa-door-open', 'Space', 'Private outdoor deck or balcony'),
('Forest View', 'fa-tree', 'View', 'Unobstructed forest panorama'),
('River View', 'fa-water', 'View', 'Direct river frontage view'),
('Rain Shower', 'fa-shower', 'Bathroom', 'Outdoor and indoor rain shower'),
('Soaking Tub', 'fa-bath', 'Bathroom', 'Deep soaking tub or clawfoot'),
('Minibar', 'fa-cocktail', 'Dining', 'Stocked with local beverages and snacks'),
('Coffee Station', 'fa-coffee', 'Dining', 'Premium local coffee and tea'),
('Smart TV', 'fa-tv', 'Entertainment', '55" 4K smart television'),
('Fire Pit', 'fa-fire', 'Outdoor', 'Private or shared fire pit'),
('Hammock', 'fa-leaf', 'Outdoor', 'Handwoven jungle hammock'),
('Telescope', 'fa-binoculars', 'Special', 'Stargazing telescope provided'),
('Plunge Pool', 'fa-swimming-pool', 'Special', 'Private cold-water plunge pool'),
('Gear Locker', 'fa-lock', 'Adventure', 'Secure storage for adventure gear');

-- Services
INSERT INTO services (name, description, price, category, icon) VALUES
('Guided Trek Package', 'Full-day guided hike with experienced local guides. Includes trail map, safety briefing, snacks, and post-hike refreshments. Choose from 4 difficulty levels.', 1200.00, 'Adventure', 'fa-hiking'),
('River Kayaking', 'Half-day white-water kayaking experience on the Cagayan River tributaries. All safety equipment and instruction included. Beginners welcome.', 950.00, 'Adventure', 'fa-water'),
('Canopy Zip Line Tour', 'Soar through the jungle canopy on our 800-meter zip line course. 7 platforms, 4 lines, and breathtaking aerial views.', 750.00, 'Adventure', 'fa-bolt'),
('Sunrise Yoga Session', 'Morning yoga on the Summit Deck overlooking the misty valley. Led by certified instructors. All levels welcome.', 400.00, 'Wellness', 'fa-sun'),
('Forest Spa Treatment', 'Deep-tissue massage using locally sourced botanical oils and forest herb hot compress. 90-minute full-body treatment.', 1800.00, 'Wellness', 'fa-leaf'),
('Jungle Chef\'s Table', 'Private 5-course dinner prepared by our executive chef using foraged ingredients. Served at your private deck or at the treehouse dining platform.', 2500.00, 'Dining', 'fa-utensils'),
('Sunrise Breakfast Basket', 'Handwoven basket delivered to your villa at dawn. Includes fresh fruit, local pastries, eggs, and brewed Benguet coffee.', 650.00, 'Dining', 'fa-coffee'),
('Wildlife Night Walk', 'After-dark guided tour to spot endemic wildlife — flying lemurs, civets, owls, and bioluminescent fungi. 2-hour experience.', 850.00, 'Adventure', 'fa-moon'),
('Resort Jeepney Transfer', 'Round-trip transfers in our vintage customized jeepney from the nearest bus terminal to the resort.', 500.00, 'Transport', 'fa-bus'),
('Bonfire & Storytelling Night', 'Join our resort guides around the communal fire pit for local folklore, star stories, and roasted forest fare. Nightly at 8PM.', 350.00, 'Experience', 'fa-fire');

-- Settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('resort_name', 'WildNest Resort', 'general'),
('resort_email', 'hello@wildnest.com', 'general'),
('resort_phone', '+63 912 345 6789', 'general'),
('resort_address', 'Sitio Kagubatan, Barangay Bundok, Baguio City, Benguet 2600', 'general'),
('check_in_time', '14:00', 'booking'),
('check_out_time', '12:00', 'booking'),
('tax_rate', '12', 'billing'),
('currency', 'PHP', 'billing'),
('max_advance_booking_days', '365', 'booking'),
('cancellation_hours', '48', 'booking');
