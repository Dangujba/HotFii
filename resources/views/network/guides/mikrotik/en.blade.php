<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">MikroTik RouterOS Setup</h2>

<div class="alert alert-info">
HotFii provides automated provisioning for MikroTik RouterOS.
For a clean router, the generated script can configure the hotspot LAN,
DHCP, RADIUS, WireGuard, captive portal and HotFii monitoring.
</div>

<div class="guide-step">
<h3 class="h5">1. Requirements</h3>

<ul>
<li>MikroTik router running RouterOS 7 or newer.</li>
<li>Administrator access using WinBox, WebFig or Terminal.</li>
<li>Working internet connection.</li>
<li>This MikroTik device already created in HotFii.</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">2. Connect the router</h3>

<p>For a clean/default HotFii installation:</p>

<ul>
<li><strong>ether1</strong> is normally used for WAN/Internet.</li>
<li><strong>ether2</strong> is normally used for the Hotspot LAN.</li>
</ul>

<p>
If the router already has a working MikroTik HotSpot,
HotFii preserves the existing HotSpot instead of replacing it.
</p>
</div>

<div class="guide-step">
<h3 class="h5">3. Confirm internet access</h3>

<p>Open MikroTik Terminal and run:</p>

<pre><code>ping 1.1.1.1 count=4</code></pre>

<p>
Do not continue until the router can reach the internet.
</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Copy the HotFii provisioning script</h3>

<p>
Return to the HotFii device page and find
<strong>RouterOS Provisioning Script</strong>.
</p>

<p>Click <strong>Copy Script</strong>.</p>

<div class="alert alert-warning">
The script is unique to this router. Never paste the same script
into another MikroTik.
</div>
</div>

<div class="guide-step">
<h3 class="h5">5. Run the script</h3>

<ol>
<li>Open WinBox.</li>
<li>Connect to the MikroTik as administrator.</li>
<li>Open <strong>New Terminal</strong>.</li>
<li>Paste the complete HotFii script once.</li>
<li>Allow the script to finish.</li>
</ol>

<p>The script configures the required HotFii components including:</p>

<ul>
<li>WireGuard management tunnel</li>
<li>RADIUS authentication</li>
<li>RADIUS accounting</li>
<li>Disconnect/CoA support</li>
<li>HotFii captive portal</li>
<li>Heartbeat monitoring</li>
<li>HotSpot/DHCP bootstrap when required</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">6. Verify WireGuard</h3>

<p>On the MikroTik, test the HotFii management gateway:</p>

<pre><code>ping 10.77.0.1 count=4</code></pre>

<p>
Successful replies mean the private HotFii management tunnel is reachable.
</p>
</div>

<div class="guide-step">
<h3 class="h5">7. Connect a customer device</h3>

<ol>
<li>Connect a phone or laptop to the Hotspot network.</li>
<li>Open an HTTP website such as <code>http://neverssl.com</code>.</li>
<li>The browser should redirect to HotFii.</li>
<li>Enter a valid voucher or use an enabled online payment option.</li>
<li>Press <strong>Connect to Internet</strong>.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">8. Run readiness tests</h3>

<p>Return to the HotFii device page and click <strong>Run readiness tests</strong>.</p>

<p>A fully tested MikroTik should eventually report:</p>

<ul>
<li>Configuration — Passed</li>
<li>Heartbeat — Passed</li>
<li>RADIUS authentication — Passed</li>
<li>Accounting — Passed</li>
<li>Captive portal — Passed</li>
<li>CoA / Disconnect — Passed</li>
</ul>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">Troubleshooting</h3>

<p><strong>No internet:</strong> confirm ether1 has a valid WAN address and gateway.</p>

<p><strong>WireGuard unavailable:</strong> verify internet connectivity and UDP access to the HotFii WireGuard server.</p>

<p><strong>Portal does not appear:</strong> confirm the HotSpot is running and the customer received a LAN IP address.</p>

<p><strong>RADIUS authentication fails:</strong> verify the WireGuard tunnel and do not manually replace the HotFii-generated RADIUS secret.</p>

<p><strong>Device shows Offline:</strong> confirm the HotFii heartbeat script is still scheduled on RouterOS.</p>
</div>

<div class="guide-step">
<h3 class="h5">Security</h3>

<ul>
<li>Never publish the provisioning script.</li>
<li>Never reuse another router's RADIUS secret.</li>
<li>Keep RouterOS updated.</li>
<li>Restrict administrative access to trusted staff.</li>
<li>Use a trusted HTTPS certificate for production captive-portal deployments where applicable.</li>
</ul>
</div>

</div>
</div>
