-- Migration: Change container_type from ENUM to VARCHAR
ALTER TABLE containers
MODIFY COLUMN container_type VARCHAR(50) NOT NULL;