-- ----------------------------------------------------------------------------
-- Front page curation, static pages, and editable site content.
--
-- The front page and the explore page are platform owned surfaces, so nothing
-- user generated appears there unless an administrator picked it:
--   1) posts.featured_on_home  admin pick for the front page showcase
--   2) blogs.is_featured       admin pick for the explore page featured blogs
--   3) pages                   admin managed static pages (about, legal, guides)
--   4) site_content            per locale overrides for editable front page text
-- ----------------------------------------------------------------------------

ALTER TABLE posts
    ADD COLUMN featured_on_home TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin pick shown on the site front page' AFTER is_featured,
    ADD INDEX idx_featured_home (featured_on_home, published_at);

ALTER TABLE blogs
    ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Admin pick for the explore page featured blogs' AFTER status,
    ADD INDEX idx_featured (is_featured, published_at);

CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    locale VARCHAR(5) NOT NULL DEFAULT 'en',
    title VARCHAR(200) NOT NULL,
    content LONGTEXT,
    meta_description VARCHAR(160) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_slug_locale (slug, locale),
    INDEX idx_slug (slug),
    INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Admin managed static pages (about, contact, legal, guides)';

CREATE TABLE IF NOT EXISTS site_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    locale VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'Empty string means the value applies to every locale',
    value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name_locale (name, locale),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Editable site text; overrides the locale file defaults';

-- ----------------------------------------------------------------------------
-- Seed pages (English). Other locales fall back to English until translated
-- from the control panel.
-- ----------------------------------------------------------------------------

INSERT INTO pages (slug, locale, title, meta_description, is_published, content) VALUES
('about', 'en', 'About Lexicon',
 'What Lexicon is, who builds it, and what we believe about writing on the web.',
 1,
'<p>Lexicon started with a simple observation. Publishing on the web had become complicated, full of plugins, dashboards inside dashboards and settings nobody asked for. We wanted a place where you sign up, name your blog and start writing. Nothing more.</p>
<p>Today Lexicon is a home for personal blogs, team publications and everything in between. You can write alone or invite other people to write with you. Every blog gets its own home page, categories and comments, and every writer keeps full ownership of their words.</p>
<h2>What we believe</h2>
<ul>
<li><strong>Your content is yours.</strong> You can export everything you have written at any time. If you decide to leave, you can delete your account and take your words with you.</li>
<li><strong>Reading should be pleasant.</strong> Clean typography, fast pages and no advertising clutter.</li>
<li><strong>Small teams write better together.</strong> Roles and an editorial review workflow are built in, so a second pair of eyes is always easy to add.</li>
</ul>
<p>Lexicon is young and growing. If you have an idea that would make it better, we genuinely want to hear it. Say hello through the <a href="/contact">contact page</a>.</p>'),

('contact', 'en', 'Contact us',
 'Questions, ideas or problems? Send the Lexicon team a message.',
 1,
'<p>Have a question, found a problem, or just want to share an idea? Send us a message and a real person will read it. We usually reply within two working days.</p>'),

('privacy', 'en', 'Privacy Policy',
 'How Lexicon collects, uses and protects your personal data.',
 1,
'<p>This policy explains what information Lexicon collects, why we collect it, and what control you have over it. We keep it short on purpose, because a privacy policy you actually read is worth more than ten pages of legal boilerplate.</p>
<h2>What we collect</h2>
<ul>
<li><strong>Account information.</strong> Your username, email address and password. Passwords are stored hashed and we can never read them.</li>
<li><strong>Content you publish.</strong> Posts, comments, blog descriptions and images you upload. Published content is public by definition.</li>
<li><strong>Technical data.</strong> Session cookies to keep you signed in, your language preference, your cookie consent choice, and IP addresses in our security logs.</li>
</ul>
<h2>How we use it</h2>
<p>We use your data to run the platform: signing you in, showing your posts to readers, sending you notifications you asked for, and keeping the service safe. We do not sell your data and we do not show advertising.</p>
<h2>Your rights</h2>
<ul>
<li>You can export a copy of everything you have written from your dashboard at any time.</li>
<li>You can correct your profile details from your account settings.</li>
<li>You can delete your account, which removes your personal data from the platform.</li>
</ul>
<h2>Retention</h2>
<p>We keep your data for as long as your account exists. Security logs are kept for a limited period and then removed. When you delete your account, your personal data is deleted with it.</p>
<h2>Questions</h2>
<p>If anything here is unclear, ask us through the <a href="/contact">contact page</a> and we will give you a straight answer.</p>'),

('terms', 'en', 'Terms of Service',
 'The rules for using Lexicon, written in plain language.',
 1,
'<p>These terms describe the agreement between you and Lexicon when you use the platform. Plain language, no tricks.</p>
<h2>Your account</h2>
<p>You are responsible for your account and for keeping your password private. You must provide a valid email address so we can reach you about your account when needed.</p>
<h2>Your content</h2>
<p>Everything you publish on Lexicon remains yours. By publishing, you give us permission to store and display your content so the platform can work. That permission ends when you delete the content or your account.</p>
<h2>Acceptable use</h2>
<p>Do not use Lexicon to publish content that is illegal, that harasses people, that infringes someone else''s copyright, or that spreads malware or spam. We may remove content or suspend accounts that break these rules.</p>
<h2>Moderation</h2>
<p>Blog owners moderate the comments on their own blogs. The Lexicon team moderates platform surfaces such as the front page and the explore page, and handles reports about content that breaks the rules above.</p>
<h2>The service</h2>
<p>We work hard to keep Lexicon fast and available, but we provide it as is, without warranties. We may change or discontinue features, and we will tell you about important changes in advance when we reasonably can.</p>
<h2>Changes to these terms</h2>
<p>If we change these terms in a meaningful way, we will announce it before the change takes effect. Continuing to use Lexicon after that means you accept the updated terms.</p>'),

('cookies', 'en', 'Cookie Policy',
 'The cookies Lexicon sets and what each one does.',
 1,
'<p>Lexicon uses a small number of cookies, all of them functional. We do not use advertising or cross site tracking cookies.</p>
<h2>Cookies we set</h2>
<ul>
<li><strong>Session cookie.</strong> Keeps you signed in while you move between pages. Deleted when the session ends.</li>
<li><strong>Language preference.</strong> Remembers which language you chose so we can show it on your next visit.</li>
<li><strong>Consent choice.</strong> Records your answer to the cookie banner so we do not ask you again.</li>
</ul>
<h2>Managing cookies</h2>
<p>You can withdraw your consent at any time from the banner link in the footer, and you can remove cookies through your browser settings. Blocking the session cookie will sign you out.</p>'),

('start-your-first-blog', 'en', 'Start your first blog in 10 minutes',
 'From new account to published post, the short version.',
 1,
'<p>You do not need a plan, a niche or a content calendar to start a blog. You need an account and something to say. Here is the whole process.</p>
<h2>1. Create your account</h2>
<p>Register with your email, confirm it and sign in. That is it, there is no server to configure and nothing to install.</p>
<h2>2. Name your blog</h2>
<p>From your dashboard, create a new blog. Pick a name you like and a short description that tells readers what to expect. Do not overthink it, you can change both later.</p>
<h2>3. Write your first post</h2>
<p>Open the editor and write. Add a featured image if you have one, it makes your post look much better in lists and on your blog''s home page. When you are happy with it, hit publish.</p>
<h2>4. Share it</h2>
<p>Your blog has its own address you can share anywhere. Every post gets a clean link that works in a message, an email or a social profile.</p>
<p>That is genuinely all there is to it. The best time to publish your first post is before you feel ready.</p>'),

('write-posts-people-read', 'en', 'Write posts people actually read',
 'Practical writing habits that keep readers on the page.',
 1,
'<p>Most posts lose their readers in the first ten seconds. These habits fix that, and none of them require talent, just a little care.</p>
<h2>Earn the click with the title</h2>
<p>Say what the reader gets, specifically. Compare "Some thoughts on cooking" with "Five dinners you can cook in the time it takes to order delivery". The second one makes a promise.</p>
<h2>Keep paragraphs short</h2>
<p>Long blocks of text look like work. Three or four sentences per paragraph gives the eye somewhere to rest and makes your post feel faster to read than it is.</p>
<h2>Use one featured image</h2>
<p>Posts with a featured image get noticed in every list they appear in. Choose one image that captures the mood of the post, and let the writing do the rest.</p>
<h2>Organize with categories and tags</h2>
<p>Categories are the big shelves of your blog, tags are the labels. A reader who liked one post about hiking should be one click away from all your other hiking posts.</p>
<h2>End with something to do</h2>
<p>A question, a recommendation, a link to a related post. Readers who reach the end are your most interested readers, give them a next step.</p>'),

('blog-with-your-team', 'en', 'Blog with your team',
 'Invite co-writers, assign roles and review posts before they go live.',
 1,
'<p>Some of the best blogs are written by more than one person. Lexicon has collaboration built in, so a team blog works the same way a personal one does, just with more people.</p>
<h2>Invite people</h2>
<p>From your blog''s team page, send an invitation to anyone by email. They get a link, accept it, and appear in your team list. You can cancel pending invitations at any time.</p>
<h2>Give everyone the right role</h2>
<ul>
<li><strong>Authors</strong> write and publish their own posts.</li>
<li><strong>Contributors</strong> write drafts that someone else publishes.</li>
<li><strong>Editors</strong> manage all the posts on the blog.</li>
<li><strong>Reviewers</strong> read pending posts and approve them or request changes.</li>
</ul>
<h2>Turn on the review workflow</h2>
<p>With the workflow enabled, a post goes through review before it can be published. The writer requests a review, a reviewer approves it or asks for changes, and everyone can see where each post stands. Nothing sloppy goes live by accident.</p>
<h2>Moderate comments together</h2>
<p>Comment moderation is shared with your editors, so questions from readers get answered even when you are away.</p>
<p>Start small. Invite one person, write one post together, and grow from there.</p>');
