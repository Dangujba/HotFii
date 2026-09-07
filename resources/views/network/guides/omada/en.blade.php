<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">TP-Link Omada Setup</h2>

<div class="alert alert-info">
HotFii integrates with TP-Link Omada using
<strong>RADIUS Server authentication</strong>,
<strong>External Web Portal</strong>,
<strong>RADIUS Accounting</strong> and
<strong>Disconnect Requests</strong>.
No MikroTik-style provisioning script is required.
</div>

<div class="guide-step">
<h3 class="h5">1. Requirements</h3>

<ul>
<li>Omada Software, Hardware or compatible Cloud Controller.</li>
<li>An Omada Site.</li>
<li>Working internet connection.</li>
<li>The public IPv4 address from which the Omada network reaches HotFii RADIUS.</li>
<li>An adopted compatible Omada EAP/Gateway for a complete real-client test.</li>
</ul>

<div class="alert alert-warning">
The controller can be configured without physical Omada equipment,
but RADIUS authentication, captive portal, accounting, session tracking
and disconnect cannot be fully proven until compatible equipment and a
real client generate traffic.
</div>
</div>

<div class="guide-step">
<h3 class="h5">2. Prepare the Omada Site and customer network</h3>

<p>
Open Omada Controller and select or create the required Site.
Create the SSID/network that customers will use.
</p>

<p>
When hardware is available, adopt the required EAP and/or Gateway and
confirm ordinary network connectivity before enabling Portal authentication.
</p>
</div>

<div class="guide-step">
<h3 class="h5">3. Create the HotFii RADIUS Profile</h3>

<p>In Omada Controller go to:</p>

<pre><code>Network Config → Profile → RADIUS Profile → Create New RADIUS Profile</code></pre>

<table class="table">
<tbody>
<tr><th>Name</th><td><code>HotFii Radius</code></td></tr>
<tr><th>VLAN Assignment</th><td>Off unless specifically required</td></tr>
<tr><th>Require Message-Authenticator</th><td><strong>Off</strong></td></tr>
<tr><th>Authentication Server</th><td><code>{{ $radiusHost }}</code></td></tr>
<tr><th>RadSec</th><td><strong>Off</strong></td></tr>
<tr><th>Authentication Port</th><td><code>{{ $authPort }}</code></td></tr>
<tr><th>Authentication Password</th><td>Use this device's HotFii RADIUS secret.</td></tr>
<tr><th>RADIUS Accounting</th><td><strong>Enable</strong></td></tr>
<tr><th>Accounting Server</th><td><code>{{ $radiusHost }}</code></td></tr>
<tr><th>Accounting Port</th><td><code>{{ $accountingPort }}</code></td></tr>
<tr><th>Accounting Password</th><td>Use the same device-specific RADIUS secret.</td></tr>
<tr><th>Interim Accounting</th><td>Enable where available; 60 seconds is recommended for HotFii.</td></tr>
<tr><th>RADIUS Proxy</th><td>Off</td></tr>
<tr><th>RADIUS CoA in RADIUS Profile</th><td><strong>Off for the current HotFii Portal flow</strong></td></tr>
</tbody>
</table>

<div class="alert alert-warning">
For the HotFii PAP portal flow,
<strong>Require Message-Authenticator must remain Off</strong>.
Do not confuse the RADIUS Profile CoA switch with the
Portal's <strong>Disconnect Requests</strong> setting configured later.
</div>

<p>Save the RADIUS Profile.</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Configure Omada Controller Connection in HotFii</h3>

<p>
Open this device in HotFii and locate
<strong>Omada Controller Connection</strong>.
</p>

<ul>
<li><strong>RADIUS Source Public IP:</strong> the actual public IPv4 from which Omada sends RADIUS traffic to HotFii.</li>
<li><strong>Controller Portal Host:</strong> hostname/IP that the customer browser can reach.</li>
<li><strong>Scheme:</strong> HTTPS is recommended.</li>
<li><strong>Portal Port:</strong> normally <code>8843</code>.</li>
<li><strong>CoA / Disconnect Address:</strong> enter a reachable address when remote disconnect is being configured.</li>
</ul>

<p>On Windows, useful checks are:</p>

<pre><code>ipconfig
curl.exe https://api.ipify.org
Test-NetConnection &lt;controller-ip&gt; -Port 8843</code></pre>

<div class="alert alert-secondary">
<strong>Important:</strong>
<code>8043</code> is normally the Omada management interface,
while <code>8843</code> is the HTTPS Portal/browser-auth service.
Do not use <code>127.0.0.1</code> as the Controller Portal Host for
customer devices.
</div>

@if(filled($managementConfig['portal_host'] ?? null))
<p>Current HotFii browser-auth destination for this device:</p>

<pre><code>{{ ($managementConfig['portal_scheme'] ?? 'https') }}://{{ $managementConfig['portal_host'] }}:{{ $managementConfig['portal_port'] ?? 8843 }}/portal/radius/browserauth</code></pre>
@endif

<p>
Save the Omada settings in HotFii and run readiness tests.
At controller-only stage, <strong>Configuration</strong> may be the only passed check.
</p>
</div>

<div class="guide-step">
<h3 class="h5">5. Create the Omada Portal</h3>

<p>Go to:</p>

<pre><code>Network Config → Authentication → Portal → Add Portal</code></pre>

<p>On the <strong>Basic</strong> tab configure:</p>

<table class="table">
<tbody>
<tr><th>Portal Name</th><td><code>HotFii Portal</code></td></tr>
<tr><th>SSID &amp; Network</th><td>Select the customer SSID/network.</td></tr>
<tr><th>HTTPS Redirection</th><td><strong>Enable</strong></td></tr>
<tr><th>Landing Page</th><td>Original URL</td></tr>
</tbody>
</table>
</div>

<div class="guide-step">
<h3 class="h5">6. Configure Portal Authentication</h3>

<p>Open the <strong>Authentication</strong> tab.</p>

<table class="table">
<tbody>
<tr><th>Authentication Type</th><td><strong>RADIUS Server</strong></td></tr>
<tr><th>RADIUS Profile</th><td><code>HotFii Radius</code></td></tr>
<tr><th>NAS ID</th><td><code>{{ $device->nas_identifier }}</code></td></tr>
<tr><th>Disconnect Requests</th><td><strong>Enable</strong></td></tr>
<tr><th>Portal Logout</th><td>Enable</td></tr>
<tr><th>Authentication Mode</th><td><strong>PAP</strong></td></tr>
<tr><th>Portal Customization</th><td><strong>External Web Portal</strong></td></tr>
</tbody>
</table>

<div class="alert alert-danger">
Do not leave Omada's default NAS ID such as <code>TP-Link</code>.
Use exactly:
<code>{{ $device->nas_identifier }}</code>
</div>
</div>

<div class="guide-step">
<h3 class="h5">7. Configure the HotFii External Web Portal</h3>

<p>Use this device-specific URL:</p>

<pre><code>{{ $portalUrl }}</code></pre>

<p>
If Omada gives a separate protocol selector, choose
<strong>https://</strong> and enter the remaining hostname/path
without adding a second <code>https://</code>.
</p>

<div class="alert alert-warning">
Never use the portal URL from another HotFii router/controller.
Every HotFii device has its own device-specific URL.
</div>

<p>Apply/save the Portal configuration.</p>
</div>

<div class="guide-step">
<h3 class="h5">8. Configure Pre-Authentication Access</h3>

<p>
Unauthenticated customers must be able to reach HotFii before login.
Allow the HotFii domain in the appropriate pre-authentication/access list.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif

<p>
If online payment is enabled, also allow the domains required by
the configured payment provider.
</p>
</div>

<div class="guide-step">
<h3 class="h5">9. Understand Omada Browser Authentication</h3>

<p>
After a customer redeems a HotFii voucher, HotFii returns the
authentication context to the trusted Omada Controller Portal endpoint:
</p>

<pre><code>https://&lt;controller-host&gt;:8843/portal/radius/browserauth</code></pre>

<p>
HotFii preserves Omada parameters such as the customer MAC/IP,
AP/Gateway information, SSID, VLAN/radio information and original URL,
then posts the RADIUS username/password back to Omada.
</p>

<div class="alert alert-info">
<strong>Do not test this endpoint by opening it directly in a browser.</strong>
A manual visit may show <strong>400 Bad Request</strong>.
That is normal because <code>/portal/radius/browserauth</code>
expects an Omada-generated POST request with authentication context.
</div>

<p>The intended flow is:</p>

<pre><code>Customer
  ↓
Omada EAP/Gateway
  ↓
HotFii External Portal
  ↓
Voucher accepted
  ↓
POST to Omada :8843/portal/radius/browserauth
  ↓
Omada RADIUS Access-Request
  ↓
HotFii FreeRADIUS
  ↓
Access-Accept</code></pre>
</div>

<div class="guide-step">
<h3 class="h5">10. Test a customer</h3>

<ol>
<li>Connect a phone/laptop to the Omada customer SSID.</li>
<li>Open a webpage.</li>
<li>Omada should redirect the customer to HotFii.</li>
<li>Redeem a HotFii voucher.</li>
<li>Press <strong>Connect to Internet</strong>.</li>
<li>HotFii posts the authentication details back to Omada.</li>
<li>Omada authenticates against HotFii FreeRADIUS.</li>
<li>The customer should receive internet access.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">11. Accounting and Session Tracking</h3>

<p>After authentication, Omada should send:</p>

<pre><code>Accounting-Start
Interim-Update
Accounting-Stop</code></pre>

<p>HotFii uses these records to track:</p>

<ul>
<li>Customer MAC/IP</li>
<li>Session start and stop</li>
<li>Duration</li>
<li>Upload/download usage</li>
<li>Plan and credential</li>
<li>Live session status</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">12. Disconnect Testing</h3>

<p>
For the HotFii Portal flow, enable
<strong>Portal → Authentication → Disconnect Requests</strong>.
</p>

<p>
A live authenticated session is required before HotFii dashboard
Disconnect can be genuinely tested.
</p>

<p>
HotFii's configured Disconnect/CoA port is:
<code>{{ $coaPort }}</code>.
The controller/network must be reachable from HotFii for this test.
</p>
</div>

<div class="guide-step">
<h3 class="h5">13. Readiness Test Meanings</h3>

<table class="table">
<tbody>
<tr>
<th>Configuration</th>
<td>HotFii has the Omada RADIUS source IP, controller portal endpoint and NAS registration.</td>
</tr>
<tr>
<th>RADIUS Authentication</th>
<td>A real Omada RADIUS Access-Request has been successfully authenticated.</td>
</tr>
<tr>
<th>Accounting</th>
<td>HotFii has received Omada Accounting-Start/interim traffic.</td>
</tr>
<tr>
<th>Captive Portal</th>
<td>A real Omada customer redirect reached HotFii.</td>
</tr>
<tr>
<th>Session Tracking</th>
<td>Omada accounting has created/updated a HotFii session.</td>
</tr>
<tr>
<th>CoA / Disconnect</th>
<td>A live Omada session was successfully disconnected from HotFii.</td>
</tr>
</tbody>
</table>

<div class="alert alert-info">
Without compatible Omada EAP/Gateway hardware and a real client,
it is normal to see:
<strong>Configuration — Passed</strong>
while the remaining traffic-dependent checks remain
<strong>Pending</strong>.
</div>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">14. Troubleshooting</h3>

<p><strong>Unknown RADIUS client:</strong>
verify the RADIUS Source Public IP stored in HotFii is the actual
public source IP seen by HotFii.</p>

<p><strong>RADIUS authentication remains Pending:</strong>
no successful Omada Access-Request has reached HotFii yet.</p>

<p><strong>A valid voucher is rejected:</strong>
check the device-specific RADIUS secret,
<code>Require Message-Authenticator = Off</code>,
PAP mode and the exact HotFii NAS ID.</p>

<p><strong>Portal does not redirect:</strong>
verify the Portal is assigned to the correct SSID/network and that
the required Omada EAP/Gateway is adopted and online.</p>

<p><strong>Browser-auth shows Bad Request:</strong>
normal when the endpoint is manually opened.
Test it through the real captive-portal flow.</p>

<p><strong>Authentication works but no HotFii session appears:</strong>
verify RADIUS Accounting and UDP <code>{{ $accountingPort }}</code>.</p>

<p><strong>Disconnect fails:</strong>
verify Portal Disconnect Requests are enabled and HotFii can reach the
configured controller/CoA address.</p>

<p><strong>Public IP changed:</strong>
update the RADIUS Source Public IP in HotFii.
A managed tunnel/static addressing design is preferable for production
sites whose WAN address changes frequently.</p>
</div>

<div class="guide-step">
<h3 class="h5">Security</h3>

<ul>
<li>Use a unique RADIUS secret for every Omada installation.</li>
<li>Do not reuse another device's NAS ID or secret.</li>
<li>Prefer HTTPS.</li>
<li>Do not expose the Omada management interface unnecessarily.</li>
<li>Protect Controller administrator accounts.</li>
<li>Rotate a RADIUS secret immediately if it is exposed.</li>
</ul>
</div>

</div>
</div>
