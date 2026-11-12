<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un corso esce nelle risposte dell'API.
 *
 * Alcuni campi compaiono solo se la query li ha calcolati (vedi CourseRepository): il numero di iscritti, i posti
 * liberi e le informazioni sull'utente che guarda (è iscritto? è istruttore?).
 *
 * @mixin \App\Models\Course
 */
class CourseResource extends JsonResource
{
    /**
     * I campi del corso.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'duration_minutes' => $this->duration_minutes,
            'is_active' => $this->is_active,
            'bookable_id' => $this->bookable_id,
            'bookable' => $this->whenLoaded('bookable', fn () => new BookableResource($this->bookable)),
            'instructors' => InstructorResource::collection($this->whenLoaded('instructors')),
            'enrolled_count' => $this->whenHas('enrolled_count', fn ($count) => (int) $count),
            'spots_left' => $this->whenHas('enrolled_count', fn ($count) => max(0, $this->capacity - (int) $count)),
            'is_enrolled' => $this->whenHas('is_enrolled', fn ($value) => (bool) $value),
            'is_instructor' => $this->whenHas('is_instructor', fn ($value) => (bool) $value),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
