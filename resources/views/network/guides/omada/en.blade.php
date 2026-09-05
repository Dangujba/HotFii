<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">TP-Link Omada Setup</h2>

<div class="alert alert-info">
HotFii integrates with Omada using External Portal,
RADIUS authentication, RADIUS accounting and Disconnect/CoA.
</div>

<div class="guide-step">
<h3 class="h5">1. Requirements</h3>

<ul>
<li>Omada Software, Hardware or Cloud Controller.</li>
<li>An Omada Site.</li>
<li>A supported Omada Gateway or EAP for real client testing.</li>
<li>Working internet connection.</li>
<li>The public IPv4 address from which Omada reaches HotFii RADIUS.</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">2. Adopt the Omada equipment</h3>

<p>
Inside Omada Controller create/select the required site and adopt
the gateway and/or EAP devices.
</p>

<p>
Verify that the network works normally before enabling the captive portal.
</p>
</div>

<div class="guide-step">
<h3 class="h5">3. Configure the HotFii Omada connection</h3>

<p>
On the HotFii device page open
<strong>Omada Controller Connection</strong>.
</p>

<p>Enter:</p>

<ul>
<li>The RADIUS source public IPv4 address.</li>
<li>The controller portal hostname/IP reachable by the customer browser.</li>
<li>HTTP or HTTPS.</li>
<li>The controller portal port.</li>
<li>The CoA/Disconnect address if different.</li>
</ul>

<p>
The common Omada HTTPS portal port is <code>8843</code>,
but use the port configured by your controller.
</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Configure RADIUS in Omada</h3>

<p>Create an External RADIUS profile using:</p>

<table class="table">
<tr><th>Server</th><td><code>{{ $radiusHost }}</code></td></tr>
<tr><th>Authentication Port</th><td><code>{{ $authPort }}</code></td></tr>
<tr><th>Accounting Port</th><td><code>{{ $accountingPort }}</code></td></tr>
<tr><th>Secret</th><td>Use the device-specific secret shown above.</td></tr>
<tr><th>Authentication</th><td>PAP</td></tr>
</table>

<p>
Enable RADIUS accounting.
</p>
</div>

<div class="guide-step">
<h3 class="h5">5. Configure Disconnect / CoA</h3>

<p>
Enable RADIUS Disconnect Requests / Dynamic Authorization where available.
</p>

<p>HotFii uses:</p>

<pre><code>{{ $coaPort }}</code></pre>

<p>
The Omada controller must be reachable from HotFii on this port
for dashboard disconnect to work.
</p>
</div>

<div class="guide-step">
<h3 class="h5">6. Configure the External Portal</h3>

<p>
Create or edit the portal profile used by the customer network.
Select the External Portal Server option.
</p>

<p>Use:</p>

<pre><code>{{ $portalUrl }}</code></pre>

<p>
Associate the portal profile with the required SSID/network.
</p>
</div>

<div class="guide-step">
<h3 class="h5">7. Configure Pre-Authentication Access</h3>

<p>
Before login, allow customers to reach the HotFii domain.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif

<p>
If online payment is enabled, also allow the payment domains required
for the checkout process.
</p>
</div>

<div class="guide-step">
<h3 class="h5">8. Test a customer</h3>

<ol>
<li>Connect a phone/laptop to the Omada guest network.</li>
<li>Open a web page.</li>
<li>Omada should redirect to HotFii.</li>
<li>Redeem a voucher.</li>
<li>Press <strong>Connect to Internet</strong>.</li>
<li>HotFii submits the voucher credentials back to Omada.</li>
<li>Omada authenticates them against HotFii FreeRADIUS.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">9. Verify accounting and session tracking</h3>

<p>
After successful authentication, HotFii should receive Accounting Start
and later interim updates from Omada.
</p>

<p>
The HotFii session should show:
</p>

<ul>
<li>Customer MAC address</li>
<li>IP address</li>
<li>Start time</li>
<li>Data usage</li>
<li>Plan and voucher</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">10. Run readiness tests</h3>

<p>A complete test should eventually show:</p>

<ul>
<li>Configuration — Passed</li>
<li>RADIUS Authentication — Passed</li>
<li>Accounting — Passed</li>
<li>Captive Portal — Passed</li>
<li>Session Tracking — Passed</li>
<li>CoA / Disconnect — Passed</li>
</ul>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">Troubleshooting</h3>

<p><strong>Unknown RADIUS client:</strong> verify the RADIUS Source Public IP saved in HotFii is the actual source IP seen by the server.</p>

<p><strong>RADIUS rejects valid voucher:</strong> verify the device-specific secret exactly matches the Omada RADIUS profile.</p>

<p><strong>Portal does not redirect:</strong> verify the portal profile is assigned to the correct SSID/network.</p>

<p><strong>Authentication works but no session appears:</strong> verify RADIUS accounting is enabled.</p>

<p><strong>Disconnect fails:</strong> verify HotFii can reach the controller's Disconnect/CoA address and port.</p>
</div>

<div class="guide-step">
<h3 class="h5">Security</h3>

<ul>
<li>Use a unique RADIUS secret for every HotFii Omada installation.</li>
<li>Do not expose the Omada management interface unnecessarily.</li>
<li>Prefer HTTPS for the external portal.</li>
<li>Restrict controller administrator accounts.</li>
</ul>
</div>

</div>
</div>
