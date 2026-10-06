-- Emails go out in the recipient's language. These record a language for the
-- people who never chose one: the page an account last signed in from, and the
-- page a subscription was made from. See App\Services\RecipientLocale.

ALTER TABLE users
    ADD COLUMN last_locale VARCHAR(5) DEFAULT NULL COMMENT 'Language of the page the person last signed in or registered from; emails use it when no language preference is set' AFTER last_login;

ALTER TABLE blog_subscribers
    ADD COLUMN locale VARCHAR(5) DEFAULT NULL COMMENT 'Language of the page the subscription was made from; post emails use it when there is no account preference' AFTER confirmed_at;
