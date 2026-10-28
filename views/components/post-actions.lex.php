<?php
/**
 * The actions menu for one post. The Posts list rows and the post cards both use
 * it, so the two views always offer the same actions in the same order.
 *
 * Attributes:
 * - post: the post row, with a `can` map of what the viewer may do with it
 * - blogSlug: slug of the post's blog, for the preview link
 * - returnToken: where the editor and delete send the viewer back to
 */
$post = $post ?? [];
$postId = (int) ($post['id'] ?? 0);
$postTitle = (string) ($post['title'] ?? '');
$status = (string) ($post['status'] ?? 'draft');
$visibility = (string) ($post['visibility'] ?? 'public');
$featured = !empty($post['is_featured']);
$returnQuery = '?r='.urlencode((string) ($returnToken ?? ''));
// Pages that list posts for one role pass what that person may do; without it every action shows.
// Duplicate needs the dialog that only the Posts list renders, so it stays off unless asked for.
$can = ($post['can'] ?? []) + ['update' => true, 'publish' => true, 'delete' => true, 'duplicate' => false];

$dashPost = static fn (string $to): string => lurl('/dashboard/post/'.$postId.'/'.$to);
$publicHref = $status === 'published' && !empty($post['slug'])
    ? '/blog/'.rawurlencode((string) ($blogSlug ?? '')).'/'.rawurlencode((string) $post['slug'])
    : '';

$postActions = [
    ['label' => $t('components.postCard.tooltips.edit'), 'icon' => 'pencil', 'href' => '/dashboard/post/'.$postId.'/edit'.$returnQuery, 'can' => $can['update']],
    ['label' => $t('components.postCard.actions.preview'), 'icon' => 'external-link', 'href' => $publicHref, 'newTab' => true, 'can' => $publicHref !== ''],
    ['label' => $t('components.postCard.tooltips.publish'), 'icon' => 'send', 'post' => $dashPost('publish'), 'can' => $can['publish'] && in_array($status, ['draft', 'archived', 'scheduled'], true)],
    ['label' => $t('components.postCard.tooltips.draft'), 'icon' => 'pencil-ruler', 'post' => $dashPost('draft'), 'can' => $can['publish'] && in_array($status, ['archived', 'published', 'scheduled'], true)],
    ['label' => $t('components.postCard.tooltips.archive'), 'icon' => 'archive', 'post' => $dashPost('archive'), 'can' => $can['publish'] && $status !== 'archived'],
    ['label' => $t('components.postCard.visibility.makePublic'), 'icon' => 'globe', 'post' => $dashPost('make-public'), 'confirm' => $t('components.postCard.visibility.makePublicConfirm'), 'can' => $can['publish'] && $visibility !== 'public'],
    ['label' => $t('components.postCard.visibility.makePrivate'), 'icon' => 'lock', 'post' => $dashPost('make-private'), 'confirm' => $t('components.postCard.visibility.makePrivateConfirm'), 'can' => $can['publish'] && $visibility !== 'private'],
    ['label' => $t('components.postCard.visibility.unlist'), 'icon' => 'eye-off', 'post' => $dashPost('unlist'), 'confirm' => $t('components.postCard.visibility.unlistConfirm'), 'can' => $can['publish'] && $visibility !== 'unlisted'],
    ['label' => $t($featured ? 'components.postCard.unfeature' : 'components.postCard.feature'), 'icon' => $featured ? 'star-off' : 'star', 'post' => $dashPost('feature'), 'can' => $can['update']],
    ['label' => $t('dashboard.posts.duplicate.action'), 'icon' => 'copy', 'data' => ['post-duplicate' => $dashPost('duplicate')], 'can' => $can['duplicate']],
    ['label' => $t('components.postCard.tooltips.delete'), 'icon' => 'trash-2', 'post' => $dashPost('destroy').$returnQuery, 'confirm' => $t('components.postCard.deleteConfirm', ['title' => $postTitle]), 'danger' => true, 'can' => $can['delete']],
];
?>
{% cmp="row-actions" title="{$postTitle}" items="{$postActions}" %}
