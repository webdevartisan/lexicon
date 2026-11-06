{% extends "back.lex.php" %}

{% block title %}Journey map{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">The map every blog with the extra shows, place search, and who uses it.</span>{% endblock %}
{% block body %}
<?php
/**
 * @var array<string, mixed> $status From MapControl::status
 * @var array<string, mixed>|null $preview From MapControl::preview, null without a map
 * @var int|null $rangeStatus The answer to a range request for the map file, null without a map
 * @var array<string, mixed> $search Place search settings and health
 * @var list<array<string, mixed>> $usage Blogs with the extra on
 * @var list<array<string, mixed>> $outside Stops beyond the downloaded area
 * @var list<int> $zooms
 * @var list<string> $presets
 */
$base = '/admin/m/journey-map';
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$heading = 'text-15 font-semibold text-slate-800 dark:text-zink-50';
$label = 'block mb-1 text-sm font-medium text-slate-700 dark:text-zink-200';
$field = 'form-input w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:text-zink-100';
$tool = $status['tool'];
$job = $status['job'];
$busy = $status['busy'];
$idleOnly = $busy ? ['disabled' => 'disabled', 'data-idle-only' => '1'] : ['data-idle-only' => '1'];
$downloadAttrs = $idleOnly + ['formaction' => lurl($base.'/download')];
if ($status['lines']['replace'] !== '') {
    $downloadAttrs += ['data-confirm' => $status['lines']['replace'], 'data-confirm-ok' => 'Download'];
}
$removeAttrs = $idleOnly;
$health = $search['health'];
$when = static fn (?string $time): string => $time === null ? 'never' : str_replace(['T', 'Z'], [' ', ' UTC'], $time);
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto" data-journey-map-admin
     data-status-url="<?= e(lurl($base.'/status')) ?>" data-busy="<?= $busy ? '1' : '0' ?>"
     data-failed-text="The request did not go through. Try again." data-check-fields="Check the marked fields.">
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">

    <section class="card xl:col-span-2">
      <div class="card-body space-y-4">
        <h2 class="<?= $heading ?>">Map</h2>
        <p class="text-sm text-slate-700 dark:text-zink-200" data-line="map"><?= e($status['lines']['map']) ?></p>
        <?php if ($preview !== null) { ?>
          <div class="journey-map-admin-preview" aria-label="The downloaded area" role="img"
               data-area-preview="<?= e(json_encode($preview['urls'], JSON_UNESCAPED_SLASHES)) ?>"
               data-bbox="<?= e(json_encode($preview['bbox'])) ?>"></div>
        <?php } ?>
        <div class="space-y-1" data-job <?= $job === null ? 'hidden' : '' ?>>
          <p class="text-sm text-slate-700 dark:text-zink-200" data-line="job"><?= e($status['lines']['job']) ?></p>
          <progress class="w-full" max="100" value="<?= (int) ($job['percent'] ?? 0) ?>" data-progress <?= $busy ? '' : 'hidden' ?>></progress>
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
          <div>
            <dt class="font-medium text-slate-700 dark:text-zink-200">Map tool</dt>
            <dd class="<?= $muted ?>">
              <?php if ($tool['installed']) { ?>
                Installed, <?= e($tool['version'] ?? 'unknown version') ?>.
              <?php } elseif (!$tool['programs']) { ?>
                This server does not let the site run programs. Use the commands in the module README instead.
              <?php } elseif (!$tool['supported']) { ?>
                The map tool has no build for this server. Use the commands in the module README instead.
              <?php } else { ?>
                Not installed yet.
              <?php } ?>
            </dd>
          </div>
          <div>
            <dt class="font-medium text-slate-700 dark:text-zink-200">Server check</dt>
            <dd class="<?= $muted ?>">
              <?php if ($rangeStatus === null) { ?>
                Runs once a map is installed.
              <?php } elseif ($rangeStatus === 206) { ?>
                The server sends the map in parts, as browsers need.
              <?php } elseif ($rangeStatus === 0) { ?>
                The site could not reach itself to check. Open the map file in a browser to be sure it loads.
              <?php } else { ?>
                The server answered <?= (int) $rangeStatus ?> instead of 206, so readers' browsers cannot load the map in parts. Check that the web server allows range requests for public/modules/journey-map/files/.
              <?php } ?>
            </dd>
          </div>
          <div>
            <dt class="font-medium text-slate-700 dark:text-zink-200">Size and space</dt>
            <dd class="<?= $muted ?>" data-line="size"><?= e($status['lines']['size']) ?></dd>
          </div>
        </dl>

        <?php if (!$tool['installed'] && $tool['programs'] && $tool['supported']) { ?>
          <form method="post" action="<?= e(lurl($base.'/tool')) ?>" data-journey-map-form data-reload>
            <?= csrf_field() ?>
            <p class="<?= $muted ?> mb-2">The page fetches the official pmtiles release for this server from GitHub and keeps it only if it matches the checksum GitHub publishes.</p>
            {% cmp="btn" type="submit" variant="blue" icon="download" label="Get the map tool" %}
          </form>
        <?php } ?>

        <form method="post" action="<?= e(lurl($base.'/area')) ?>" class="flex flex-wrap items-end gap-3" data-journey-map-form data-area-form>
          <?= csrf_field() ?>
          <div class="grow max-w-md">
            <label class="<?= $label ?>" for="journey-map-place">Country or region by name</label>
            <input id="journey-map-place" name="name" type="text" maxlength="120" class="<?= $field ?>" placeholder="Portugal">
            <p class="mt-1 text-xs text-red-600" data-error="name" hidden></p>
          </div>
          {% cmp="btn" type="submit" variant="slate" icon="search" label="Look up" %}
        </form>

        <form method="post" action="<?= e(lurl($base.'/check')) ?>" class="space-y-3" data-journey-map-form data-map-form>
          <?= csrf_field() ?>
          <input type="hidden" name="region" value="" data-region>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="<?= $label ?>" for="journey-map-zoom">Detail</label>
              <select id="journey-map-zoom" name="zoom" class="<?= $field ?>">
                <?php foreach ($zooms as $zoom) { ?>
                  <option value="<?= (int) $zoom ?>" <?= $zoom === 10 ? 'selected' : '' ?>>
                    <?= e(['8' => '8: regions and large towns', '9' => '9: towns', '10' => '10: towns and main roads'][(string) $zoom]) ?>
                  </option>
                <?php } ?>
              </select>
              <p class="mt-1 text-xs text-red-600" data-error="zoom" hidden></p>
            </div>
            <div>
              <label class="<?= $label ?>" for="journey-map-area">Area</label>
              <select id="journey-map-area" name="area" class="<?= $field ?>">
                <option value="world">Whole world</option>
                <?php foreach ($presets as $preset) { ?>
                  <option value="<?= e($preset) ?>"><?= e(ucwords(str_replace('-', ' ', $preset))) ?></option>
                <?php } ?>
                <option value="place" data-place-option hidden>The place you looked up</option>
              </select>
              <p class="mt-1 text-xs text-red-600" data-error="area" hidden></p>
              <p class="mt-1 text-xs text-red-600" data-error="region" hidden></p>
            </div>
            <div class="flex items-end">
              <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-zink-200">
                <input type="hidden" name="overview" value="0">
                <input type="checkbox" name="overview" value="1" checked class="form-checkbox">
                Add a world overview up to zoom 5
              </label>
            </div>
          </div>
          <div class="flex flex-wrap gap-3">
            {% cmp="btn" type="submit" variant="slate" icon="ruler" label="Check size" attrs="{$idleOnly}" %}
            {% cmp="btn" type="submit" variant="blue" icon="download" label="Download" attrs="{$downloadAttrs}" %}
          </div>
          <p class="<?= $muted ?>">Download needs a size check of the same choice first, and room on the disk for it with a tenth to spare. An installed map keeps working until the new one is complete.</p>
        </form>

        <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-4 dark:border-zink-500">
          <form method="post" action="<?= e(lurl($base.'/cancel')) ?>" data-journey-map-form>
            <?= csrf_field() ?>
            {% cmp="btn" type="submit" variant="slate" icon="square" label="Cancel the job" %}
          </form>
          <form method="post" action="<?= e(lurl($base.'/remove')) ?>" data-journey-map-form data-reload
                data-confirm="Delete the map, its fonts and its icons? Pages show places without a map until you download it again."
                data-confirm-tone="danger" data-confirm-ok="Remove">
            <?= csrf_field() ?>
            {% cmp="btn" type="submit" variant="red" icon="trash-2" label="Remove the map" attrs="{$removeAttrs}" %}
          </form>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-body space-y-3">
        <h2 class="<?= $heading ?>">Place search</h2>
        <ul class="<?= $muted ?> space-y-1">
          <li>Last answer: <?= e($when($health['lastSuccess'])) ?></li>
          <li>Last failure: <?= e($when($health['lastFailure'])) ?><?= $health['failureReason'] !== null ? ' ('.e($health['failureReason']).')' : '' ?></li>
          <li>Searches refused as busy today: <?= (int) $health['busyToday'] ?></li>
        </ul>
        <form method="post" action="<?= e(lurl($base.'/search')) ?>" class="space-y-3" data-journey-map-form>
          <?= csrf_field() ?>
          <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-zink-200">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" class="form-checkbox" <?= $search['enabled'] ? 'checked' : '' ?>>
            Search places by name in the editor
          </label>
          <p class="<?= $muted ?>">Off hides the search box. Writers can still drop a pin on the map or type coordinates.</p>
          <div>
            <label class="<?= $label ?>" for="journey-map-contact">Contact address sent with each search</label>
            <input id="journey-map-contact" name="contact" type="email" class="<?= $field ?>" value="<?= e($search['contact']) ?>">
            <p class="mt-1 text-xs text-red-600" data-error="contact" hidden></p>
          </div>
          <div>
            <label class="<?= $label ?>" for="journey-map-server">Search server</label>
            <input id="journey-map-server" name="server" type="url" class="<?= $field ?>" value="<?= e($search['server']) ?>" placeholder="https://nominatim.openstreetmap.org/search">
            <p class="mt-1 <?= $muted ?>">Leave empty for the public OpenStreetMap service, which allows one search a second for the whole site. A server of your own has no such limit.</p>
            <p class="mt-1 text-xs text-red-600" data-error="server" hidden></p>
          </div>
          {% cmp="btn" type="submit" variant="blue" icon="save" label="Save" %}
        </form>
        <form method="post" action="<?= e(lurl($base.'/search/clear')) ?>" data-journey-map-form>
          <?= csrf_field() ?>
          {% cmp="btn" type="submit" variant="slate" icon="eraser" label="Clear saved answers" %}
        </form>
      </div>
    </section>

    <section class="card">
      <div class="card-body space-y-3">
        <h2 class="<?= $heading ?>">Usage</h2>
        <?php if ($usage === []) { ?>
          <p class="<?= $muted ?>">No blog has switched the journey map on yet.</p>
        <?php } else { ?>
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-start text-slate-500 dark:text-zink-300">
                <th class="py-1 pe-3 text-start font-medium">Blog</th>
                <th class="py-1 pe-3 text-end font-medium">Stops</th>
                <th class="py-1 pe-3 text-end font-medium">Public</th>
                <th class="py-1 text-start font-medium">Last change</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usage as $row) { ?>
                <tr class="border-t border-slate-200 dark:border-zink-500">
                  <td class="py-1 pe-3"><?= e($row['blog_name']) ?></td>
                  <td class="py-1 pe-3 text-end"><?= (int) $row['stops'] ?></td>
                  <td class="py-1 pe-3 text-end"><?= (int) $row['public_stops'] ?></td>
                  <td class="py-1 <?= $muted ?>"><?= e($row['last_added'] ?? 'none') ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
          </div>
        <?php } ?>
        <?php if ($outside !== []) { ?>
          <details class="text-sm">
            <summary class="cursor-pointer text-amber-700 dark:text-amber-200">
              <?= count($outside) ?> <?= count($outside) === 1 ? 'stop lies' : 'stops lie' ?> outside the downloaded area, so readers see them only on the overview.
            </summary>
            <ul class="mt-2 list-disc ps-5 <?= $muted ?>">
              <?php foreach ($outside as $stop) { ?>
                <li><?= e($stop['place_name']) ?>: <?= e($stop['title']) ?> (<?= e($stop['blog_name']) ?>)</li>
              <?php } ?>
            </ul>
          </details>
        <?php } ?>
      </div>
    </section>

    <section class="card xl:col-span-2">
      <div class="card-body space-y-3 text-sm text-slate-700 dark:text-zink-200">
        <h2 class="<?= $heading ?>">Help</h2>
        <p>Detail 8 shows regions and large towns, 9 adds towns, 10 adds main roads. Each step up roughly doubles or triples the size.</p>
        <p>A whole continent or one country keeps the file small. The world overview adds outlines and big cities for the rest of the world, so readers far from your area never see a blank map.</p>
        <p>Roads and names change. Download again now and then, or schedule the "Journey map download" task on the Scheduler page to refresh the same choice.</p>
        <p>Map data comes from OpenStreetMap (ODbL) through Protomaps. The map shows the attribution readers need. Shell commands for servers that cannot run programs are in modules/journey-map/README.md.</p>
      </div>
    </section>
  </div>
</div>
{% endblock %}

{% block head %}
<?php foreach ($preview['files']['css'] ?? [] as $file) { ?>
<link rel="stylesheet" href="<?= e(versioned_asset('/modules/journey-map/'.$file)) ?>">
<?php } ?>
<link rel="stylesheet" href="<?= e(versioned_asset('/modules/journey-map/admin.css')) ?>">
{% endblock %}

{% block scripts %}
<?php foreach ($preview === null ? [] : [...$preview['files']['js'], 'admin-preview.js'] as $file) { ?>
<script src="<?= e(versioned_asset('/modules/journey-map/'.$file)) ?>" defer></script>
<?php } ?>
<script src="<?= e(versioned_asset('/modules/journey-map/admin.js')) ?>" defer></script>
{% endblock %}
