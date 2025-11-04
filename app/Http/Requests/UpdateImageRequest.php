<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Los archivos son opcionales en update (solo si se quieren cambiar)
            'url' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp',
            'audio_url' => 'nullable|file|mimes:mp3,wav,ogg',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'nullable|string',
            'level_id' => 'nullable|integer|exists:levels,id',
            'dia' => 'required|integer',
            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'integer|exists:subcategories,id'
        ];
    }
}
