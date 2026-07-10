<div id="sidebar">
    <div class="inner">
        <!-- Menu -->
        <nav id="menu">
            <header class="major">
                <h2>{{ t('sidebar.menu.title') }}</h2>
            </header>
            <ul>
                {% foreach ($nav_items as $it): %}
                <li>
                    <a href="<?= lurl($it['href']) ?>" {{ it['current_attr'] }}>{{ it['label'] }}</a>
                </li>
                {% endforeach; %}
            </ul>
        </nav>

        <!-- Getting started -->
        <section>
            <header class="major">
                <h2><?= e(site_content('sidebar.gettingStarted.title')) ?></h2>
            </header>
            <div class="mini-posts">
                <article>
                    <a href="/getting-started/start-your-first-blog" class="image"><img src="/images/pic07.jpg" alt="" loading="lazy" /></a>
                    <p><?= e(site_content('sidebar.gettingStarted.items.tip1')) ?></p>
                </article>
                <article>
                    <a href="/getting-started/write-posts-people-read" class="image"><img src="/images/pic08.jpg" alt="" loading="lazy" /></a>
                    <p><?= e(site_content('sidebar.gettingStarted.items.tip2')) ?></p>
                </article>
                <article>
                    <a href="/getting-started/blog-with-your-team" class="image"><img src="/images/pic09.jpg" alt="" loading="lazy" /></a>
                    <p><?= e(site_content('sidebar.gettingStarted.items.tip3')) ?></p>
                </article>
            </div>
            <ul class="actions">
                <li><a href="/getting-started" class="button"><?= e(site_content('sidebar.gettingStarted.actionMore')) ?></a></li>
            </ul>
        </section>

        <!-- Get in touch -->
        <section>
            <header class="major">
                <h2><?= e(site_content('contact.title')) ?></h2>
            </header>
            <p><?= e(site_content('contact.body')) ?></p>
            <?php
            // Only details the admin actually configured are shown; the
            // contact page link is always there as the reliable channel.
            $contactEmail = site_content('contact.email', '');
            $contactPhone = site_content('contact.phone', '');
            $contactAddress = site_content('contact.address', '');
            ?>
            <ul class="contact">
                {% if (!empty($contactEmail)): %}
                <li class="icon solid fa fa-envelope"><a href="mailto:<?= e($contactEmail) ?>">{{ contactEmail }}</a></li>
                {% endif %}
                {% if (!empty($contactPhone)): %}
                <li class="icon solid fa fa-phone">{{ contactPhone }}</li>
                {% endif %}
                {% if (!empty($contactAddress)): %}
                <li class="icon solid fa fa-home">{{ contactAddress }}</li>
                {% endif %}
                <li class="icon solid fa fa-comment"><a href="/contact">{{ t('contact.link') }}</a></li>
            </ul>
        </section>
    </div>
</div>
