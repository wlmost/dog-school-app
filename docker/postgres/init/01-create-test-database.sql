-- Create test database for automated testing
CREATE DATABASE dog_school_test;
GRANT ALL PRIVILEGES ON DATABASE dog_school_test TO dog_school_user;

-- Set default encoding and collation
ALTER DATABASE dog_school SET LC_COLLATE = 'en_US.utf8';
ALTER DATABASE dog_school SET LC_CTYPE = 'en_US.utf8';
ALTER DATABASE dog_school_test SET LC_COLLATE = 'en_US.utf8';
ALTER DATABASE dog_school_test SET LC_CTYPE = 'en_US.utf8';
