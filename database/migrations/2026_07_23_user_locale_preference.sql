-- ============================================================
-- User Locale Preference — 2026-07-23
-- The dashboard used to carry its own language switcher, ten
-- hardcoded options wide, that wrote localStorage and flipped
-- lang and dir client side. The dashboard is authenticated only,
-- so an account preference covers it completely and the switcher
-- goes away entirely.
--
-- Sits next to timezone because it is the same category of "how I
-- want things rendered". NULL means follow the page's own
-- language, which is what every existing row gets and what the
-- guest experience already does.
-- ============================================================

START TRANSACTION;

ALTER TABLE user_preferences
    ADD COLUMN locale VARCHAR(5) DEFAULT NULL
        COMMENT 'Preferred interface language (ISO 639-1); NULL follows page content'
        AFTER timezone;

INSERT INTO migrations (filename) VALUES ('2026_07_23_user_locale_preference.sql');

COMMIT;
