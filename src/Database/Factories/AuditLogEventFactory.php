<?php

namespace Lunnar\AuditLogging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lunnar\AuditLogging\Models\AuditLogEvent;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Lunnar\AuditLogging\Models\AuditLogEvent>
 */
class AuditLogEventFactory extends Factory
{
    protected $model = AuditLogEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = $this->faker->randomElement([
            'resource.created',
            'resource.updated',
            'resource.deleted',
            'user.login',
            'user.logout',
            'settings.changed',
        ]);

        return [
            'event' => $event,
            'level' => $this->faker->numberBetween(0, 100),
            'message_data' => $this->generateMessageData($event),
            'payload' => $this->generatePayload($event),
            'diff' => $this->faker->optional(0.5)->passthrough($this->generateDiff()),
            'actor_id' => $this->faker->uuid(),
            'reference_id' => $this->faker->uuid(),
            'checksum' => hash('sha256', $this->faker->uuid()),
        ];
    }

    /**
     * Generate message data based on event type.
     */
    protected function generateMessageData(string $event): array
    {
        return match (true) {
            str_ends_with($event, '.created') => ['name' => $this->faker->word()],
            str_ends_with($event, '.updated') => ['field' => $this->faker->word()],
            str_ends_with($event, '.deleted') => ['name' => $this->faker->word()],
            default => [],
        };
    }

    /**
     * Generate payload based on event type.
     */
    protected function generatePayload(string $event): ?array
    {
        if (str_ends_with($event, '.updated')) {
            return null;
        }

        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
        ];
    }

    /**
     * Generate a diff array.
     */
    protected function generateDiff(): array
    {
        $field = $this->faker->randomElement(['name', 'status', 'description']);

        return [
            'before' => [$field => $this->faker->word()],
            'after' => [$field => $this->faker->word()],
        ];
    }

    /**
     * Set a specific event.
     */
    public function withEvent(string $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => $event,
        ]);
    }

    /**
     * Set a specific actor ID.
     */
    public function withActor(string $actorId): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => $actorId,
        ]);
    }

    /**
     * Set a specific reference ID.
     */
    public function withReferenceId(string $referenceId): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Set specific payload.
     */
    public function withPayload(?array $payload): static
    {
        return $this->state(fn (array $attributes) => [
            'payload' => $payload,
        ]);
    }

    /**
     * Set specific diff.
     */
    public function withDiff(?array $diff): static
    {
        return $this->state(fn (array $attributes) => [
            'diff' => $diff,
        ]);
    }

    /**
     * Create without a checksum.
     */
    public function withoutChecksum(): static
    {
        return $this->state(fn (array $attributes) => [
            'checksum' => null,
        ]);
    }

    /**
     * Set a specific level.
     */
    public function withLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * Create a log with subjects attached.
     *
     * @param  array<array{type: string, id: string, role?: string}>  $subjects
     */
    public function withSubjects(array $subjects): static
    {
        return $this->afterCreating(function (AuditLogEvent $auditLog) use ($subjects) {
            foreach ($subjects as $subject) {
                $auditLog->subjects()->create([
                    'subject_type' => $subject['type'],
                    'subject_id' => $subject['id'],
                    'role' => $subject['role'] ?? 'primary',
                ]);
            }
        });
    }
}
