<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Ensure we have at least two users to work with
        $user = User::all()->random();

        $event = $this->faker->randomElement([
            'user.created',
            'user.updated',
            'user.deleted',
            'product.created',
            'product.updated',
            'product.deleted',
            'product_variant.created',
            'product_variant.updated',
            'product_variant.deleted',
        ]);

        $messageData = match ($event) {
            'user.created' => ['name' => $this->faker->name(), 'email' => $this->faker->email()],
            'user.updated' => ['field' => $this->faker->randomElement(['name', 'email', 'phone', 'address'])],
            'user.deleted' => ['name' => $this->faker->name()],
            'product.created' => ['name' => $this->faker->word()],
            'product.updated' => ['field' => $this->faker->randomElement(['name', 'description', 'price'])],
            'product.deleted' => ['name' => $this->faker->word()],
            'product_variant.created' => ['name' => $this->faker->word()],
            'product_variant.updated' => ['field' => $this->faker->randomElement(['name', 'sku', 'price'])],
            'product_variant.deleted' => ['name' => $this->faker->word()],
            default => [],
        };

        $diff = $this->faker->randomElement([
            null,
            [
                'before' => ['name' => $this->faker->word()],
                'after' => ['name' => $this->faker->word()],
            ],
            [
                'before' => ['email' => $this->faker->email()],
                'after' => ['email' => $this->faker->email()],
            ],
        ]);

        $payload = match ($event) {
            'user.created', 'user.deleted' => [
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
                'phone' => $this->faker->phoneNumber(),
                'language' => $this->faker->languageCode(),
            ],
            'product.created', 'product.deleted' => [
                'name' => $this->faker->word(),
                'description' => $this->faker->sentence(),
                'price' => $this->faker->randomFloat(2, 10, 1000),
            ],
            'product_variant.created', 'product_variant.deleted' => [
                'name' => $this->faker->word(),
                'sku' => $this->faker->uuid(),
                'price' => $this->faker->randomFloat(2, 10, 1000),
            ],
            default => null,
        };

        return [
            'event' => $event,
            'message_data' => $messageData,
            'payload' => $payload,
            'diff' => $diff,
            'metadata' => [
                'ip' => $this->faker->ipv4(),
                'ua' => $this->faker->userAgent(),
            ],
            'actor_id' => $user->id,
            'checksum' => hash('sha256', $this->faker->uuid()),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($auditLog) {
            $user = User::all()->random();
            $product = Product::all()->random();
            $productVariant = ProductVariant::all()->random();

            $subjects = [];

            // Add primary subject based on event type
            if (str_starts_with($auditLog->event, 'user.')) {
                $subjects[] = [
                    'audit_log_id' => $auditLog->id,
                    'subject_type' => 'users',
                    'subject_id' => (string) $user->id,
                    'role' => 'primary',
                ];
            } elseif (str_starts_with($auditLog->event, 'product_variant.')) {
                $subjects[] = [
                    'audit_log_id' => $auditLog->id,
                    'subject_type' => 'product_variants',
                    'subject_id' => (string) $productVariant->id,
                    'role' => 'primary',
                ];
                $subjects[] = [
                    'audit_log_id' => $auditLog->id,
                    'subject_type' => 'products',
                    'subject_id' => (string) $product->id,
                    'role' => 'parent',
                ];
            } elseif (str_starts_with($auditLog->event, 'product.')) {
                $subjects[] = [
                    'audit_log_id' => $auditLog->id,
                    'subject_type' => 'products',
                    'subject_id' => (string) $product->id,
                    'role' => 'primary',
                ];
            }

            // Create the audit log subjects
            foreach ($subjects as $subject) {
                $auditLog->subjects()->create($subject);
            }
        });
    }
}
