<?php

declare(strict_types=1);

namespace App\Helpers;

class LinksHelper
{
    /**
     * Transform social links array to flat key-value pairs for form display.
     *
     * @param  array<int, array{network: string, url: string}>  $links  Social links from database
     * @return array<string, string> Flat array keyed by network name
     */
    public static function linksToFlatInputs(array $links): array
    {
        $out = [
            'website' => '',
            'twitter' => '',
            'instagram' => '',
            'linkedin' => '',
            'github' => '',
        ];

        foreach ($links as $row) {
            $network = $row['network'];
            if (isset($out[$network])) {
                $out[$network] = $row['url'];
            }
        }

        return $out;
    }
}
