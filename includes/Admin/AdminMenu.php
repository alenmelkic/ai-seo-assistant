<?php

namespace AiSeoAssistant\Admin;

class AdminMenu
{
    public function register(): void
    {
        add_options_page(
            __('AI SEO Assistant', 'ai-seo-assistant'),
            __('SEO AI Assistant', 'ai-seo-assistant'),
            'manage_aisa',
            'ai-seo-assistant',
            [new SettingsPage(), 'render']
        );

        // Phase 1.5: Suggestions Inbox
        $pendingCount = \AiSeoAssistant\Suggestions\SuggestionRepository::countPending();
        $menuTitle = __('SEO Suggestions', 'ai-seo-assistant');
        if ($pendingCount > 0) {
            $menuTitle .= ' <span class="awaiting-mod">' . $pendingCount . '</span>';
        }

        add_submenu_page(
            'edit.php',
            __('SEO Suggestions Inbox', 'ai-seo-assistant'),
            $menuTitle,
            'manage_aisa',
            'aisa-suggestions',
            [new SuggestionsInbox(), 'render']
        );
    }
}
