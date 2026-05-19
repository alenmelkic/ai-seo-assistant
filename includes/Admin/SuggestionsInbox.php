<?php

namespace AiSeoAssistant\Admin;

use AiSeoAssistant\Suggestions\Suggestion;
use AiSeoAssistant\Suggestions\SuggestionRepository;

class SuggestionsInbox
{
    public function render(): void
    {
        if (!current_user_can('manage_aisa')) {
            wp_die(__('You do not have permission to access this page.', 'ai-seo-assistant'));
        }

        $this->handleActions();

        $status = sanitize_text_field($_GET['status'] ?? 'pending');
        $type = sanitize_text_field($_GET['suggestion_type'] ?? '');
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $perPage = 20;

        $args = [
            'status' => $status,
            'per_page' => $perPage,
            'offset' => ($paged - 1) * $perPage,
            'exclude_snoozed' => ($status === 'pending'),
        ];

        if (!empty($type)) {
            $args['type'] = $type;
        }

        $suggestions = SuggestionRepository::query($args);
        $totalItems = SuggestionRepository::count(array_filter([
            'status' => $status,
            'type' => $type ?: null,
        ]));
        $totalPages = ceil($totalItems / $perPage);

        $pendingCount = SuggestionRepository::countPending();

        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('SEO Suggestions Inbox', 'ai-seo-assistant'); ?>
                <?php if ($pendingCount > 0): ?>
                    <span class="aisa-badge"><?php echo esc_html($pendingCount); ?></span>
                <?php endif; ?>
            </h1>

            <!-- Filters -->
            <ul class="subsubsub">
                <?php
                $statuses = [
                    'pending' => __('Pending', 'ai-seo-assistant'),
                    'accepted' => __('Accepted', 'ai-seo-assistant'),
                    'rejected' => __('Rejected', 'ai-seo-assistant'),
                    'expired' => __('Expired', 'ai-seo-assistant'),
                ];
                $statusLinks = [];
                foreach ($statuses as $key => $label) {
                    $count = SuggestionRepository::count(['status' => $key]);
                    $current = ($status === $key) ? ' class="current"' : '';
                    $url = add_query_arg(['page' => 'aisa-suggestions', 'status' => $key], admin_url('admin.php'));
                    $statusLinks[] = "<li><a href='" . esc_url($url) . "'{$current}>" . esc_html($label) . " <span class='count'>({$count})</span></a></li>";
                }
                echo implode(' | ', $statusLinks);
                ?>
            </ul>

            <!-- Type filter -->
            <form method="get" class="aisa-inbox-filters">
                <input type="hidden" name="page" value="aisa-suggestions" />
                <input type="hidden" name="status" value="<?php echo esc_attr($status); ?>" />
                <select name="suggestion_type">
                    <option value=""><?php esc_html_e('All types', 'ai-seo-assistant'); ?></option>
                    <option value="meta_title" <?php selected($type, 'meta_title'); ?>><?php esc_html_e('Meta Title', 'ai-seo-assistant'); ?></option>
                    <option value="meta_description" <?php selected($type, 'meta_description'); ?>><?php esc_html_e('Meta Description', 'ai-seo-assistant'); ?></option>
                    <option value="aeo_refresh" <?php selected($type, 'aeo_refresh'); ?>><?php esc_html_e('AEO Refresh', 'ai-seo-assistant'); ?></option>
                </select>
                <?php submit_button(__('Filter', 'ai-seo-assistant'), 'secondary', 'filter', false); ?>
            </form>

            <!-- Suggestions table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-post"><?php esc_html_e('Post', 'ai-seo-assistant'); ?></th>
                        <th scope="col" class="column-type"><?php esc_html_e('Type', 'ai-seo-assistant'); ?></th>
                        <th scope="col" class="column-reason"><?php esc_html_e('Reason', 'ai-seo-assistant'); ?></th>
                        <th scope="col" class="column-date"><?php esc_html_e('Date', 'ai-seo-assistant'); ?></th>
                        <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'ai-seo-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suggestions)): ?>
                        <tr>
                            <td colspan="5">
                                <?php esc_html_e('No suggestions found.', 'ai-seo-assistant'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suggestions as $suggestion): ?>
                            <?php $this->renderRow($suggestion); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $paged,
                            'total' => $totalPages,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .aisa-badge { background: #d63638; color: #fff; border-radius: 10px; padding: 2px 8px; font-size: 12px; vertical-align: middle; }
            .aisa-inbox-filters { margin: 12px 0; display: flex; gap: 8px; align-items: center; }
            .aisa-suggestion-detail { background: #f6f7f7; padding: 12px; margin-top: 8px; border-radius: 4px; }
            .aisa-suggestion-diff { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 8px 0; }
            .aisa-suggestion-diff__side { padding: 8px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 13px; word-break: break-word; }
            .aisa-suggestion-diff__label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #757575; margin-bottom: 4px; }
            .aisa-suggestion-actions { display: flex; gap: 8px; margin-top: 8px; }
            .aisa-gsc-context { font-size: 12px; color: #757575; margin-top: 8px; }
            .column-type { width: 120px; }
            .column-date { width: 120px; }
            .column-actions { width: 100px; }
        </style>
        <?php
    }

    private function renderRow(Suggestion $suggestion): void
    {
        $post = get_post($suggestion->postId);
        $postTitle = $post ? $post->post_title : __('(deleted)', 'ai-seo-assistant');
        $editUrl = $post ? get_edit_post_link($suggestion->postId) : '';
        $typeLabels = [
            'meta_title' => __('Meta Title', 'ai-seo-assistant'),
            'meta_description' => __('Meta Description', 'ai-seo-assistant'),
            'aeo_refresh' => __('AEO Refresh', 'ai-seo-assistant'),
        ];

        $detailId = 'aisa-detail-' . $suggestion->id;
        ?>
        <tr>
            <td class="column-post">
                <?php if ($editUrl): ?>
                    <a href="<?php echo esc_url($editUrl); ?>"><strong><?php echo esc_html($postTitle); ?></strong></a>
                <?php else: ?>
                    <strong><?php echo esc_html($postTitle); ?></strong>
                <?php endif; ?>
            </td>
            <td class="column-type">
                <span class="aisa-type-label"><?php echo esc_html($typeLabels[$suggestion->type] ?? $suggestion->type); ?></span>
            </td>
            <td class="column-reason">
                <?php echo esc_html(wp_trim_words($suggestion->reason ?? '', 15)); ?>
                <button type="button" class="button-link" onclick="document.getElementById('<?php echo esc_attr($detailId); ?>').toggleAttribute('hidden')">
                    <?php esc_html_e('Show details', 'ai-seo-assistant'); ?>
                </button>
                <div id="<?php echo esc_attr($detailId); ?>" class="aisa-suggestion-detail" hidden>
                    <p><strong><?php esc_html_e('Reason:', 'ai-seo-assistant'); ?></strong> <?php echo esc_html($suggestion->reason); ?></p>
                    <div class="aisa-suggestion-diff">
                        <div class="aisa-suggestion-diff__side">
                            <div class="aisa-suggestion-diff__label"><?php esc_html_e('Current', 'ai-seo-assistant'); ?></div>
                            <?php echo esc_html($suggestion->currentValue ?: __('(empty)', 'ai-seo-assistant')); ?>
                        </div>
                        <div class="aisa-suggestion-diff__side">
                            <div class="aisa-suggestion-diff__label"><?php esc_html_e('Suggested', 'ai-seo-assistant'); ?></div>
                            <?php echo esc_html($suggestion->suggestedValue ?: __('(empty)', 'ai-seo-assistant')); ?>
                        </div>
                    </div>
                    <?php if ($suggestion->gscSnapshot): ?>
                        <div class="aisa-gsc-context">
                            <strong><?php esc_html_e('GSC Data:', 'ai-seo-assistant'); ?></strong>
                            <?php if (!empty($suggestion->gscSnapshot['top_query'])): ?>
                                <?php printf(
                                    esc_html__('Top query: "%s" (CTR: %s%%, Position: %s)', 'ai-seo-assistant'),
                                    esc_html($suggestion->gscSnapshot['top_query']),
                                    esc_html($suggestion->gscSnapshot['top_query_ctr'] ?? 'N/A'),
                                    esc_html($suggestion->gscSnapshot['position_avg'] ?? 'N/A')
                                ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($suggestion->status === 'pending'): ?>
                        <div class="aisa-suggestion-actions">
                            <?php $this->renderActionButton($suggestion->id, 'accept', __('Accept', 'ai-seo-assistant'), 'primary'); ?>
                            <?php $this->renderActionButton($suggestion->id, 'reject', __('Reject', 'ai-seo-assistant'), 'secondary'); ?>
                            <?php $this->renderActionButton($suggestion->id, 'snooze', __('Snooze 7 days', 'ai-seo-assistant'), 'secondary'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </td>
            <td class="column-date">
                <?php echo esc_html(wp_date(get_option('date_format'), strtotime($suggestion->createdAt))); ?>
            </td>
            <td class="column-actions">
                <?php if ($suggestion->status === 'pending'): ?>
                    <?php $this->renderActionButton($suggestion->id, 'accept', __('Accept', 'ai-seo-assistant'), 'primary small'); ?>
                <?php elseif ($suggestion->reviewedAt): ?>
                    <em><?php echo esc_html(wp_date(get_option('date_format'), strtotime($suggestion->reviewedAt))); ?></em>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function renderActionButton(int $id, string $action, string $label, string $class = 'secondary'): void
    {
        $url = wp_nonce_url(
            add_query_arg([
                'page' => 'aisa-suggestions',
                'aisa_action' => $action,
                'suggestion_id' => $id,
            ], admin_url('admin.php')),
            'aisa_suggestion_' . $action . '_' . $id
        );
        echo '<a href="' . esc_url($url) . '" class="button button-' . esc_attr($class) . '">' . esc_html($label) . '</a> ';
    }

    private function handleActions(): void
    {
        if (empty($_GET['aisa_action']) || empty($_GET['suggestion_id'])) {
            return;
        }

        $action = sanitize_text_field($_GET['aisa_action']);
        $id = absint($_GET['suggestion_id']);
        $nonceAction = 'aisa_suggestion_' . $action . '_' . $id;

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], $nonceAction)) {
            wp_die(__('Security check failed.', 'ai-seo-assistant'));
        }

        $suggestion = SuggestionRepository::findById($id);
        if (!$suggestion) {
            return;
        }

        $userId = get_current_user_id();

        switch ($action) {
            case 'accept':
                $this->applySuggestion($suggestion);
                SuggestionRepository::updateStatus($id, 'accepted', $userId);
                break;
            case 'reject':
                SuggestionRepository::updateStatus($id, 'rejected', $userId);
                break;
            case 'snooze':
                SuggestionRepository::snooze($id, 7);
                break;
        }

        wp_safe_redirect(remove_query_arg(['aisa_action', 'suggestion_id', '_wpnonce']));
        exit;
    }

    /**
     * Apply a suggestion by updating the post meta/SEO fields.
     */
    private function applySuggestion(Suggestion $suggestion): void
    {
        $seoAdapter = \AiSeoAssistant\Plugin::getInstance()->getSEOAdapter();

        switch ($suggestion->type) {
            case 'meta_title':
                $seoAdapter->setMetaTitle($suggestion->postId, $suggestion->suggestedValue);
                break;
            case 'meta_description':
                $seoAdapter->setMetaDescription($suggestion->postId, $suggestion->suggestedValue);
                break;
            case 'aeo_refresh':
                update_post_meta($suggestion->postId, '_aisa_aeo_tldr', $suggestion->suggestedValue);
                break;
        }
    }
}
