<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:atom="http://www.w3.org/2005/Atom"
    xmlns:dc="http://purl.org/dc/elements/1.1/">
  <xsl:output method="html" encoding="UTF-8" indent="yes" />

  <xsl:template match="/rss/channel">
    <html lang="en">
      <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex" />
        <title><xsl:value-of select="title" /> feed</title>
        <style>
          body { font: 16px/1.6 system-ui, sans-serif; color: #1a1a1a; background: #fafafa; margin: 0; }
          main { max-width: 42rem; margin: 0 auto; padding: 2rem 1rem 4rem; }
          .notice { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 2rem; }
          .notice p { margin: 0.25rem 0; }
          code { background: #f0f0f0; padding: 0.1rem 0.4rem; border-radius: 4px; word-break: break-all; }
          h1 { font-size: 1.75rem; margin: 0 0 0.25rem; }
          ul { list-style: none; padding: 0; margin: 0; }
          li { padding: 1rem 0; border-top: 1px solid #ddd; }
          li a { font-size: 1.15rem; font-weight: 600; color: inherit; }
          .meta { color: #666; font-size: 0.875rem; }
          @media (prefers-color-scheme: dark) {
            body { background: #151515; color: #eee; }
            .notice { background: #1e1e1e; border-color: #333; }
            code { background: #2a2a2a; }
            li { border-color: #333; }
            .meta { color: #aaa; }
          }
        </style>
      </head>
      <body>
        <main>
          <div class="notice">
            <p><strong>This is a feed.</strong> Copy this page's address into a feed reader to follow new posts.</p>
            <p><code><xsl:value-of select="atom:link[@rel='self']/@href" /></code></p>
          </div>
          <h1><xsl:value-of select="title" /></h1>
          <p><xsl:value-of select="description" /></p>
          <p><a href="{link}">Visit the blog</a></p>
          <ul>
            <xsl:for-each select="item">
              <li>
                <a href="{link}"><xsl:value-of select="title" /></a>
                <div class="meta"><xsl:value-of select="dc:creator" /> · <xsl:value-of select="substring(pubDate, 1, 16)" /></div>
                <div><xsl:value-of select="description" /></div>
              </li>
            </xsl:for-each>
          </ul>
        </main>
      </body>
    </html>
  </xsl:template>
</xsl:stylesheet>
