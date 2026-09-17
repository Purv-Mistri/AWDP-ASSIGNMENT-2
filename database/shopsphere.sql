-- =======================================================
-- EveNeed – Smart E-Commerce & Order Management System
-- Database Schema & Comprehensive Sample Data
-- =======================================================

CREATE DATABASE IF NOT EXISTS `shopsphere` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shopsphere`;

-- Drop existing tables in reverse dependency order
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `coupon_usage`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `cart`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS TABLE
-- Important: Default status is 'Blocked' so newly registered users require admin approval.
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `mobile` VARCHAR(20) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `address` TEXT NULL,
    `profile_image` VARCHAR(255) DEFAULT 'assets/images/user-avatar.png',
    `status` ENUM('Blocked', 'Active') NOT NULL DEFAULT 'Blocked',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. ADMINS TABLE
CREATE TABLE `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CATEGORIES TABLE
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `icon` VARCHAR(50) DEFAULT 'bi-tag-fill',
    `image` VARCHAR(255) DEFAULT 'assets/images/cat_electronics.svg',
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. PRODUCTS TABLE
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `category_id` INT NOT NULL,
    `brand` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `discount` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `image` VARCHAR(255) NOT NULL DEFAULT 'assets/images/default.jpg',
    `stock` INT NOT NULL DEFAULT 0,
    `rating` DECIMAL(2,1) NOT NULL DEFAULT 4.5,
    `status` ENUM('Active', 'Inactive', 'Out of Stock') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. ADDRESSES TABLE
CREATE TABLE `addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `mobile` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(20) NOT NULL,
    `address_type` ENUM('Home', 'Work', 'Other') NOT NULL DEFAULT 'Home',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. WISHLIST TABLE
CREATE TABLE `wishlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_product_wishlist` (`user_id`, `product_id`),
    CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wishlist_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. CART TABLE (Persistent sync with PHP Session)
CREATE TABLE `cart` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_product_cart` (`user_id`, `product_id`),
    CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. COUPONS TABLE
CREATE TABLE `coupons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_order_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `expiry_date` DATE NOT NULL,
    `usage_limit` INT NOT NULL DEFAULT 100,
    `used_count` INT NOT NULL DEFAULT 0,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. ORDERS TABLE
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `coupon_id` INT NULL,
    `coupon_code` VARCHAR(50) NULL,
    `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
    `payment_status` ENUM('Pending', 'Completed', 'Failed') NOT NULL DEFAULT 'Pending',
    `order_status` ENUM('Pending', 'Confirmed', 'Packed', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `shipping_full_name` VARCHAR(100) NOT NULL,
    `shipping_mobile` VARCHAR(20) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `shipping_city` VARCHAR(100) NOT NULL,
    `shipping_state` VARCHAR(100) NOT NULL,
    `shipping_pincode` VARCHAR(20) NOT NULL,
    `billing_full_name` VARCHAR(100) NOT NULL,
    `billing_mobile` VARCHAR(20) NOT NULL,
    `billing_address` TEXT NOT NULL,
    `billing_city` VARCHAR(100) NOT NULL,
    `billing_state` VARCHAR(100) NOT NULL,
    `billing_pincode` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. ORDER_ITEMS TABLE
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `brand` VARCHAR(100) NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `discount` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `final_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. PAYMENTS TABLE
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
    `amount` DECIMAL(10,2) NOT NULL,
    `status` ENUM('Pending', 'Completed', 'Failed') NOT NULL DEFAULT 'Completed',
    `card_last_four` VARCHAR(4) NULL,
    `upi_id` VARCHAR(100) NULL,
    `bank_name` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. COUPON_USAGE TABLE
CREATE TABLE `coupon_usage` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `coupon_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `order_id` INT NOT NULL,
    `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_usage_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_usage_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. REVIEWS TABLE
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL,
    `review_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- SEED DATA
-- =======================================================

-- Admin account: admin / admin123
INSERT INTO `admins` (`id`, `username`, `password`, `email`) VALUES
(1, 'admin', '$2y$10$vN0K6c7WvC.j3vN1cOaFtepE0g0hYI0Wk8x3S6Pj3bZ4K9z8iXG2e', 'admin@eveneed.com');

-- Sample users (john_doe: password123 [Active], priya_sharma: password123 [Active], rohit_kumar: password123 [Blocked])
INSERT INTO `users` (`id`, `name`, `username`, `email`, `mobile`, `password`, `address`, `status`) VALUES
(1, 'John Doe', 'john_doe', 'john@example.com', '9876543210', '$2y$10$tZ8k0rY9.1aC7jR7B3cO3u7Hq9e1N2M3L4K5J6H7G8F9E0D1C2B3A', '124 Park Avenue, Koramangala, Bengaluru, Karnataka - 560034', 'Active'),
(2, 'Priya Sharma', 'priya_sharma', 'priya@example.com', '9876543211', '$2y$10$tZ8k0rY9.1aC7jR7B3cO3u7Hq9e1N2M3L4K5J6H7G8F9E0D1C2B3A', 'Flat 402, Sunshine Heights, Andheri West, Mumbai, Maharashtra - 400053', 'Active'),
(3, 'Rohit Kumar', 'rohit_kumar', 'rohit@example.com', '9876543212', '$2y$10$tZ8k0rY9.1aC7jR7B3cO3u7Hq9e1N2M3L4K5J6H7G8F9E0D1C2B3A', '88 Civil Lines, Jaipur, Rajasthan - 302006', 'Blocked');

-- Seed 10 Categories
INSERT INTO `categories` (`id`, `name`, `description`, `icon`, `image`, `status`) VALUES
(1, 'Electronics', 'Smart accessories, headphones, audio devices & cutting-edge tech gadgets.', 'bi-cpu-fill', 'assets/images/cat_electronics.svg', 'Active'),
(2, 'Mobiles', 'Latest flagship smartphones, 5G devices, and feature phones.', 'bi-phone-fill', 'assets/images/cat_mobiles.svg', 'Active'),
(3, 'Laptops', 'High-performance ultrabooks, gaming rigs, and student workstations.', 'bi-laptop-fill', 'assets/images/cat_laptops.svg', 'Active'),
(4, 'Fashion', 'Trendy apparel, designer wear, footwear, and casual essentials.', 'bi-bag-heart-fill', 'assets/images/cat_fashion.svg', 'Active'),
(5, 'Grocery', 'Fresh daily staples, premium dry fruits, organic food and beverages.', 'bi-basket2-fill', 'assets/images/cat_grocery.svg', 'Active'),
(6, 'Beauty', 'Skin care, hair care, cosmetics, and wellness grooming products.', 'bi-stars', 'assets/images/cat_beauty.svg', 'Active'),
(7, 'Home & Kitchen', 'Modern cookware, kitchen appliances, home decor & organizer essentials.', 'bi-house-heart-fill', 'assets/images/cat_home.svg', 'Active'),
(8, 'Sports', 'Fitness equipment, athletic wear, sports gear, and outdoor essentials.', 'bi-trophy-fill', 'assets/images/cat_sports.svg', 'Active'),
(9, 'Books', 'Bestsellers, self-help guides, academic textbooks, and literature classics.', 'bi-book-fill', 'assets/images/cat_books.svg', 'Active'),
(10, 'Accessories', 'Watches, premium leather wallets, travel bags, and daily utility gear.', 'bi-watch', 'assets/images/cat_accessories.svg', 'Active');

-- Seed 32 Products across all categories with realistic stock and discount variations
INSERT INTO `products` (`id`, `name`, `category_id`, `brand`, `description`, `price`, `discount`, `image`, `stock`, `rating`, `status`) VALUES
-- Mobiles (Category 2)
(1, 'Samsung Galaxy S24 Ultra 5G', 2, 'Samsung', 'Flagship AI smartphone with 200MP camera, Snapdragon 8 Gen 3, titanium frame and built-in S-Pen.', 129999.00, 10.00, 'assets/images/prod_samsung_s24.svg', 15, 4.9, 'Active'),
(2, 'Apple iPhone 15 Pro Max (256GB)', 2, 'Apple', 'Titanium design with A17 Pro chip, Action button, and 5x optical zoom camera system.', 144900.00, 5.00, 'assets/images/prod_iphone_15.svg', 8, 4.8, 'Active'),
(3, 'OnePlus 12 5G (Flowy Emerald)', 2, 'OnePlus', 'Hasselblad 4th Gen camera, Snapdragon 8 Gen 3 processor with 100W SUPERVOOC fast charging.', 64999.00, 8.00, 'assets/images/prod_oneplus_12.svg', 22, 4.7, 'Active'),
(4, 'Redmi Note 13 Pro+ 5G', 2, 'Xiaomi', 'Curved AMOLED 120Hz display with 200MP OIS camera and 120W HyperCharge technology.', 29999.00, 15.00, 'assets/images/prod_redmi_note13.svg', 30, 4.5, 'Active'),

-- Laptops (Category 3)
(5, 'Apple MacBook Air M3 (13-inch)', 3, 'Apple', 'Supercharged by M3 chip, 8-core CPU, 10-core GPU, Liquid Retina display with 18-hour battery life.', 114900.00, 7.00, 'assets/images/prod_macbook_m3.svg', 12, 4.9, 'Active'),
(6, 'HP Pavilion Gaming Laptop 15', 3, 'HP', 'AMD Ryzen 7 7735HS, NVIDIA RTX 4050, 16GB DDR5 RAM, 512GB SSD, 144Hz FHD IPS display.', 74999.00, 12.00, 'assets/images/prod_hp_pavilion.svg', 10, 4.6, 'Active'),
(7, 'Dell Inspiron 14 Core i5', 3, 'Dell', 'Intel 13th Gen Core i5, 16GB RAM, 512GB NVMe SSD, backlit keyboard, Windows 11 & MS Office.', 56990.00, 10.00, 'assets/images/prod_dell_inspiron.svg', 4, 4.4, 'Active'),
(8, 'ASUS ROG Strix G16 Gaming Laptop', 3, 'ASUS', 'Intel Core i9-13980HX, NVIDIA RTX 4070 8GB, 32GB RAM, 1TB SSD, 240Hz Nebula Display.', 189990.00, 15.00, 'assets/images/prod_asus_rog.svg', 3, 4.8, 'Active'),

-- Electronics (Category 1)
(9, 'Sony WH-1000XM5 Wireless Headphones', 1, 'Sony', 'Industry-leading Active Noise Cancelling headphones with Auto NC Optimizer, 30hr battery & crystal clear calls.', 29990.00, 17.00, 'assets/images/prod_sony_xm5.svg', 25, 4.8, 'Active'),
(10, 'Boat Airdopes 141 ANC Earbuds', 1, 'Boat', 'True wireless earbuds with 32dB ANC, 42 hours total playtime, BEAST mode for gaming, and ENx tech.', 1999.00, 35.00, 'assets/images/prod_boat_airdopes.svg', 45, 4.3, 'Active'),
(11, 'JBL Charge 5 Portable Bluetooth Speaker', 1, 'JBL', 'Bold JBL Original Pro Sound with long excursion driver, IP67 waterproof and built-in powerbank.', 14999.00, 20.00, 'assets/images/prod_jbl_speaker.svg', 18, 4.7, 'Active'),
(12, 'Logitech MX Master 3S Wireless Mouse', 1, 'Logitech', 'Quiet clicks performance wireless mouse with 8K DPI tracking on any surface and MagSpeed scrolling.', 9495.00, 10.00, 'assets/images/prod_logitech_mouse.svg', 0, 4.9, 'Out of Stock'),

-- Fashion (Category 4)
(13, 'Levi\'s Men\'s Slim Fit Cotton Jeans', 4, 'Levi\'s', 'Classic 511 slim fit premium stretchable denim jeans for men with iconic 5-pocket styling.', 3999.00, 25.00, 'assets/images/prod_levis_jeans.svg', 35, 4.5, 'Active'),
(14, 'Zara Oversized Cotton Blend Hoodie', 4, 'Zara', 'Comfortable relaxed fit hoodie crafted in brushed fleece with kangaroo pocket and rib trim.', 2990.00, 10.00, 'assets/images/prod_zara_hoodie.svg', 20, 4.4, 'Active'),
(15, 'Nike Air Max SC Lifestyle Sneakers', 4, 'Nike', 'Heritage track-inspired casual sneakers with visible Max Air cushioning and durable leather overlays.', 6495.00, 18.00, 'assets/images/prod_nike_sneakers.svg', 14, 4.7, 'Active'),
(16, 'Puma Men\'s Solid Slim Fit Polo T-Shirt', 4, 'Puma', 'Breathable pique cotton polo shirt with signature Puma cat logo and ribbed collar.', 1799.00, 30.00, 'assets/images/prod_puma_polo.svg', 28, 4.3, 'Active'),

-- Grocery (Category 5)
(17, 'Fortune Sunlite Refined Sunflower Oil (5L)', 5, 'Fortune', 'Enriched with Vitamin A & D, heart-healthy refined cooking oil ideal for Indian dishes.', 850.00, 12.00, 'assets/images/prod_fortune_oil.svg', 40, 4.6, 'Active'),
(18, 'India Gate Super Basmati Rice (5kg)', 5, 'India Gate', 'Aged extra long grain basmati rice with irresistible aroma and fluffy texture for biryani.', 749.00, 15.00, 'assets/images/prod_basmati_rice.svg', 50, 4.8, 'Active'),
(19, 'Nutraj California Almonds (1kg Pack)', 5, 'Nutraj', 'Premium 100% natural Californian almonds packed with protein, magnesium and antioxidants.', 999.00, 20.00, 'assets/images/prod_almonds.svg', 30, 4.7, 'Active'),
(20, 'Tata Tea Gold Premium Blend (1kg)', 5, 'Tata Tea', 'Exquisite blend of fine Assam tea leaves with gently rolled 15% long leaves for rich aroma.', 580.00, 10.00, 'assets/images/prod_tata_tea.svg', 60, 4.6, 'Active'),

-- Beauty (Category 6)
(21, 'Minimalist 10% Niacinamide Face Serum (30ml)', 6, 'Minimalist', 'Niacinamide and Zinc daily facial serum for blemish reduction, acne scar control, and oil regulation.', 599.00, 10.00, 'assets/images/prod_face_serum.svg', 25, 4.7, 'Active'),
(22, 'Cetaphil Gentle Skin Cleanser (250ml)', 6, 'Cetaphil', 'Hydrating non-foaming daily facial cleanser formulated with Niacinamide and Vitamin B5 for sensitive skin.', 615.00, 8.00, 'assets/images/prod_cetaphil.svg', 20, 4.8, 'Active'),
(23, 'L\'Oreal Paris Total Repair 5 Hair Mask (200g)', 6, 'L\'Oreal', 'Intensive repairing ceramic hair treatment to fight 5 signs of damaged hair.', 449.00, 15.00, 'assets/images/prod_hair_mask.svg', 18, 4.5, 'Active'),

-- Home & Kitchen (Category 7)
(24, 'Prestige Iris 750W Mixer Grinder', 7, 'Prestige', 'Heavy-duty 750 Watt motor with 3 stainless steel jars and 1 transparent juicer jar.', 4295.00, 28.00, 'assets/images/prod_mixer_grinder.svg', 12, 4.4, 'Active'),
(25, 'Milton Thermosteel Flip Lid Bottle (1000ml)', 7, 'Milton', 'Vacuum insulated double-wall 24hr hot & cold stainless steel hydration flask.', 1120.00, 15.00, 'assets/images/prod_water_bottle.svg', 35, 4.7, 'Active'),
(26, 'Philips Air Fryer HD9200 (4.1 Liter)', 7, 'Philips', 'Rapid Air technology for up to 90% less fat frying, grilling, roasting, and baking with timer.', 7995.00, 22.00, 'assets/images/prod_air_fryer.svg', 2, 4.6, 'Active'),

-- Sports (Category 8)
(27, 'Yonex Nanoray 18i Graphite Badminton Racket', 8, 'Yonex', 'Ultra-lightweight 77g badminton racket with isometric head and high tension string capability.', 2490.00, 20.00, 'assets/images/prod_yonex_racket.svg', 16, 4.6, 'Active'),
(28, 'Nivia Storm Football (Size 5)', 8, 'Nivia', 'Rubber moulded durable 32-panel match football suitable for hard and grassy grounds.', 850.00, 25.00, 'assets/images/prod_football.svg', 25, 4.5, 'Active'),
(29, 'Boldfit PVC Hex Dumbbells Set (5kg x 2)', 8, 'Boldfit', 'Anti-roll hexagon rubber coated home workout hand weights for strength conditioning.', 1999.00, 30.00, 'assets/images/prod_dumbbells.svg', 8, 4.7, 'Active'),

-- Books (Category 9)
(30, 'Atomic Habits by James Clear', 9, 'Penguin', 'Proven framework for improving every day with remarkable insights on habit formation and self-discipline.', 799.00, 35.00, 'assets/images/prod_atomic_habits.svg', 40, 4.9, 'Active'),
(31, 'The Psychology of Money by Morgan Housel', 9, 'Jaico', 'Timeless lessons on wealth, greed, and happiness analyzing how people think about money.', 499.00, 30.00, 'assets/images/prod_psychology_money.svg', 35, 4.8, 'Active'),

-- Accessories (Category 10)
(32, 'Fossil Grant Chronograph Leather Watch', 10, 'Fossil', 'Classic Roman numeral stainless steel chronograph watch with genuine dark brown leather strap.', 12495.00, 25.00, 'assets/images/prod_fossil_watch.svg', 6, 4.7, 'Active');

-- Seed User Addresses
INSERT INTO `addresses` (`id`, `user_id`, `full_name`, `mobile`, `address`, `city`, `state`, `pincode`, `address_type`, `is_default`) VALUES
(1, 1, 'John Doe', '9876543210', 'Flat 302, Green Glen Layout, Outer Ring Road, Bellandur', 'Bengaluru', 'Karnataka', '560103', 'Home', 1),
(2, 1, 'John Doe (Office)', '9876543210', 'Tech Park Block B, Embassy GolfLinks, Domlur', 'Bengaluru', 'Karnataka', '560071', 'Work', 0),
(3, 2, 'Priya Sharma', '9876543211', 'Flat 402, Sunshine Heights, Lokhandwala Complex, Andheri West', 'Mumbai', 'Maharashtra', '400053', 'Home', 1);

-- Seed Coupons
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount`, `expiry_date`, `usage_limit`, `used_count`, `status`) VALUES
(1, 'WELCOME50', 'percentage', 50.00, 399.00, 200.00, '2027-12-31', 500, 12, 'Active'),
(2, 'SAVE10', 'percentage', 10.00, 499.00, 500.00, '2027-12-31', 1000, 45, 'Active'),
(3, 'SPHERE20', 'percentage', 20.00, 999.00, 600.00, '2027-12-31', 200, 18, 'Active'),
(4, 'FESTIVE100', 'fixed', 100.00, 899.00, 100.00, '2027-12-31', 300, 25, 'Active'),
(5, 'EXPIRED20', 'percentage', 20.00, 500.00, 300.00, '2024-01-01', 50, 50, 'Inactive');

-- Seed Sample Completed Order #1 for John Doe (for review validation and immediate reports)
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `subtotal`, `discount_amount`, `coupon_id`, `coupon_code`, `delivery_charge`, `tax`, `total_amount`, `payment_method`, `payment_status`, `order_status`, `shipping_full_name`, `shipping_mobile`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_pincode`, `billing_full_name`, `billing_mobile`, `billing_address`, `billing_city`, `billing_state`, `billing_pincode`, `created_at`) VALUES
(1, 'ORD-20260810-1001', 1, 24891.70, 200.00, 1, 'WELCOME50', 0.00, 1234.58, 25926.28, 'Credit / Debit Card', 'Completed', 'Delivered', 'John Doe', '9876543210', 'Flat 302, Green Glen Layout, Outer Ring Road, Bellandur', 'Bengaluru', 'Karnataka', '560103', 'John Doe', '9876543210', 'Flat 302, Green Glen Layout, Outer Ring Road, Bellandur', 'Bengaluru', 'Karnataka', '560103', '2026-08-10 11:30:00'),
(2, 'ORD-20260815-1002', 1, 1299.35, 0.00, NULL, NULL, 0.00, 64.97, 1364.32, 'UPI', 'Completed', 'Shipped', 'John Doe', '9876543210', 'Flat 302, Green Glen Layout, Outer Ring Road, Bellandur', 'Bengaluru', 'Karnataka', '560103', 'John Doe', '9876543210', 'Flat 302, Green Glen Layout, Outer Ring Road, Bellandur', 'Bengaluru', 'Karnataka', '560103', '2026-08-15 16:45:00'),
(3, 'ORD-20260818-1003', 2, 539.10, 0.00, NULL, NULL, 50.00, 26.95, 616.05, 'Cash on Delivery', 'Pending', 'Confirmed', 'Priya Sharma', '9876543211', 'Flat 402, Sunshine Heights, Lokhandwala Complex, Andheri West', 'Mumbai', 'Maharashtra', '400053', 'Priya Sharma', '9876543211', 'Flat 402, Sunshine Heights, Lokhandwala Complex, Andheri West', 'Mumbai', 'Maharashtra', '400053', '2026-08-18 19:20:00');

-- Seed Order Items for Order #1
INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `brand`, `price`, `discount`, `final_price`, `quantity`, `subtotal`) VALUES
(1, 9, 'Sony WH-1000XM5 Wireless Headphones', 'Sony', 29990.00, 17.00, 24891.70, 1, 24891.70),
(2, 10, 'Boat Airdopes 141 ANC Earbuds', 'Boat', 1999.00, 35.00, 1299.35, 1, 1299.35),
(3, 21, 'Minimalist 10% Niacinamide Face Serum (30ml)', 'Minimalist', 599.00, 10.00, 539.10, 1, 539.10);

-- Seed Payments
INSERT INTO `payments` (`order_id`, `user_id`, `payment_method`, `transaction_id`, `amount`, `status`, `card_last_four`, `upi_id`) VALUES
(1, 1, 'Credit / Debit Card', 'TXN-CARD-8839210', 25926.28, 'Completed', '4242', NULL),
(2, 1, 'UPI', 'TXN-UPI-9920192', 1364.32, 'Completed', NULL, 'john@okaxis'),
(3, 2, 'Cash on Delivery', 'TXN-COD-1192834', 616.05, 'Pending', NULL, NULL);

-- Seed Coupon Usage
INSERT INTO `coupon_usage` (`coupon_id`, `user_id`, `order_id`, `used_at`) VALUES
(1, 1, 1, '2026-08-10 11:30:00');

-- Seed Verified Reviews
INSERT INTO `reviews` (`product_id`, `user_id`, `rating`, `review_text`, `created_at`) VALUES
(9, 1, 5, 'Exceptional noise cancellation and battery life! The audio clarity is pristine and well-balanced. Worth every penny.', '2026-08-12 14:15:00'),
(10, 1, 4, 'Great sound for daily commuting and good bass. Active noise cancellation works nicely in office environments.', '2026-08-16 09:30:00');