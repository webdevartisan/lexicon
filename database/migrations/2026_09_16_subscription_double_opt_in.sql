-- A guest can type anyone's address into the subscribe form, so nothing is sent
-- until the owner of the inbox follows the link in the confirmation email.
ALTER TABLE blog_subscribers
    ADD COLUMN confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER token;

-- Nothing has launched yet, so every existing row is seed data.
UPDATE blog_subscribers SET confirmed_at = created_at;
