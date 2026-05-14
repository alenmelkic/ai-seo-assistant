<?php

namespace AiSeoAssistant\Prompts;

abstract class AbstractPrompt implements PromptInterface
{
    public function render(array $variables = []): string
    {
        $template = PromptRegistry::loadTemplate($this->getAbilityName());

        $variables['language'] = $variables['language'] ?? PromptRegistry::buildLanguageBlock('auto');
        $variables['brand_voice'] = $variables['brand_voice'] ?? PromptRegistry::buildBrandVoiceBlock();
        $variables['gsc_context'] = $variables['gsc_context'] ?? '';

        return PromptRegistry::renderTemplate($template, $variables);
    }

    public function getSystemMessage(array $variables = []): string
    {
        $language = $variables['language'] ?? PromptRegistry::buildLanguageBlock('auto');

        $system = "You are an expert SEO and content optimization assistant. Always respond with valid JSON only, no markdown formatting around it.";

        if ($language === 'bs') {
            $system .= "\n\nIMPORTANT LANGUAGE RULES - BOSNIAN (ijekavica):\n"
                . "- Use Bosnian ijekavica EXCLUSIVELY (mlijeko, vrijeme, dijete, lijep, bijel)\n"
                . "- NEVER use Croatian-specific words: tjedan, kolovoz, siječanj, veljača, ožujak, travanj, svibanj, lipanj, srpanj, rujan, listopad, studeni, prosinac, tisuća, djelatnik, tvrtka, glazba, povijest, nogomet, ured\n"
                . "- USE Bosnian equivalents: sedmica, august, januar, februar, mart, april, maj, juni, juli, septembar, oktobar, novembar, decembar, hiljada, uposlenik, firma/preduzeće, muzika, historija, fudbal, kancelarija\n"
                . "- NEVER use Serbian ekavica: mleko, vreme, dete, lep, beo\n"
                . "- Use Latin script (NOT Cyrillic)\n"
                . "- Turkish-origin loanwords are acceptable where natural (komšija, džamija, čaršija)";
        }

        return $system;
    }
}
