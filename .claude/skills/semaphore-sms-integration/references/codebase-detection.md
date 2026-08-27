# Codebase Detection Checklist

Before writing a single line of Semaphore integration code, spend a few tool calls understanding the project. This is the difference between an integration that looks like it belongs, and one that looks bolted on.

## 1. Identify the stack

Look for the manifest file that names the ecosystem, and read it (not just its existence) to catch the framework, not just the language:

| File present | Ecosystem | Look inside for |
|---|---|---|
| `composer.json` | PHP | `laravel/framework` -> Laravel; `symfony/*` -> Symfony; otherwise plain PHP |
| `package.json` | Node.js | `next`, `express`, `nestjs`, `fastify` in dependencies |
| `requirements.txt` / `pyproject.toml` | Python | `django`, `flask`, `fastapi` |
| `*.csproj` / `*.sln` | .NET | ASP.NET Core vs. console/worker |
| `Gemfile` | Ruby | `rails` vs. plain Ruby / Sinatra |
| `go.mod` | Go | check for a web framework (`gin`, `echo`) or plain `net/http` |

## 2. Find the existing HTTP client convention

Grep for how the codebase already talks to third-party APIs (Stripe, Twilio, SendGrid, an internal API, anything). Whatever library is already a dependency and already used elsewhere should be reused:

- PHP/Laravel: `Http::` facade (Laravel's built-in HTTP client) is almost always preferred over introducing Guzzle directly if the app is Laravel 8+.
- Node: `axios`, native `fetch` (Node 18+), or a wrapped `got`/`node-fetch` instance.
- Python: `requests` vs `httpx` -- check `requirements.txt`/`pyproject.toml`, and whether the project is async (FastAPI, async Django) since that favors `httpx`.
- Ruby: `Faraday`, `HTTParty`, or Rails' built-in `Net::HTTP` wrapper.
- .NET: `HttpClient` via `IHttpClientFactory` is the idiomatic modern approach in ASP.NET Core -- check `Startup.cs`/`Program.cs` for existing `AddHttpClient` registrations to follow the same pattern.

Do not add a second HTTP library to satisfy one integration when the project already has one.

## 3. Find (or decide not to build) a notification abstraction

Search for existing directories/files like `Notifications/`, `Channels/`, `notifiers/`, `messaging/`, a `NotificationService`, or a mailer-style interface (`Mailer`, `EmailService`) that other channels (email, push, Slack) already implement. If one exists:

- Laravel: implement Semaphore as a custom **Notification Channel** (`app/Notifications/Channels/SemaphoreChannel.php`) so it plugs into `$notifiable->notify()` the same way Laravel's built-in `mail`/`database`/`broadcast` channels do.
- Generic OOP codebases: implement the same interface/base class the existing channels implement (e.g. a `send(string $to, string $body): SendResult` contract).

If no such abstraction exists and the task is narrow (e.g. "send an OTP on signup"), don't invent one -- a single `SemaphoreClient`/`SemaphoreService` class is enough. Building a generic multi-channel notification framework for a single SMS use case is over-engineering.

## 4. Find the config/env convention

- Check for an existing `.env`/`.env.example` and how other API keys are named there (`STRIPE_SECRET`, `TWILIO_AUTH_TOKEN`, etc) to match casing/prefix style with `SEMAPHORE_API_KEY` (or whatever the project's convention implies, e.g. `SEMAPHORE_SENDERNAME` for the default sender name).
- Laravel: add a `semaphore` block to `config/services.php` reading from `env('SEMAPHORE_API_KEY')`, rather than calling `env()` directly outside the `config/` directory (Laravel best practice, needed for config caching to work).
- Node: check whether the project uses `dotenv` + raw `process.env`, or a validated config module (`zod`/`envalid`/a `config.ts`) -- add the new key through the same validated path if one exists.
- Django: add to `settings.py` alongside other third-party keys, using whatever the project's pattern is for reading env vars (`os.environ`, `django-environ`, etc).
- .NET: add to `appsettings.json` (and `appsettings.Development.json` if that pattern is used) plus a strongly-typed options class if the project already uses the Options pattern for other integrations.

## 5. Find the existing test-mocking convention

Whatever the project already uses for mocking outbound HTTP in tests, use the same tool for the new Semaphore calls:

- Laravel: `Http::fake([...])`
- Node/Jest: `nock` or `jest.mock` on the HTTP client module
- Python: `responses` (requests) or `respx` (httpx)
- Ruby: `WebMock`/`VCR`
- .NET: a fake `HttpMessageHandler` or a mocked `IHttpClientFactory`

If the project has no test infrastructure at all, it's reasonable to skip adding tests unless asked -- but say so explicitly rather than silently omitting them.

## 6. Only after all of the above, write the code

The goal of this pass isn't ceremony -- it's making sure the Semaphore client that gets added reads like it was written by whoever wrote the rest of the codebase, using the libraries, naming, and structure that are already there.
