<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Services\OpenAIService;

class OpenAIServiceTest extends TestCase
{
    public function test_analyze_diff_parses_json_response()
    {
        $fakeContent = json_encode([
            'issues' => [
                ['path' => 'app/Example.php', 'line' => 10, 'severity' => 'warning', 'message' => 'Example issue'],
            ],
            'suggestions' => [
                ['title' => 'Fix example', 'description' => 'Apply small fix', 'patch' => "---\n+++\n@@ -1 +1 @@\n-foo\n+bar\n"],
            ],
        ]);

        $fakeResponse = [
            'choices' => [
                ['message' => ['content' => $fakeContent]],
            ],
        ];

        Http::fake([
            '*' => Http::response($fakeResponse, 200),
        ]);

        $service = new OpenAIService();
        $result = $service->analyzeDiff("diff --git a/file b/file\n--- a/file\n+++ b/file\n");

        $this->assertIsArray($result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertCount(1, $result['issues']);
        $this->assertEquals('app/Example.php', $result['issues'][0]['path']);
    }
}
