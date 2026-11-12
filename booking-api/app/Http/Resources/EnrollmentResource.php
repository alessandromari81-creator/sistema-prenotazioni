<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un'iscrizione esce nelle risposte dell'API.
 *
 * @mixin \App\Models\Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * I campi dell'iscrizione; corso e studente compaiono solo se caricati.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'course' => $this->whenLoaded('course', fn () => new CourseResource($this->course)),
            'student' => $this->whenLoaded('user', fn () => new StudentResource($this->user)),
            'created_at' => $this->created_at,
        ];
    }
}
