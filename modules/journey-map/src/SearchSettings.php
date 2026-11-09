<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Models\SettingModel;

/**
 * The site wide place search settings, kept in the core settings table under journey-map. keys.
 */
final class SearchSettings
{
    private const ENABLED = 'journey-map.search_enabled';

    private const CONTACT = 'journey-map.search_contact';

    private const SERVER = 'journey-map.search_server';

    private const SERVER_MAX = 255;

    public function __construct(private SettingModel $settings) {}

    /**
     * Whether writers can search for places by name. Off still lets them pin the map or type coordinates.
     */
    public function enabled(): bool
    {
        return $this->settings->getBool(self::ENABLED, true);
    }

    /**
     * The address the search service may write to about this site's use, as its policy asks.
     */
    public function contact(): string
    {
        return (string) ($this->settings->get(self::CONTACT) ?? site_content('contact.email', ''));
    }

    /**
     * A self hosted search address, or empty for the public OpenStreetMap service.
     */
    public function server(): string
    {
        return (string) $this->settings->get(self::SERVER, '');
    }

    /**
     * Where searches go.
     */
    public function endpoint(): string
    {
        return $this->server() !== '' ? $this->server() : PlaceSearch::PUBLIC_ENDPOINT;
    }

    /**
     * Check and store all three settings, or store nothing.
     *
     * @return array<string, string> Field name => what is wrong, empty when saved
     */
    public function save(bool $enabled, string $contact, string $server): array
    {
        $contact = trim($contact);
        $server = trim($server);
        $errors = [];

        if ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact'] = 'Enter an email address, or leave it empty.';
        }
        if ($server !== '' && !$this->validServer($server)) {
            $errors['server'] = 'Use an https address ending in /search, like https://maps.example.org/search.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $this->settings->setMany([
            self::ENABLED => $enabled ? '1' : '0',
            self::CONTACT => $contact,
            self::SERVER => $server,
        ]);

        return [];
    }

    /**
     * An https address with a host, no sign in details, no query, and the Nominatim /search path.
     */
    private function validServer(string $server): bool
    {
        $parts = parse_url($server);
        if (!is_array($parts) || strlen($server) > self::SERVER_MAX) {
            return false;
        }

        return ($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') !== ''
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment'])
            && str_ends_with($parts['path'] ?? '', '/search');
    }
}
