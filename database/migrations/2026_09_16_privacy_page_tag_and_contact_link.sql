-- The privacy policy still listed a username as account information. Accounts now
-- carry a name and a tag instead.
UPDATE pages
SET content = REPLACE(
        content,
        'Your username, email address and password.',
        'Your name, tag, email address and password.'
    )
WHERE slug = 'privacy' AND locale = 'en';

-- The Greek page was written in the admin editor, which stores unaccented Greek as
-- HTML entities, so the replacement has to match that encoding.
UPDATE pages
SET content = REPLACE(
        content,
        '&Tau;&omicron; ό&nu;&omicron;&mu;&alpha; &chi;&rho;ή&sigma;&tau;&eta;,',
        '&Tau;&omicron; ό&nu;&omicron;&mu;&alpha; &kappa;&alpha;&iota; &tau;&eta;&nu; &epsilon;&tau;&iota;&kappa;έ&tau;&alpha; &sigma;&omicron;&upsilon;,'
    )
WHERE slug = 'privacy' AND locale = 'el';

-- The relative link climbed past the root to /contact, which redirects to the
-- default locale, so Greek readers landed on the English contact page.
UPDATE pages
SET content = REPLACE(content, 'href="../../../../contact"', 'href="/el/contact"')
WHERE slug = 'privacy' AND locale = 'el';
