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
    }
}
