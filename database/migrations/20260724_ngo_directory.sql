-- Migration: NGO Directory Enhancement
-- Date: 2026-07-24
-- Adds category, description, city, district, state, logo, is_active columns to ngos table
-- Seeds 27 NGOs from Dakshina Kannada and Udupi districts

ALTER TABLE ngos
  ADD COLUMN category VARCHAR(100) NULL AFTER name,
  ADD COLUMN description TEXT NULL AFTER category,
  ADD COLUMN city VARCHAR(100) NULL AFTER description,
  ADD COLUMN district VARCHAR(100) NULL AFTER city,
  ADD COLUMN state VARCHAR(100) NULL DEFAULT 'Karnataka' AFTER district,
  ADD COLUMN logo VARCHAR(255) NULL AFTER state,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER logo,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add indexes for filtering
ALTER TABLE ngos ADD INDEX ngos_district_index (district);
ALTER TABLE ngos ADD INDEX ngos_category_index (category);
ALTER TABLE ngos ADD INDEX ngos_active_index (is_active);

-- Clear existing seed data (if any)
DELETE FROM ngos WHERE id > 0;

-- Seed: Dakshina Kannada NGOs
INSERT INTO ngos (name, category, description, city, district, state, is_active) VALUES
('Canara Organisation for Development and Peace (CODP)', 'Community Development', 'Working towards community development and peace in the coastal Karnataka region.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Humanity In Focus (HIF India)', 'Education & Healthcare', 'Focused on improving education and healthcare access for underprivileged communities.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Navodaya Grama Vikasa Charitable Trust', 'Rural Development', 'Empowering rural communities through sustainable development programs.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Moolathva Foundation Charitable Trust', 'Social Welfare', 'Dedicated to social welfare and upliftment of marginalized sections.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Moras Charitable Foundation', 'Education & Healthcare', 'Supporting education and healthcare initiatives across Dakshina Kannada.', 'Dakshina Kannada', 'Dakshina Kannada', 'Karnataka', 1),
('Academy of Nature Rehabilitation', 'Rehabilitation', 'Rehabilitation services focused on nature-based healing and recovery.', 'Dakshina Kannada', 'Dakshina Kannada', 'Karnataka', 1),
('Aniketana Educational Trust', 'Education', 'Providing quality education opportunities to children in need.', 'Puttur', 'Dakshina Kannada', 'Karnataka', 1),
('Ashika', 'Women & Child Welfare', 'Working for the welfare of women and children in the region.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Gajani Foundation', 'Social Welfare', 'Social welfare initiatives for community empowerment.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Galaxy India Foundation', 'Community Development', 'Community development and social upliftment programs.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Inchara Foundation', 'Education', 'Supporting educational programs for underprivileged students.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Karmapath', 'Community Development', 'Building stronger communities through collective action.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Riya Foundation', 'Healthcare', 'Providing healthcare support and medical assistance.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Shree Chakrapani Fine Arts & Education Trust', 'Education & Arts', 'Promoting fine arts and education in the community.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Shubhodaya Education Trust', 'Education', 'Dedicated to making education accessible to all.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('The Torch Trust', 'Social Welfare', 'Lighting the path for social welfare and community development.', 'Mangaluru', 'Dakshina Kannada', 'Karnataka', 1),
('Shri Kshethra Dharmasthala Rural Development Project (SKDRDP)', 'Rural Development', 'Comprehensive rural development serving lakhs of families across Karnataka.', 'Dharmasthala', 'Dakshina Kannada', 'Karnataka', 1);

-- Seed: Udupi NGOs
INSERT INTO ngos (name, category, description, city, district, state, is_active) VALUES
('Apeksha Foundation', 'Child Welfare', 'Caring for underprivileged children and ensuring their well-being.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Adarsha Charitable Trust', 'Social Welfare', 'Social welfare and community support programs in Udupi.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Manipal Foundation', 'Education & Healthcare', 'Advancing education and healthcare in the Manipal region.', 'Manipal', 'Udupi', 'Karnataka', 1),
('Rotary Club Charitable Trust', 'Community Service', 'Community service and humanitarian projects.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Lions Club Charitable Trust', 'Community Service', 'Serving communities through charitable initiatives.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Seva Bharathi Udupi', 'Social Welfare', 'Service-oriented social welfare programs.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Sanjeevini Charitable Trust', 'Healthcare', 'Healthcare and medical support for the needy.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Sri Krishna Seva Foundation', 'Education', 'Educational support and scholarships for students.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Snehalaya Charitable Trust', 'Child Welfare', 'Providing shelter and care for orphaned children.', 'Udupi', 'Udupi', 'Karnataka', 1),
('Samagra Grameena Ashrama', 'Rural Development', 'Holistic rural development and community upliftment.', 'Udupi', 'Udupi', 'Karnataka', 1);
