# Integration Patterns by Stack

These are starting shapes, not templates to paste verbatim -- adapt naming, error handling, and file placement to match what `codebase-detection.md` turned up in the actual project. Every example below wraps the raw Semaphore call in a small client class with real error handling, not the bare cURL/requests snippets from Semaphore's own docs (those are fine as a starting reference, not as production code -- they don't check `status`, don't handle non-2xx responses, and don't separate OTP from standard sends).

## PHP / Laravel

```php
// app/Services/Semaphore/SemaphoreClient.php
namespace App\Services\Semaphore;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SemaphoreClient
{
    public function __construct(
        private readonly string $apiKey = '',
        private readonly ?string $senderName = null,
    ) {
        $this->apiKey = $apiKey ?: config('services.semaphore.api_key');
        $this->senderName = $senderName ?? config('services.semaphore.sender_name');
    }

    public function sendSms(string $number, string $message): array
    {
        return $this->post('https://api.semaphore.co/api/v4/messages', $number, $message);
    }

    public function sendPriority(string $number, string $message): array
    {
        return $this->post('https://api.semaphore.co/api/v4/priority', $number, $message);
    }

    /** @return array{message_id:int,code:string} */
    public function sendOtp(string $number, string $template, ?string $customCode = null): array
    {
        $payload = [
            'apikey' => $this->apiKey,
            'number' => $number,
            'message' => $template, // should contain {otp}
            'sendername' => $this->senderName,
        ];
        if ($customCode !== null) {
            $payload['code'] = $customCode;
        }

        $response = Http::asForm()->post('https://api.semaphore.co/api/v4/otp', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Semaphore OTP send failed: {$response->status()} {$response->body()}");
        }

        $body = $response->json();
        $entry = $body[0] ?? $body;

        if (($entry['status'] ?? null) === 'Failed') {
            throw new RuntimeException("Semaphore reported the OTP send as Failed for {$number}");
        }

        // Do not log $entry['code'] -- it's the live OTP value.
        return $entry;
    }

    private function post(string $url, string $number, string $message): array
    {
        $response = Http::asForm()->post($url, [
            'apikey' => $this->apiKey,
            'number' => $number,
            'message' => $message,
            'sendername' => $this->senderName,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Semaphore send failed: {$response->status()} {$response->body()}");
        }

        return $response->json();
    }
}
```

```php
// config/services.php
'semaphore' => [
    'api_key' => env('SEMAPHORE_API_KEY'),
    'sender_name' => env('SEMAPHORE_SENDERNAME'),
],
```

If the app already has a Laravel Notification setup, prefer wiring this as a custom channel (`SemaphoreChannel implements` the notification-channel contract, calling `SemaphoreClient` internally) so it composes with `Notification::send()` like the app's other channels.

Verification/OTP comparison belongs in application code, not the client: store a hash of the code + an expiry (e.g. `Cache::put("otp:{$userId}", Hash::make($code), now()->addMinutes(5))`), and rate-limit verification attempts against brute force.

## Node.js

```js
// src/services/semaphoreClient.js
const axios = require('axios');

class SemaphoreClient {
  constructor({ apiKey = process.env.SEMAPHORE_API_KEY, senderName = process.env.SEMAPHORE_SENDERNAME } = {}) {
    if (!apiKey) throw new Error('SEMAPHORE_API_KEY is not set');
    this.apiKey = apiKey;
    this.senderName = senderName;
  }

  async sendSms(number, message) {
    return this._post('https://api.semaphore.co/api/v4/messages', { number, message });
  }

  async sendOtp(number, template, customCode) {
    const payload = { number, message: template }; // template should contain {otp}
    if (customCode) payload.code = customCode;
    const [entry] = await this._post('https://api.semaphore.co/api/v4/otp', payload);
    if (entry.status === 'Failed') {
      throw new Error(`Semaphore reported OTP send as Failed for ${number}`);
    }
    // Do not console.log/entry.code anywhere here.
    return entry;
  }

  async _post(url, params) {
    try {
      const { data } = await axios.post(url, new URLSearchParams({
        apikey: this.apiKey,
        sendername: this.senderName,
        ...params,
      }));
      return data;
    } catch (err) {
      if (err.response) {
        throw new Error(`Semaphore request failed: ${err.response.status} ${JSON.stringify(err.response.data)}`);
      }
      throw err;
    }
  }
}

module.exports = { SemaphoreClient };
```

OTP verification: persist a hashed code + expiry server-side (e.g. Redis with a TTL matching the message's stated validity window), never trust a client-echoed "verified" flag, and rate-limit verify attempts per number/session.

## Python

```python
# services/semaphore_client.py
import os
import requests

class SemaphoreError(RuntimeError):
    pass

class SemaphoreClient:
    def __init__(self, api_key: str | None = None, sender_name: str | None = None):
        self.api_key = api_key or os.environ["SEMAPHORE_API_KEY"]
        self.sender_name = sender_name or os.environ.get("SEMAPHORE_SENDERNAME")

    def send_sms(self, number: str, message: str) -> list[dict]:
        return self._post("https://api.semaphore.co/api/v4/messages", number=number, message=message)

    def send_otp(self, number: str, template: str, code: str | None = None) -> dict:
        payload = {"number": number, "message": template}  # template should contain {otp}
        if code:
            payload["code"] = code
        entries = self._post("https://api.semaphore.co/api/v4/otp", **payload)
        entry = entries[0] if isinstance(entries, list) else entries
        if entry.get("status") == "Failed":
            raise SemaphoreError(f"Semaphore reported OTP send as Failed for {number}")
        # Do not log entry["code"] anywhere.
        return entry

    def _post(self, url: str, **params) -> list[dict] | dict:
        body = {"apikey": self.api_key, "sendername": self.sender_name, **params}
        resp = requests.post(url, data=body, timeout=10)
        if not resp.ok:
            raise SemaphoreError(f"Semaphore request failed: {resp.status_code} {resp.text}")
        return resp.json()
```

Verification: store a hash of the code with an expiry (Django cache framework, Redis, or a DB row with `expires_at`), rate-limit verification attempts.

## Ruby

```ruby
# app/services/semaphore_client.rb
class SemaphoreClient
  class SendFailed < StandardError; end

  def initialize(api_key: ENV.fetch('SEMAPHORE_API_KEY'), sender_name: ENV['SEMAPHORE_SENDERNAME'])
    @api_key = api_key
    @sender_name = sender_name
  end

  def send_sms(number:, message:)
    post('https://api.semaphore.co/api/v4/messages', number: number, message: message)
  end

  def send_otp(number:, template:, code: nil)
    payload = { number: number, message: template } # template should contain {otp}
    payload[:code] = code if code
    entries = post('https://api.semaphore.co/api/v4/otp', **payload)
    entry = entries.is_a?(Array) ? entries.first : entries
    raise SendFailed, "Semaphore reported OTP send as Failed for #{number}" if entry['status'] == 'Failed'
    # Do not log entry['code'] anywhere.
    entry
  end

  private

  def post(url, **params)
    response = HTTP.post(url, form: { apikey: @api_key, sendername: @sender_name, **params })
    raise SendFailed, "Semaphore request failed: #{response.status}" unless response.status.success?
    JSON.parse(response.body.to_s)
  end
end
```

## .NET (C#)

```csharp
public class SemaphoreClient
{
    private readonly HttpClient _http;
    private readonly string _apiKey;
    private readonly string? _senderName;

    public SemaphoreClient(HttpClient http, IOptions<SemaphoreOptions> options)
    {
        _http = http;
        _apiKey = options.Value.ApiKey;
        _senderName = options.Value.SenderName;
    }

    public async Task<JsonElement> SendOtpAsync(string number, string template, string? code = null)
    {
        var fields = new Dictionary<string, string>
        {
            ["apikey"] = _apiKey,
            ["number"] = number,
            ["message"] = template, // should contain {otp}
        };
        if (_senderName is not null) fields["sendername"] = _senderName;
        if (code is not null) fields["code"] = code;

        var response = await _http.PostAsync(
            "https://api.semaphore.co/api/v4/otp",
            new FormUrlEncodedContent(fields));

        if (!response.IsSuccessStatusCode)
        {
            var body = await response.Content.ReadAsStringAsync();
            throw new SemaphoreException($"Semaphore OTP send failed: {response.StatusCode} {body}");
        }

        var json = await response.Content.ReadFromJsonAsync<JsonElement>();
        var entry = json.ValueKind == JsonValueKind.Array ? json[0] : json;

        if (entry.TryGetProperty("status", out var status) && status.GetString() == "Failed")
        {
            throw new SemaphoreException($"Semaphore reported OTP send as Failed for {number}");
        }

        // Do not log entry.GetProperty("code") anywhere.
        return entry;
    }
}

// Program.cs
builder.Services.Configure<SemaphoreOptions>(builder.Configuration.GetSection("Semaphore"));
builder.Services.AddHttpClient<SemaphoreClient>();
```

## Cross-stack: bulk sending with chunking

Whatever the language, a bulk-send helper should chunk into groups of at most 1,000 numbers per call and pace calls to stay under 120/minute for the standard endpoint (no pacing needed for `/otp` and `/priority`, which are unrate-limited). Pseudocode:

```
chunks = split(recipient_numbers, size=1000)
for chunk in chunks:
    send(chunk.join(","), message)
    if using /api/v4/messages and chunks.length > 1:
        sleep appropriately to stay under 120 calls/minute
```
