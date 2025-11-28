<?php

namespace Lunnar\AuditLogging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lunnar\AuditLogging\Models\AuditLog;
use Lunnar\AuditLogging\Models\AuditLogSubject;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Lunnar\AuditLogging\Models\AuditLogSubject>
 */
class AuditLogSubjectFactory extends Factory
{
    protected $model = AuditLogSubject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_log_id' => AuditLog::factory(),
            'subject_type' => $this->faker->randomElement(['users', 'posts', 'comments', 'orders']),
            'subject_id' => $this->faker->uuid(),
            'role' => $this->faker->randomElement(['primary', 'parent', 'related', 'actor', 'target']),
        ];
    }

    /**
     * Set a specific role.
     */
    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Set a specific subject type and ID.
     */
    public function forSubject(string $type, string $id): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => $type,
            'subject_id' => $id,
        ]);
    }

    /**
     * Set the subject from a model instance.
     */
    public function forModel(object $model): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
        ]);
    }

    /**
     * Attach to an existing audit log.
     */
    public function forAuditLog(AuditLog $auditLog): static
    {
        return $this->state(fn (array $attributes) => [
            'audit_log_id' => $auditLog->id,
        ]);
    }

    /**
     * Create as primary subject.
     */
    public function primary(): static
    {
        return $this->withRole('primary');
    }

    /**
     * Create as parent subject.
     */
    public function parent(): static
    {
        return $this->withRole('parent');
    }

    /**
     * Create as related subject.
     */
    public function related(): static
    {
        return $this->withRole('related');
    }
}
