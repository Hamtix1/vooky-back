<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->file_url,
            'audio_url' => $this->audio_file_url,
            'description' => $this->description,
            'dia' => $this->dia,
            'category_id' => $this->category_id,
            'level_id' => $this->level_id,
            'subcategories' => $this->whenLoaded('subcategories', function() {
                return $this->subcategories->map(function($subcategory) {
                    return [
                        'id' => $subcategory->id,
                        'name' => $subcategory->name,
                        'description' => $subcategory->description,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
