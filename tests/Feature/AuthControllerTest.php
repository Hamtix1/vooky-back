<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class AuthControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase; // Limpia la base de datos en cada prueba

    /** @test */
    public function puede_registrar_un_usuario_nuevo()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Hamilton',
            'email' => 'hamilton@example.com',
            'password' => '123456',
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Usuario registrado correctamente',
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'hamilton@example.com'
        ]);
    }

    /** @test */
    public function no_permite_registrar_un_usuario_existente()
    {
        User::factory()->create([
            'email' => 'hamilton@example.com'
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Hamilton 2',
            'email' => 'hamilton@example.com',
            'password' => '123456',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function valida_que_todos_los_campos_son_obligatorios()
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'email', 'password']);
    }
}
