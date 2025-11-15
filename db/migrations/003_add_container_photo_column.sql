-- Migration: Add photo column to containers table
ALTER TABLE containers
ADD COLUMN photo VARCHAR(255) DEFAULT NULL;
