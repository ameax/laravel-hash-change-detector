<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Database\Factories;

use ameax\HashChangeDetector\Models\Publisher;
use ameax\HashChangeDetector\Publishers\LogPublisher;
use ameax\HashChangeDetector\Tests\TestModels\TestModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublisherFactory extends Factory
{
    protected $model = Publisher::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true).' Publisher',
            'model_type' => TestModel::class,
            'publisher_class' => LogPublisher::class,
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'config' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function withConfig(array $config): self
    {
        return $this->state(fn (array $attributes) => [
            'config' => $config,
        ]);
    }
}
