-- Migration: Add is_visible column to containers table for product launch/visibility toggle
ALTER TABLE containers ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER photo;