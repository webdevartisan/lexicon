/**
 * Markup for the media library's grid tiles and list rows.
 *
 * @param {object} deps t, esc, humanSize, displayName, typeLabel, thumbUrl, checkbox
 * @returns {{grid: function(object[]): string, list: function(object[]): string, icon: function(string): string}}
 */
window.createMediaCards = function (deps) {
    var t = deps.t;
    var esc = deps.esc;

    var ICONS = {
        edit: '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352'
            + 'a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/>'
            + '<line x1="12" x2="12" y1="15" y2="3"/>',
        copy: '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>'
            + '<path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/>'
            + '<path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/>'
            + '<line x1="14" x2="14" y1="11" y2="17"/>',
        more: '<circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>',
    };

    var ACTION_CLASS = 'inline-flex items-center justify-center size-8 rounded-md text-slate-500 hover:text-slate-800'
        + ' hover:bg-slate-100 dark:text-zink-300 dark:hover:text-zink-50 dark:hover:bg-zink-600 transition-colors'
        + ' focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';
    var DELETE_CLASS = 'inline-flex items-center justify-center size-8 rounded-md text-red-500 hover:text-red-700'
        + ' hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10 transition-colors'
        + ' focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500';
    var NAME_CLASS = 'text-[13px] font-medium truncate text-slate-800 dark:text-zink-50';
    var MUTED_CLASS = 'text-xs text-slate-500 dark:text-zink-300';
    var PHONE_CHECKBOX = 'max-sm:hidden max-sm:group-data-[selecting]/media:flex';

    function icon(name) {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"'
            + ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            + ' aria-hidden="true" data-icon="' + name + '">' + ICONS[name] + '</svg>';
    }

    function label(key, item) {
        return esc(t(key, { name: deps.displayName(item) }));
    }

    function meta(item) {
        return esc(deps.typeLabel(item)) + ' · ' + esc(deps.humanSize(item.size_bytes));
    }

    function action(tag, attrs, className, key, iconName, item) {
        var text = label(key, item);
        return '<' + tag + ' ' + attrs + ' class="' + className + '" aria-label="' + text + '" title="' + text + '">'
            + icon(iconName) + '</' + tag + '>';
    }

    function actionsHtml(item) {
        var download = 'href="' + esc(item.url) + '" download="' + esc(deps.displayName(item)) + '"';
        return action('button', 'type="button" data-media-open', ACTION_CLASS, 'edit', 'edit', item)
            + action('a', download, ACTION_CLASS, 'download', 'download', item)
            + action('button', 'type="button" data-media-copy', ACTION_CLASS, 'copy', 'copy', item)
            + action('button', 'type="button" data-media-delete', DELETE_CLASS, 'delete', 'trash', item);
    }

    function image(item, extraClass) {
        return '<img src="' + esc(deps.thumbUrl(item)) + '" alt="" loading="lazy" decoding="async"'
            + ' class="object-cover w-full h-full ' + extraClass + '">';
    }

    function nameLine(name) {
        return '<p data-media-name class="' + NAME_CLASS + '" title="' + name + '">' + name + '</p>';
    }

    function tile(item) {
        var name = esc(deps.displayName(item));
        return ''
            + '<li class="relative flex flex-col overflow-hidden border rounded-md border-slate-200 bg-white'
            + ' dark:border-zink-500 dark:bg-zink-700 data-[selected]:ring-2 data-[selected]:ring-custom-500"'
            + ' data-media-id="' + esc(item.id) + '">'
            + '  <label class="absolute z-10 flex items-center justify-center rounded top-2 start-2 size-7'
            + ' bg-white/90 dark:bg-zink-700/90 ' + PHONE_CHECKBOX + '">' + deps.checkbox(item) + '</label>'
            + '  <button type="button" data-media-open tabindex="-1" aria-hidden="true"'
            + ' class="block w-full overflow-hidden aspect-square sm:aspect-[4/3] bg-slate-100 dark:bg-zink-600">'
            + image(item, 'transition-transform hover:scale-105')
            + '  </button>'
            + '  <button type="button" data-media-more class="absolute inset-0 sm:hidden"'
            + ' aria-label="' + label('options', item) + '"></button>'
            + '  <div class="flex-col flex-1 hidden gap-0.5 p-2 sm:flex">'
            + nameLine(name)
            + '    <p class="' + MUTED_CLASS + '">' + meta(item) + '</p>'
            + '    <div class="flex items-center justify-end gap-0.5 mt-auto pt-1">' + actionsHtml(item) + '</div>'
            + '  </div>'
            + '</li>';
    }

    function row(item, largest) {
        var name = esc(deps.displayName(item));
        var share = largest > 0 ? Math.max(2, Math.round((item.size_bytes || 0) / largest * 100)) : 0;
        return ''
            + '<li class="grid items-center gap-3 px-3 py-2 border-t first:border-t-0 border-slate-200'
            + ' dark:border-zink-500 hover:bg-slate-50 dark:hover:bg-zink-600/40'
            + ' data-[selected]:bg-custom-50 dark:data-[selected]:bg-custom-500/10'
            + ' grid-cols-[2.5rem_minmax(0,1fr)_auto]'
            + ' max-sm:group-data-[selecting]/media:grid-cols-[1rem_2.5rem_minmax(0,1fr)]'
            + ' sm:grid-cols-[1rem_2.5rem_minmax(0,1fr)_4rem_5rem_auto]" data-media-id="' + esc(item.id) + '">'
            + '  <label class="flex ' + PHONE_CHECKBOX + '">' + deps.checkbox(item) + '</label>'
            + '  <button type="button" data-media-open tabindex="-1" aria-hidden="true"'
            + ' class="block overflow-hidden rounded size-10 bg-slate-100 dark:bg-zink-600">'
            + image(item, '')
            + '  </button>'
            + '  <div class="min-w-0">'
            + nameLine(name)
            + '    <p class="sm:hidden ' + MUTED_CLASS + '">' + meta(item) + '</p>'
            + '    <div class="hidden sm:block h-1.5 mt-1 overflow-hidden rounded max-w-44'
            + ' bg-slate-100 dark:bg-zink-600" aria-hidden="true">'
            + '      <div class="h-full rounded bg-slate-400 dark:bg-zink-300" style="width:' + share + '%"></div>'
            + '    </div>'
            + '  </div>'
            + '  <span class="hidden sm:block ' + MUTED_CLASS + '">' + esc(deps.typeLabel(item)) + '</span>'
            + '  <span class="hidden whitespace-nowrap sm:block ' + MUTED_CLASS + '">'
            + esc(deps.humanSize(item.size_bytes)) + '</span>'
            + '  <div class="hidden gap-0.5 sm:flex">' + actionsHtml(item) + '</div>'
            + '  <button type="button" data-media-more aria-label="' + label('options', item) + '"'
            + ' class="inline-flex items-center justify-center rounded-md sm:hidden size-11 -me-2 text-slate-500'
            + ' dark:text-zink-300 max-sm:group-data-[selecting]/media:hidden">' + icon('more') + '</button>'
            + '</li>';
    }

    function grid(items) {
        return '<ul data-media-grid class="grid grid-cols-3 gap-1 sm:gap-3 md:grid-cols-4 xl:grid-cols-6">'
            + items.map(tile).join('') + '</ul>';
    }

    function list(items) {
        var largest = items.reduce(function (max, item) { return Math.max(max, item.size_bytes || 0); }, 0);
        return '<ul data-media-list class="overflow-hidden border rounded-md border-slate-200 dark:border-zink-500">'
            + items.map(function (item) { return row(item, largest); }).join('') + '</ul>';
    }

    return { grid: grid, list: list, icon: icon };
};
