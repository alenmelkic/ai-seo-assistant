<?php

namespace AiSeoAssistant\Suggestions;

class Suggestion
{
    public int $id;
    public int $postId;
    public string $type;
    public ?string $currentValue;
    public ?string $suggestedValue;
    public ?string $reason;
    public ?array $gscSnapshot;
    public string $status;
    public ?string $snoozedUntil;
    public string $createdAt;
    public ?string $reviewedAt;
    public ?int $reviewedBy;

    public static function fromRow(object $row): self
    {
        $s = new self();
        $s->id = (int) $row->id;
        $s->postId = (int) $row->post_id;
        $s->type = $row->type;
        $s->currentValue = $row->current_value;
        $s->suggestedValue = $row->suggested_value;
        $s->reason = $row->reason;
        $s->gscSnapshot = $row->gsc_snapshot ? json_decode($row->gsc_snapshot, true) : null;
        $s->status = $row->status;
        $s->snoozedUntil = $row->snoozed_until ?? null;
        $s->createdAt = $row->created_at;
        $s->reviewedAt = $row->reviewed_at;
        $s->reviewedBy = $row->reviewed_by ? (int) $row->reviewed_by : null;
        return $s;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->postId,
            'type' => $this->type,
            'current_value' => $this->currentValue,
            'suggested_value' => $this->suggestedValue,
            'reason' => $this->reason,
            'gsc_snapshot' => $this->gscSnapshot,
            'status' => $this->status,
            'snoozed_until' => $this->snoozedUntil,
            'created_at' => $this->createdAt,
            'reviewed_at' => $this->reviewedAt,
            'reviewed_by' => $this->reviewedBy,
        ];
    }
}
