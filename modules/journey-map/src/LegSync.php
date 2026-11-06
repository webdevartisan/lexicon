<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Keeps a blog's stored legs in step with its public journey: new neighbours get a leg, and legs between
 * places that are no longer neighbours go. A moved place has a new fingerprint, so its legs are new ones.
 */
final class LegSync
{
    public function __construct(private StopModel $stops, private LegModel $legs)
    {
    }

    /**
     * Bring the blog's legs in line with its journey as readers see it now.
     */
    public function sync(int $blogId): void
    {
        $stored = $this->legs->forBlog($blogId);
        $journey = $this->stops->published($blogId);

        for ($i = 1, $n = count($journey); $i < $n; $i++) {
            $key = Leg::key($journey[$i - 1], $journey[$i]);
            if ($key === null) {
                continue;
            }
            if (!isset($stored[$key])) {
                $this->legs->add($blogId, $journey[$i - 1], $journey[$i]);
            }
            // Marked rather than unset, so a pair that comes twice in one journey is added only once.
            $stored[$key] = true;
        }

        foreach ($stored as $key => $leg) {
            if ($leg !== true) {
                [$from, $to] = explode(':', $key);
                $this->legs->delete($blogId, $from, $to);
            }
        }
    }
}
