-- Blogs no longer keep a timezone: analytics count in UTC days and readers see dates on their own clock
ALTER TABLE blog_settings DROP COLUMN timezone;
