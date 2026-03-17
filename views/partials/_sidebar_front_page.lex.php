<div id="sidebar">
    <div class="inner">
        <!-- Search -->
            <section id="search" class="alt">
                <form method="get" action="/search">
                    <input type="text" name="q" id="query" placeholder="Search" />
                </form>
            </section>

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

        <!-- Section -->
            <section>
                <header class="major">
                    <h2>Getting Started</h2>
                </header>
                <div class="mini-posts">
                    <article>
                        <a href="#" class="image"><img src="/images/pic07.jpg" alt="" /></a>
                        <p>New to blogging? Here's how to launch your first post in under 10 minutes.</p>
                    </article>
                    <article>
                        <a href="#" class="image"><img src="/images/pic08.jpg" alt="" /></a>
                        <p>Why BlogHub was built: a note from the creator on simplicity and creative freedom.</p>
                    </article>
                    <article>
                        <a href="#" class="image"><img src="/images/pic09.jpg" alt="" /></a>
                        <p>Coming soon: custom domains, monetization tools, and more — help shape what’s next.</p>
                    </article>
                </div>
                <ul class="actions">
                    <li><a href="#" class="button">More</a></li>
                </ul>
            </section>

        <!-- Section -->
            <section>
                <header class="major">
                    <h2>Get in touch</h2>
                </header>
                <p>Have questions, ideas, or feedback? We'd love to hear from you.</p>
                <ul class="contact">
                    <li class="icon solid fa fa-envelope"><a href="#">hello@bloghub.dev</a></li>
                    <li class="icon solid fa fa-phone">(000) 000-0000</li>
                    <li class="icon solid fa fa-home">1234 Somewhere Road #8254<br />
                    Nashville, TN 00000-0000</li>
                </ul>
            </section>

    </div>
</div>