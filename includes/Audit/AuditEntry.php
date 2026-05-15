<?php

namespace AiSeoAssistant\Audit;

class AuditEntry
{
    public function __construct(
        public readonly int $postId,
        public readonly int $userId,
        public readonly string $action,
        public readonly string $field,
        public readonly ?string $previousValue,
        public readonly ?string $newValue,
        public readonly ?string $aiSuggestion,
        public readonly string $source,
        public readonly ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'action' => $this->action,
            'field' => $this->field,
            'previous_value' => $this->previousValue,
            'new_value' => $this->newValue,
            'ai_suggestion' => $this->aiSuggestion,
            'source' => $this->source,
            'metadata' => $this->metadata ? wp_json_encode($this->metadata) : null,
            'created_at' => current_time('mysql'),
        ];
    }
}
