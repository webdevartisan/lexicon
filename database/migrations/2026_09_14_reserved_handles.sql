-- Handles are the only thing this table guards, so it is named for them, and each
-- word now carries how strictly it is reserved.
RENAME TABLE reserved_slugs TO reserved_handles;

ALTER TABLE reserved_handles
    CHANGE COLUMN slug handle VARCHAR(100) NOT NULL,
    ADD COLUMN match_type ENUM('exact', 'contains') NOT NULL DEFAULT 'exact' AFTER handle,
    COMMENT = 'Words that cannot be claimed as a user handle';

-- contains also rejects the word inside a longer handle, so it is kept to words
-- that lend authority. On a site word like "me" it would reject "james".
UPDATE reserved_handles SET match_type = 'contains', reason = 'Reads as site staff' WHERE handle = 'admin';

-- staff and security stay exact: real handles such as staffordshire and
-- cybersecurity-notes contain them.
INSERT INTO reserved_handles (handle, match_type, reason) VALUES
    ('moderator', 'contains', 'Reads as site staff'),
    ('support',   'contains', 'Reads as site staff'),
    ('official',  'contains', 'Reads as site staff'),
    ('lexicon',   'contains', 'Reads as the site itself'),
    ('staff',     'exact',    'Reads as site staff'),
    ('security',  'exact',    'Reads as site staff');
