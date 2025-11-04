<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Audio;

class AudioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Audio::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'description' => 'nullable|string'
        ]);

        $audio = Audio::create($request->all());

        return response()->json($audio, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Audio $audio)
    {
        return $audio;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Audio $audio)
    {
        $audio->update($request->all());

        return response()->json($audio);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Audio $audio)
    {
        $audio->delete();

        return response()->json(null, 204);
    }
}
