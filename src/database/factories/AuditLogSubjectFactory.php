<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAgreement;
use App\Models\PaymentCard;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLogSubject>
 */
class AuditLogSubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjectTypes = [
            'users' => User::class,
            'organizations' => Organization::class,
            'payments' => Payment::class,
            'payment_agreements' => PaymentAgreement::class,
            'payment_cards' => PaymentCard::class,
            'subscriptions' => Subscription::class,
            'products' => Product::class,
            'product_variants' => ProductVariant::class,
            'receipts' => Receipt::class,
        ];

        $subjectType = $this->faker->randomElement(array_keys($subjectTypes));
        $subjectModel = $subjectTypes[$subjectType];

        // Create a subject of the selected type
        // Use withoutEvents for receipts to prevent notification sending
        $subject = $subjectType === 'receipts'
            ? $subjectModel::withoutEvents(fn () => $subjectModel::factory()->create())
            : $subjectModel::factory()->create();

        $roles = ['primary', 'parent', 'related', 'actor', 'target'];

        return [
            'audit_log_id' => AuditLog::factory(),
            'subject_type' => $subjectType,
            'subject_id' => $subject->id,
            'role' => $this->faker->randomElement($roles),
        ];
    }

    /**
     * Create a subject with a specific role.
     */
    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Create a subject for a specific subject type.
     */
    public function forSubjectType(string $subjectType): static
    {
        $subjectTypes = [
            'users' => User::class,
            'organizations' => Organization::class,
            'payments' => Payment::class,
            'payment_agreements' => PaymentAgreement::class,
            'payment_cards' => PaymentCard::class,
            'subscriptions' => Subscription::class,
            'products' => Product::class,
            'product_variants' => ProductVariant::class,
            'receipts' => Receipt::class,
        ];

        if (! isset($subjectTypes[$subjectType])) {
            throw new \InvalidArgumentException("Invalid subject type: {$subjectType}");
        }

        $subjectModel = $subjectTypes[$subjectType];

        return $this->state(fn (array $attributes) => [
            'subject_type' => $subjectType,
            'subject_id' => $subjectModel::factory(),
        ]);
    }

    /**
     * Create a subject for an existing audit log.
     */
    public function forAuditLog(AuditLog $auditLog): static
    {
        return $this->state(fn (array $attributes) => [
            'audit_log_id' => $auditLog->id,
        ]);
    }

    /**
     * Create a subject for an existing model instance.
     */
    public function forSubject($subject): static
    {
        $subjectType = match (get_class($subject)) {
            User::class => 'users',
            Organization::class => 'organizations',
            Payment::class => 'payments',
            PaymentAgreement::class => 'payment_agreements',
            PaymentCard::class => 'payment_cards',
            Subscription::class => 'subscriptions',
            Product::class => 'products',
            ProductVariant::class => 'product_variants',
            Receipt::class => 'receipts',
            default => throw new \InvalidArgumentException('Unsupported subject type: '.get_class($subject)),
        };

        return $this->state(fn (array $attributes) => [
            'subject_type' => $subjectType,
            'subject_id' => $subject->id,
        ]);
    }
}
