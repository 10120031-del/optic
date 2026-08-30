-- =============================================================================
-- Demo data seed for the optics storefront (SQLite — matches DB_CONNECTION
-- in .env). Safe to re-run: every insert is guarded so it won't duplicate
-- rows or violate the unique/email/sku constraints if run more than once.
--
-- How to run:
--   sqlite3 database/database.sqlite < database/demo_seed.sql
--
-- (This project's Laravel seeders — database/seeders/DatabaseSeeder.php and
-- CatalogSeeder.php — already create a "test@example.com" customer and an
-- "admin@example.com" owner plus a starter catalog via `php artisan db:seed`.
-- This script is a standalone alternative/addition for environments where
-- you just want to run raw SQL against the sqlite file directly.)
--
-- All demo accounts use the password: password
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Accounts: 1 owner (admin) + 2 customers
-- Password hash below is bcrypt("password") — Laravel's Hash::check() will
-- verify it regardless of cost factor.
-- -----------------------------------------------------------------------------
INSERT OR IGNORE INTO users
    (first_name, last_name, email, email_verified_at, password, phone_number, role, newsletter_opt_in, created_at, updated_at)
VALUES
    ('Shop', 'Owner', 'owner@opticsdemo.com', datetime('now'),
     '$2y$10$KzzI2MDsbFjjUAZnjghv/O2zT4SAq5vRirDQsYDUZkODA9ts3ZD0a',
     '+961 71 000 001', 'admin', 1, datetime('now'), datetime('now')),

    ('Maya', 'Haddad', 'maya.customer@opticsdemo.com', datetime('now'),
     '$2y$10$KzzI2MDsbFjjUAZnjghv/O2zT4SAq5vRirDQsYDUZkODA9ts3ZD0a',
     '+961 71 000 002', 'customer', 1, datetime('now'), datetime('now')),

    ('Karim', 'Fares', 'karim.customer@opticsdemo.com', datetime('now'),
     '$2y$10$KzzI2MDsbFjjUAZnjghv/O2zT4SAq5vRirDQsYDUZkODA9ts3ZD0a',
     '+961 71 000 003', 'customer', 0, datetime('now'), datetime('now'));

-- Profiles for the three accounts above (user_id is unique, so OR IGNORE
-- keeps this idempotent).
INSERT OR IGNORE INTO profiles (user_id, description, address_line, city, postal_code, country, created_at, updated_at)
SELECT id, 'Store owner account.', 'Hamra Street', 'Beirut', '1103', 'Lebanon', datetime('now'), datetime('now')
FROM users WHERE email = 'owner@opticsdemo.com';

INSERT OR IGNORE INTO profiles (user_id, address_line, city, postal_code, country, created_at, updated_at)
SELECT id, 'Sassine Square', 'Beirut', '1200', 'Lebanon', datetime('now'), datetime('now')
FROM users WHERE email = 'maya.customer@opticsdemo.com';

INSERT OR IGNORE INTO profiles (user_id, address_line, city, postal_code, country, created_at, updated_at)
SELECT id, 'Rue Gouraud', 'Jounieh', '0000', 'Lebanon', datetime('now'), datetime('now')
FROM users WHERE email = 'karim.customer@opticsdemo.com';

-- -----------------------------------------------------------------------------
-- Stock: frames (unique on sku, so OR IGNORE keeps this idempotent)
-- -----------------------------------------------------------------------------
INSERT OR IGNORE INTO frames
    (name, brand, sku, lens_width, lens_height, bridge_width, temple_length, frame_width, weight_grams,
     size, description, material, category, type, shape, gender, color, color_hex, price, stock, is_active,
     created_at, updated_at)
VALUES
    ('Riverside Rectangle', 'Optix', 'FR-2001', 53, 39, 19, 145, 140, 21,
     'medium', 'Riverside Rectangle — Black acetate frame.', 'acetate', 'eyeglasses', 'full_rim', 'rectangle', 'unisex', 'Black', '#000000', 92.00, 60, 1,
     datetime('now'), datetime('now')),

    ('Marina Geometric', 'Lumina', 'FR-2002', 51, 44, 18, 142, 136, 19,
     'medium', 'Marina Geometric — Crystal Clear acetate frame.', 'acetate', 'eyeglasses', 'semi_rimless', 'geometric', 'women', 'Crystal Clear', '#DCEFF2', 108.00, 32, 1,
     datetime('now'), datetime('now')),

    ('Summit Hexagon', 'FlexFrame', 'FR-2003', 50, 45, 20, 140, 134, 20,
     'narrow', 'Summit Hexagon — Rose Gold metal frame.', 'metal', 'sunglasses', 'full_rim', 'hexagonal', 'unisex', 'Rose Gold', '#B76E79', 115.00, 18, 1,
     datetime('now'), datetime('now')),

    ('Bolt Wayfarer Sport', 'FlexFrame', 'FR-2004', 55, 47, 17, 132, 137, 25,
     'medium', 'Bolt Wayfarer Sport — Matte Navy plastic frame.', 'plastic', 'sports', 'full_rim', 'wayfarer', 'men', 'Matte Navy', '#1B263B', 79.00, 42, 1,
     datetime('now'), datetime('now'));

-- -----------------------------------------------------------------------------
-- Stock: lenses (no unique key in the schema, so guard on name instead)
-- -----------------------------------------------------------------------------
INSERT INTO lenses (name, material, type, refractive_index, price, description, is_active, created_at, updated_at)
SELECT 'Trivex Sport Single Vision', 'trivex', 'single_vision', 1.53, 55.00,
       'Extra impact-resistant lens for sport and kids frames.', 1, datetime('now'), datetime('now')
WHERE NOT EXISTS (SELECT 1 FROM lenses WHERE name = 'Trivex Sport Single Vision');

INSERT INTO lenses (name, material, type, refractive_index, price, description, is_active, created_at, updated_at)
SELECT 'Ultra-Thin Glass Bifocal', 'glass', 'bifocal', 1.70, 95.00,
       'Premium high-index glass bifocal for strong prescriptions.', 1, datetime('now'), datetime('now')
WHERE NOT EXISTS (SELECT 1 FROM lenses WHERE name = 'Ultra-Thin Glass Bifocal');

-- -----------------------------------------------------------------------------
-- Stock: contact lenses (unique on sku, so OR IGNORE keeps this idempotent)
-- -----------------------------------------------------------------------------
INSERT OR IGNORE INTO contact_lenses
    (name, brand, sku, type, material, color, diameter, base_curve, pack_size, expiry_months, price, description,
     stock, is_active, created_at, updated_at)
VALUES
    ('YearlyClassic', 'ClearView', 'CL-3001', 'yearly', 'hydrogel', NULL, 14.00, 8.60, 1, 12, 45.00,
     'Traditional yearly-replacement lens, reusable with daily cleaning solution.', 60, 1, datetime('now'), datetime('now')),

    ('ColorPop Hazel', 'VisionPlus', 'CL-3002', 'monthly', 'silicone_hydrogel', 'Hazel', 14.20, 8.60, 2, 1, 26.00,
     'Cosmetic monthly lens with a natural hazel tint.', 90, 1, datetime('now'), datetime('now'));
