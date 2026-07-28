<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\SiteContentModel;
use App\Services\LocaleRegistry;
use App\Services\PublicCacheInvalidator;
use App\Services\TranslationService;
use Framework\Core\Response;

/**
 * Edit the public site text (front page, sidebar, footer) per locale.
 *
 * The locale files stay the defaults; this screen only stores overrides in
 * site_content. Clearing a field falls back to the locale file, so admins
 * can always get the original text back by emptying the input.
 *
 * Adding a new language means: (1) add the code to `supported` in
 * config/localization.php, (2) drop a locales/{code}.json file. This screen
 * then discovers it automatically.
 */
final class FrontPageController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageSettings';

    /**
     * Localized tabs: each entry is one tab on the admin page.
     *
     * The fields inside each group are laid out in the order given here,
     * and grouped into subsections for the two-column grids in the view.
     * Field keys mirror translation keys; form field names swap dots for
     * double underscores because PHP mangles dots in POST names.
     *
     * @var array<string, array{icon: string, label: string, groups: array<string, array<string, array{0: string, 1: string}>>}>
     */
    private const TABS = [
        'hero' => [
            'icon' => 'megaphone',
            'label' => 'Hero',
            'groups' => [
                'Headline & call to action' => [
                    'banner.eyebrow' => ['Eyebrow line', 'text'],
                    'banner.cta' => ['Button (guests)', 'text'],
                    'banner.title' => ['Sense 1 (the headline)', 'textarea'],
                    'banner.ctaDashboard' => ['Button (signed in)', 'text'],
                    'banner.subtitle' => ['Sense 2', 'textarea'],
                    'banner.body' => ['Usage note', 'textarea'],
                ],
                // The hero is typeset as a dictionary entry. Separate the parts
                // with an interpunct (·) to mark syllable breaks.
                'Entry heading' => [
                    'banner.headword' => ['Headword, e.g. lex·i·con', 'text'],
                    'banner.pronunciation' => ['Pronunciation', 'text'],
                    'banner.pos' => ['Part of speech, e.g. n.', 'text'],
                    'banner.noteLabel' => ['Usage note label', 'text'],
                ],
            ],
        ],
        'sections' => [
            'icon' => 'layout',
            'label' => 'Sections',
            'groups' => [
                'How it works' => [
                    'how.title' => ['Section title', 'text'],
                    'how.entryWord' => ['Headword, e.g. pub·lish', 'text'],
                    'how.entryPos' => ['Part of speech, e.g. v.', 'text'],
                    'how.step1.title' => ['Step 1 title', 'text'],
                    'how.step1.body' => ['Step 1 text', 'textarea'],
                    'how.step2.title' => ['Step 2 title', 'text'],
                    'how.step2.body' => ['Step 2 text', 'textarea'],
                    'how.step3.title' => ['Step 3 title', 'text'],
                    'how.step3.body' => ['Step 3 text', 'textarea'],
                ],
                // Each feature is rendered as a dictionary entry: a headword and
                // a part of speech on the left, the definition on the right.
                'Related entries' => [
                    'features.title' => ['Section title', 'text'],
                    'features.items.writing.word' => ['Entry 1 headword', 'text'],
                    'features.items.writing.pos' => ['Entry 1 part of speech', 'text'],
                    'features.items.writing.title' => ['Entry 1 definition', 'text'],
                    'features.items.writing.body' => ['Entry 1 text', 'textarea'],
                    'features.items.team.word' => ['Entry 2 headword', 'text'],
                    'features.items.team.pos' => ['Entry 2 part of speech', 'text'],
                    'features.items.team.title' => ['Entry 2 definition', 'text'],
                    'features.items.team.body' => ['Entry 2 text', 'textarea'],
                    'features.items.ownership.word' => ['Entry 3 headword', 'text'],
                    'features.items.ownership.pos' => ['Entry 3 part of speech', 'text'],
                    'features.items.ownership.title' => ['Entry 3 definition', 'text'],
                    'features.items.ownership.body' => ['Entry 3 text', 'textarea'],
                    'features.items.readers.word' => ['Entry 4 headword', 'text'],
                    'features.items.readers.pos' => ['Entry 4 part of speech', 'text'],
                    'features.items.readers.title' => ['Entry 4 definition', 'text'],
                    'features.items.readers.body' => ['Entry 4 text', 'textarea'],
                ],
                'Featured writing' => [
                    'showcase.title' => ['Section title', 'text'],
                    'showcase.subtitle' => ['Section subtitle', 'text'],
                    'showcase.emptyTitle' => ['Fallback title (no picks)', 'text'],
                ],
                'Closing call to action' => [
                    'cta.title' => ['Title', 'text'],
                    'cta.body' => ['Text', 'textarea'],
                ],
            ],
        ],
        'faq' => [
            'icon' => 'help-circle',
            'label' => 'FAQ',
            'groups' => [
                'Frequently asked questions' => [
                    'faq.title' => ['Section title', 'text'],
                    'faq.items.q1.q' => ['Question 1', 'text'],
                    'faq.items.q1.a' => ['Answer 1', 'textarea'],
                    'faq.items.q2.q' => ['Question 2', 'text'],
                    'faq.items.q2.a' => ['Answer 2', 'textarea'],
                    'faq.items.q3.q' => ['Question 3', 'text'],
                    'faq.items.q3.a' => ['Answer 3', 'textarea'],
                    'faq.items.q4.q' => ['Question 4', 'text'],
                    'faq.items.q4.a' => ['Answer 4', 'textarea'],
                    'faq.items.q5.q' => ['Question 5', 'text'],
                    'faq.items.q5.a' => ['Answer 5', 'textarea'],
                    'faq.items.q6.q' => ['Question 6', 'text'],
                    'faq.items.q6.a' => ['Answer 6', 'textarea'],
                ],
            ],
        ],
        'sidebar' => [
            'icon' => 'panel-right',
            'label' => 'Guides & Footer',
            'groups' => [
                // Since the front sidebar was removed these feed the footer's
                // guides column and the front page's fallback cards.
                'Guides' => [
                    'sidebar.gettingStarted.title' => ['Section title', 'text'],
                    'sidebar.gettingStarted.actionMore' => ['Button label', 'text'],
                    'sidebar.gettingStarted.items.tip1' => ['Card 1 text', 'textarea'],
                    'sidebar.gettingStarted.items.tip2' => ['Card 2 text', 'textarea'],
                    'sidebar.gettingStarted.items.tip3' => ['Card 3 text', 'textarea'],
                ],
                'Get in touch' => [
                    'contact.title' => ['Section title', 'text'],
                    'contact.body' => ['Section text', 'textarea'],
                ],
                'Footer' => [
                    'footer.aboutText' => ['About text', 'textarea'],
                ],
            ],
        ],
    ];

    /**
     * Global tab: locale-independent values (contact details, social URLs).
     *
     * Empty values hide the corresponding element on the site, so a fresh
     * install shows no fake phone numbers or dead social icons.
     *
     * @var array{icon: string, label: string, groups: array<string, array<string, array{0: string, 1: string}>>}
     */
    private const GLOBAL_TAB = [
        'icon' => 'globe',
        'label' => 'Contact & Social',
        'groups' => [
            'Contact details' => [
                'contact.email' => ['Email address', 'text'],
                'contact.phone' => ['Phone', 'text'],
                'contact.address' => ['Address', 'textarea'],
            ],
            'Social links' => [
                'social.twitter' => ['X / Twitter URL', 'text'],
                'social.facebook' => ['Facebook URL', 'text'],
                'social.instagram' => ['Instagram URL', 'text'],
                'social.medium' => ['Medium URL', 'text'],
            ],
        ],
    ];

    public function __construct(
        private SiteContentModel $content,
        private PublicCacheInvalidator $publicCache,
        private LocaleRegistry $localeRegistry
    ) {}

    public function index(): Response
    {
        $locales = $this->supportedLocales();
        $locale = $this->request->get['locale'] ?? $locales[0];
        if (!in_array($locale, $locales, true)) {
            $locale = $locales[0];
        }

        $translator = new TranslationService($locale);

        // Materialize each tab with the current locale's values and defaults
        $tabs = [];
        foreach (self::TABS as $tabKey => $tab) {
            $tabs[$tabKey] = [
                'icon' => $tab['icon'],
                'label' => $tab['label'],
                'groups' => $this->materializeGroups($tab['groups'], $locale, $translator),
            ];
        }

        $globalTab = [
            'icon' => self::GLOBAL_TAB['icon'],
            'label' => self::GLOBAL_TAB['label'],
            'groups' => $this->materializeGroups(self::GLOBAL_TAB['groups'], '', null),
        ];

        return $this->view([
            'tabs' => $tabs,
            'globalTab' => $globalTab,
            'locales' => $locales,
            'activeLocale' => $locale,
        ]);
    }

    /**
     * Save whichever form section was posted.
     *
     * Only whitelisted keys are accepted; anything else in the payload is
     * ignored. Empty inputs delete the override so the locale file default
     * shows again.
     */
    public function update(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $locales = $this->supportedLocales();
        $locale = (string) $this->request->postParam('locale');
        if (!in_array($locale, $locales, true)) {
            $locale = $locales[0];
        }

        $localized = $this->collectPosted($this->allLocalizedFieldKeys());
        $global = $this->collectPosted(array_keys($this->flattenGroups(self::GLOBAL_TAB['groups'])));

        if ($localized !== []) {
            $this->content->setMany($localized, $locale);
        }
        if ($global !== []) {
            $this->content->setMany($global, '');
        }

        audit()->log(
            (int) auth()->user()['id'],
            'site_content.updated',
            'setting',
            null,
            ['locale' => $locale, 'fields' => count($localized) + count($global)],
            $this->request->ip()
        );

        $this->publicCache->purgeAllPublic();
        $this->flash('success', 'Front page content saved.');

        $tab = (string) $this->request->postParam('tab', 'hero');

        return $this->redirect('/admin/front-page?locale='.$locale.'&tab='.$tab);
    }

    /**
     * Attach current value and locale-file default to each field in a group.
     *
     * @param  array<string, array<string, array{0: string, 1: string}>>  $groups
     * @return array<string, array<int, array{name: string, label: string, type: string, value: string, default: string}>>
     */
    private function materializeGroups(array $groups, string $locale, ?TranslationService $translator): array
    {
        $out = [];

        foreach ($groups as $groupLabel => $fields) {
            foreach ($fields as $key => [$fieldLabel, $type]) {
                $out[$groupLabel][] = [
                    'name' => str_replace('.', '__', $key),
                    'label' => $fieldLabel,
                    'type' => $type,
                    'value' => $this->content->getExact($key, $locale) ?? '',
                    'default' => $translator ? $translator->translate($key) : '',
                ];
            }
        }

        return $out;
    }

    /**
     * All localized field keys across every tab, keyed by translation key.
     *
     * @return array<int, string>
     */
    private function allLocalizedFieldKeys(): array
    {
        $keys = [];

        foreach (self::TABS as $tab) {
            foreach ($this->flattenGroups($tab['groups']) as $key => $_) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Flatten a groups map into a single key => meta map for iteration.
     *
     * @param  array<string, array<string, array{0: string, 1: string}>>  $groups
     * @return array<string, array{0: string, 1: string}>
     */
    private function flattenGroups(array $groups): array
    {
        $flat = [];
        foreach ($groups as $fields) {
            foreach ($fields as $key => $meta) {
                $flat[$key] = $meta;
            }
        }

        return $flat;
    }

    /**
     * Posted values for whitelisted keys, trimmed and length-capped.
     *
     * @param  array<int, string>  $keys  Whitelisted translation keys
     * @return array<string, string> key => value (empty string requests deletion)
     */
    private function collectPosted(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $fieldName = str_replace('.', '__', $key);
            $posted = $this->request->postParam($fieldName);

            if ($posted === null) {
                continue;
            }

            $values[$key] = mb_substr(trim((string) $posted), 0, 2000);
        }

        return $values;
    }

    /**
     * @return array<int, string> Lowercased supported locale codes
     */
    private function supportedLocales(): array
    {
        return $this->localeRegistry->supported();
    }
}
