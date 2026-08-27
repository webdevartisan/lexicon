<?php
$discoverTotalPages = (int) ($pagination['totalPages'] ?? 1);

if ($discoverTotalPages > 1) {
    $discoverCurrent = (int) ($pagination['currentPage'] ?? 1);

    $discoverWindow = 2;
    $discoverPages = [1, $discoverTotalPages];
    for ($p = $discoverCurrent - $discoverWindow; $p <= $discoverCurrent + $discoverWindow; $p++) {
        if ($p >= 1 && $p <= $discoverTotalPages) {
            $discoverPages[] = $p;
        }
    }
    $discoverPages = array_unique($discoverPages);
    sort($discoverPages);

    $discoverPageUrl = static function (int $p) use ($tab, $searchQuery): string {
        return lurl('/discover').'?'.http_build_query(array_filter([
            'tab' => $tab,
            'q' => $searchQuery,
            'page' => $p > 1 ? $p : null,
        ]));
    };
    ?>
    <nav aria-label="{{ t('discover.paginationAria') }}">
        <ul class="lx-pagination">
            {% if ($discoverCurrent > 1): %}
                <li>
                    <a href="<?= e($discoverPageUrl($discoverCurrent - 1)) ?>" class="lx-pagination-step" rel="prev">
                        <span class="fa-solid fa-chevron-left" aria-hidden="true"></span>
                        <span class="lx-visually-hidden">{{ t('discover.paginationPrev') }}</span>
                    </a>
                </li>
            {% endif %}

            <?php $discoverPrev = 0; ?>
            {% foreach ($discoverPages as $p): %}
                {% if ($p - $discoverPrev > 1): %}
                    <li><span class="lx-pagination-gap" aria-hidden="true">&hellip;</span></li>
                {% endif %}
                <li>
                    <a href="<?= e($discoverPageUrl($p)) ?>"
                       <?= $p === $discoverCurrent ? 'aria-current="page"' : '' ?>>
                        <?= (int) $p ?>
                    </a>
                </li>
                <?php $discoverPrev = $p; ?>
            {% endforeach; %}

            {% if ($discoverCurrent < $discoverTotalPages): %}
                <li>
                    <a href="<?= e($discoverPageUrl($discoverCurrent + 1)) ?>" class="lx-pagination-step" rel="next">
                        <span class="lx-visually-hidden">{{ t('discover.paginationNext') }}</span>
                        <span class="fa-solid fa-chevron-right" aria-hidden="true"></span>
                    </a>
                </li>
            {% endif %}
        </ul>
    </nav>
<?php } ?>
