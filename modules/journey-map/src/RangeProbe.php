<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Asks this site for the first bytes of the map file, the way a reader's browser does.
 * A server that answers 206 serves the map; one that answers 200 sends the whole file every time.
 */
class RangeProbe
{
    /**
     * The status code of a range request, or 0 when the site could not be reached.
     */
    public function status(string $url): int
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RANGE => '0-99',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return $status;
    }
}
