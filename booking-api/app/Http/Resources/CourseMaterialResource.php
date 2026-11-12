<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Come un materiale esce nelle risposte dell'API. Il percorso del file sul disco non si mostra mai.
 *
 * @mixin \App\Models\CourseMaterial
 */
class CourseMaterialResource extends JsonResource
{
    /**
     * I campi del materiale.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at,
        ];
    }
}
