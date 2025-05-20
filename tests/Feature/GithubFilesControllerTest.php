<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GithubFilesControllerTest extends TestCase
{
    public function testGetGithubPageReturnsSuccess()
    {
        $response = $this->get('github');

        $response->assertStatus(200);
    }

    public function testPostInvalidGithubUrlReturnsError()
    {
        $response = $this->post('/github/download', [
            'github_link' => 'https://invalid-url.com/repo',
            'branch' => 'main'
        ]);

        $response->assertJson([
            'error' => 'An invalid repository link has been submitted!'
        ]);
    }
    
    public function testPostGithubRepoWithoutPhpFilesReturnsEmptyErrors()
    {
        Storage::fake('local');

        $response = $this->post('/github/download', [
            'github_link' => 'https://github.com/marcoaparaujo/padroes-projeto/tree/master',
            'branch' => 'main'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'problems' => []
        ]);
    }
    
    public function testPostGithubRepoWithPhpFilesReturnsSuccess()
    {
        Storage::fake('local');

        $response = $this->post('/github/download', [
            'github_link' => 'https://github.com/TheAlgorithms/PHP',
            'branch' => 'main'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'file',
            'problems' => [
                '*' => [
                    'line',
                    'category',
                    'problem'
                ]
            ]
        ]);
    }
}

