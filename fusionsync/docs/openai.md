# OpenAI integration

This project includes a small integration with an LLM to analyze code diffs and propose fixes.

Environment
- Add your API key to `.env` (or use the system):

```
OPENAI_API_KEY=your_api_key_here
OPENAI_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=60
```

Service
- The service is implemented at `app/Services/OpenAIService.php` and provides:
  - `analyzeDiff(string $diff): array` — returns parsed JSON or `['raw' => '<text>']`.
  - `detectInconsistencies(string $diff)` — alias for analyzeDiff.
  - `proposeAutomatedFixes(string $diff)` — returns suggestion patches when available.

Artisan command
- `php artisan openai:review-diff path/to/diff` — analyze a diff file and print JSON to stdout.
- You can also pipe a diff in:

```bash
git diff origin/main...HEAD | php artisan openai:review-diff
```

- Use `--write-patches` to save suggestion patches to `storage/app/openai-suggestions`.

Notes
- The current implementation uses Laravel's HTTP client to call the OpenAI Chat Completions endpoint.
- For testing the service, the HTTP client is faked in `tests/Unit/OpenAIServiceTest.php`.
