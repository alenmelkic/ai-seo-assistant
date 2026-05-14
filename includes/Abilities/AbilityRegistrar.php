<?php

namespace AiSeoAssistant\Abilities;

class AbilityRegistrar
{
    public function register(): void
    {
        if (!function_exists('register_ability')) {
            return;
        }

        $abilities = [
            new AnalyzeContentAbility(),
            new GenerateMetaAbility(),
            new GenerateTagsAbility(),
            new GenerateAEOAbility(),
        ];

        foreach ($abilities as $ability) {
            register_ability($ability->getName(), [
                'description' => $ability->getDescription(),
                'permission_callback' => [$ability, 'permissionCheck'],
                'input_schema' => $ability->getInputSchema(),
                'output_schema' => $ability->getOutputSchema(),
                'execute_callback' => [$ability, 'execute'],
            ]);
        }
    }
}
