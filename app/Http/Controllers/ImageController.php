<?php

namespace App\Http\Controllers;
use App\Models\Image;
use App\Http\Requests\StoreImageRequest;
use App\Http\Requests\UpdateImageRequest;
use Illuminate\Support\Facades\Storage;


class ImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Image::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreImageRequest $request) {

        // Guardar imagen obligatoria
        $path = Storage::disk('public')->put('uploads', $request->file('url'));

        // Guardar audio obligatorio
        $audioPath = Storage::disk('public')->put('uploads', $request->file('audio_url'));

        $validatedData = $request->validated();
        $validatedData['url'] = $path;
        $validatedData['audio_url'] = $audioPath;

        $image = Image::create($validatedData);

        // Asociar subcategorías si se enviaron
        if ($request->has('subcategory_ids') && is_array($request->subcategory_ids)) {
            $image->subcategories()->sync($request->subcategory_ids);
        }

        return response()->json($image->load('subcategories'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Image $image)
    {
        return $image;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateImageRequest $request, Image $image)
    {
        $validatedData = $request->validated();

        // Si se sube una nueva imagen, eliminar la anterior y guardar la nueva
        if ($request->hasFile('url')) {
            // Eliminar imagen anterior del storage
            if ($image->url && Storage::disk('public')->exists($image->url)) {
                Storage::disk('public')->delete($image->url);
            }
            
            // Guardar nueva imagen
            $validatedData['url'] = Storage::disk('public')->put('uploads', $request->file('url'));
        } else {
            // No actualizar el campo url si no se envió un nuevo archivo
            unset($validatedData['url']);
        }

        // Si se sube un nuevo audio, eliminar el anterior y guardar el nuevo
        if ($request->hasFile('audio_url')) {
            // Eliminar audio anterior del storage
            if ($image->audio_url && Storage::disk('public')->exists($image->audio_url)) {
                Storage::disk('public')->delete($image->audio_url);
            }
            
            // Guardar nuevo audio
            $validatedData['audio_url'] = Storage::disk('public')->put('uploads', $request->file('audio_url'));
        } else {
            // No actualizar el campo audio_url si no se envió un nuevo archivo
            unset($validatedData['audio_url']);
        }

        $image->update($validatedData);

        // Actualizar subcategorías si se enviaron
        if ($request->has('subcategory_ids')) {
            $image->subcategories()->sync($request->subcategory_ids ?? []);
        }

        return response()->json($image->load('subcategories'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Image $image)
    {
        // Eliminar archivos del storage antes de eliminar el registro
        if ($image->url && Storage::disk('public')->exists($image->url)) {
            Storage::disk('public')->delete($image->url);
        }
        
        if ($image->audio_url && Storage::disk('public')->exists($image->audio_url)) {
            Storage::disk('public')->delete($image->audio_url);
        }

        // Eliminar el registro de la base de datos
        $image->delete();

        return response()->json(['message' => 'Imagen eliminada correctamente'], 200);
    }

}
