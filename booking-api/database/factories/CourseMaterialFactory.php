<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Genera materiali finti per i test. Il file non esiste sul disco: i test che lo leggono lo creano con Storage::fake.
 *
 * @extends Factory<CourseMaterial>
 */
class CourseMaterialFactory extends Factory
{
    /** I valori di partenza di un materiale finto (si possono sovrascrivere nei test). */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->words(3, true),
            'file_path' => 'course-materials/'.fake()->uuid().'.pdf',
            'size_bytes' => 1024,
        ];
    }
}
