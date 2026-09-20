<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Ubiquiti UniFi Setup</h2>

<div class="alert alert-info">
HotFii integrates with UniFi using the
<strong>UniFi Site Manager / Network API</strong> and
<strong>External Portal</strong>.
UniFi guest authorization does not use the HotFii RADIUS flow.
</div>

<div class="guide-step">
<h3 class="h5">1. Requirements</h3>

<ul>
<li>UniFi Network / UniFi OS environment.</li>
<li>A UniFi Site.</li>
<li>Administrator access.</li>
<li>A UniFi Site Manager API key.</li>
<li>An adopted UniFi AP for complete captive-portal testing.</li>
</ul>

<div class="alert alert-warning">
HotFii can validate the controller/API connection without an AP.
A physical adopted AP/client is required to prove captive portal,
guest authorization, session tracking and disconnect end-to-end.
</div>
</div>

<div class="guide-step">
<h3 class="h5">2. Create a Site Manager API Key</h3>

<p>
Sign in to UniFi Site Manager and generate an API key for an account
that can access the intended UniFi Site.
</p>

<div class="alert alert-danger">
Treat the API key as a password.
Do not paste it into chats, tickets or public screenshots.
</div>
</div>

<div class="guide-step">
<h3 class="h5">3. Connect UniFi to HotFii</h3>

<ol>
<li>Open this UniFi device in HotFii.</li>
<li>Find <strong>UniFi Cloud Connection</strong>.</li>
<li>Paste the Site Manager API key.</li>
<li>Click <strong>Discover Sites</strong>.</li>
<li>Select <strong>Use this site</strong> for the correct Site.</li>
</ol>

<p>
HotFii securely stores the API configuration and automatically maps
the Site Manager Site identifier to the corresponding UniFi Network
API Site identifier.
</p>

@if(filled($managementConfig['site_name'] ?? null))
<div class="alert alert-success">
Connected Site:
<strong>{{ $managementConfig['site_name'] }}</strong>
</div>
@endif
</div>

<div class="guide-step">
<h3 class="h5">4. Controller-only verification</h3>

<p>
Run <strong>Readiness Tests</strong> after selecting the Site.
Without an AP/client, the expected result is approximately:
</p>

<ul>
<li>Configuration — <strong>Passed</strong></li>
<li>API Connection — <strong>Passed</strong></li>
<li>Captive Portal — Pending</li>
<li>API Authorization — Pending</li>
<li>Session Tracking — Pending</li>
<li>Disconnect — Pending</li>
</ul>

<p>
A successful API connection proves that HotFii can communicate with
the selected UniFi Network Site.
</p>
</div>

<div class="guide-step">
<h3 class="h5">5. Create or select the customer Wi-Fi</h3>

<p>Inside UniFi Network:</p>

<ol>
<li>Create or select the Wi-Fi/SSID used for HotFii customers.</li>
<li>Enable Hotspot / Captive Portal functionality.</li>
<li>Select the External Portal option available in your UniFi Network version.</li>
<li>Use the device-specific HotFii Portal URL below.</li>
</ol>

<pre><code>{{ $portalUrl }}</code></pre>

<div class="alert alert-warning">
Never reuse the portal URL of another HotFii device.
</div>
</div>

<div class="guide-step">
<h3 class="h5">6. Configure Pre-Authentication Access</h3>

<p>
Before authentication, the guest must be able to reach HotFii.
Allow the HotFii host in UniFi's pre-authorization/walled-garden settings.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif

<p>
If online payments are enabled, also allow the payment domains
required by the configured checkout provider.
</p>
</div>

<div class="guide-step">
<h3 class="h5">7. How UniFi guest authorization works</h3>

<pre><code>Customer
  ↓
UniFi AP / Captive Portal
  ↓
HotFii External Portal
  ↓
Voucher accepted
  ↓
HotFii finds the UniFi client by MAC
  ↓
UniFi Network API guest authorization
  ↓
Internet access</code></pre>

<p>
HotFii uses the UniFi client identifier to create a UniFi-backed
HotFii session and synchronize its state/usage from the Network API.
</p>
</div>

<div class="guide-step">
<h3 class="h5">8. Test a real guest</h3>

<ol>
<li>Connect a phone/laptop to the UniFi customer Wi-Fi.</li>
<li>Allow UniFi to redirect the browser to HotFii.</li>
<li>Redeem a voucher.</li>
<li>Press <strong>Connect to Internet</strong>.</li>
<li>HotFii finds the client on the selected UniFi Site.</li>
<li>HotFii requests guest authorization through the UniFi Network API.</li>
<li>Verify internet access.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">9. Session Tracking and Disconnect</h3>

<p>
UniFi does not create a FreeRADIUS accounting session for this integration.
HotFii synchronizes active UniFi sessions through the Network API.
</p>

<p>
After a live guest session exists, open
<strong>HotFii → Live Sessions</strong> and test
<strong>Disconnect</strong>.
HotFii will request UniFi guest unauthorization through the API.
</p>
</div>

<div class="guide-step">
<h3 class="h5">10. Readiness Test Meanings</h3>

<table class="table">
<tbody>
<tr><th>Configuration</th><td>API key, console and mapped UniFi Network Site are stored.</td></tr>
<tr><th>API Connection</th><td>HotFii successfully reached the selected UniFi Network Site.</td></tr>
<tr><th>Captive Portal</th><td>A real UniFi guest redirect reached HotFii.</td></tr>
<tr><th>API Authorization</th><td>A real guest was authorized through the UniFi API.</td></tr>
<tr><th>Session Tracking</th><td>HotFii synchronized a UniFi guest session/usage.</td></tr>
<tr><th>Disconnect</th><td>A live UniFi guest was successfully disconnected.</td></tr>
</tbody>
</table>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">11. Troubleshooting</h3>

<p><strong>Discover Sites fails:</strong>
verify the API key and account permissions.</p>

<p><strong>Site is discovered but API Connection fails:</strong>
reselect the Site. HotFii must map the Site Manager Site to the
corresponding UniFi Network Site.</p>

<p><strong>API Connection passes but Captive Portal is Pending:</strong>
this is normal until an adopted AP and real guest generate a redirect.</p>

<p><strong>Client not found:</strong>
verify the guest is connected to the selected Site and that the
MAC supplied by the UniFi redirect is correct.</p>

<p><strong>Authorization Pending:</strong>
no real HotFii guest has completed UniFi API authorization yet.</p>

<p><strong>Session Tracking Pending:</strong>
a real authorized UniFi guest session must exist before synchronization
can be proven.</p>

<p><strong>Disconnect Pending:</strong>
a live session must first exist before disconnect can be tested.</p>
</div>

<div class="guide-step">
<h3 class="h5">Security</h3>

<ul>
<li>Keep Site Manager API keys private.</li>
<li>Rotate an API key immediately if exposed.</li>
<li>Use HTTPS for HotFii.</li>
<li>Use only the permissions required for HotFii integration.</li>
<li>Never publish API keys in screenshots or support messages.</li>
</ul>
</div>

</div>
</div>
