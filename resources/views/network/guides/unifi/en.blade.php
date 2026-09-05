<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Ubiquiti UniFi Setup</h2>

<div class="alert alert-info">
HotFii integrates with UniFi using the UniFi controller/API and External Portal.
No MikroTik-style provisioning script is required.
</div>

<div class="guide-step">
<h3 class="h5">1. Requirements</h3>

<ul>
<li>UniFi Network controller / UniFi OS environment.</li>
<li>A UniFi site.</li>
<li>At least one adopted UniFi access point for a real Wi-Fi test.</li>
<li>Administrator access to UniFi.</li>
<li>A UniFi Site Manager API key.</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">2. Generate a UniFi API key</h3>

<p>
Sign in to UniFi Site Manager and create an API key for the account
that can access the required site.
</p>

<div class="alert alert-warning">
Treat the API key like a password. Do not send it through WhatsApp,
email or public screenshots.
</div>
</div>

<div class="guide-step">
<h3 class="h5">3. Connect UniFi to HotFii</h3>

<ol>
<li>Open this UniFi device in HotFii.</li>
<li>Find <strong>UniFi Cloud Connection</strong>.</li>
<li>Paste the Site Manager API key.</li>
<li>Click <strong>Discover Sites</strong>.</li>
<li>Select the correct UniFi site.</li>
</ol>

<p>
HotFii stores the connection credentials encrypted.
</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Create or select the guest Wi-Fi</h3>

<p>Inside UniFi Network:</p>

<ol>
<li>Create or select the Wi-Fi/SSID used for paid or voucher access.</li>
<li>Enable Hotspot/Captive Portal functionality.</li>
<li>Select the External Portal Server option.</li>
</ol>

<p>Use this HotFii portal URL:</p>

<pre><code>{{ $portalUrl }}</code></pre>
</div>

<div class="guide-step">
<h3 class="h5">5. Pre-authentication access</h3>

<p>
Before authentication, the guest must be able to reach HotFii.
Allow the HotFii domain in the UniFi pre-authentication/walled-garden settings.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif

<p>
If online payments are enabled, allow any payment domains required by HotFii as well.
</p>
</div>

<div class="guide-step">
<h3 class="h5">6. Test a guest</h3>

<ol>
<li>Connect a phone to the UniFi guest Wi-Fi.</li>
<li>Allow UniFi to redirect the browser to HotFii.</li>
<li>Redeem a voucher.</li>
<li>Press <strong>Connect to Internet</strong>.</li>
<li>HotFii authorizes the guest using the UniFi API.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">7. Verify session control</h3>

<p>
After a guest connects, HotFii should create a UniFi-backed session.
Usage information will be synchronized from the controller.
</p>

<p>
Use the HotFii session dashboard to test
<strong>Disconnect</strong>.
</p>
</div>

<div class="guide-step">
<h3 class="h5">8. Readiness tests</h3>

<p>Run readiness tests from the HotFii device page.</p>

<p>During controller-only testing, some results may remain pending until a real AP/client session exists.</p>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">Troubleshooting</h3>

<p><strong>Site discovery fails:</strong> verify the API key and ensure the UniFi account can access the site.</p>

<p><strong>Portal does not open:</strong> verify External Portal is enabled on the guest network.</p>

<p><strong>HotFii cannot authorize the guest:</strong> check that the selected HotFii site matches the network containing the client.</p>

<p><strong>Client is not found:</strong> verify the client MAC supplied by the UniFi redirect.</p>
</div>

<div class="guide-step">
<h3 class="h5">Security</h3>

<ul>
<li>Keep the API key private.</li>
<li>Rotate the key immediately if exposed.</li>
<li>Use HTTPS for HotFii.</li>
<li>Give the UniFi account only the access required for the integration.</li>
</ul>
</div>

</div>
</div>
