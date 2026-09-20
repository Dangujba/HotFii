<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">OpenWrt / Compatible Router Setup</h2>

<div class="alert alert-warning">
HotFii OpenWrt automated provisioning is currently being prepared.
Do not use this guide to flash unsupported router hardware.
</div>

<div class="guide-step">
<h3 class="h5">1. What counts as an OpenWrt-compatible router?</h3>

<p>
OpenWrt is router firmware, not a hardware manufacturer.
Routers from manufacturers such as GL.iNet, TP-Link, D-Link,
Linksys, Netgear, Xiaomi, Cudy and others may support OpenWrt.
</p>

<p>
Support depends on the exact <strong>model and hardware revision</strong>.
</p>
</div>

<div class="guide-step">
<h3 class="h5">2. Verify compatibility first</h3>

<p>
Before replacing factory firmware, verify the exact router model
and hardware revision against the official OpenWrt supported-device information.
</p>

<div class="alert alert-danger">
Never flash an OpenWrt image intended for a different model or hardware revision.
Doing so can make the router unusable.
</div>
</div>

<div class="guide-step">
<h3 class="h5">3. Factory firmware versus OpenWrt</h3>

<p>
A D-Link, TP-Link or other compatible router using its original
manufacturer firmware cannot use the HotFii OpenWrt setup directly.
</p>

<p>The normal path is:</p>

<pre><code>Compatible router
      ↓
Install supported OpenWrt firmware
      ↓
Confirm OpenWrt works
      ↓
Create OpenWrt device in HotFii
      ↓
Run HotFii OpenWrt provisioning</code></pre>
</div>

<div class="guide-step">
<h3 class="h5">4. Confirm internet access</h3>

<p>
Once OpenWrt is installed, verify WAN connectivity before running HotFii provisioning.
</p>
</div>

<div class="guide-step">
<h3 class="h5">5. HotFii provisioning</h3>

<p>
When the OpenWrt adapter is enabled, HotFii will provide a
device-specific provisioning procedure from this device page.
</p>

<p>The target HotFii integration will configure:</p>

<ul>
<li>Secure HotFii management connectivity</li>
<li>RADIUS authentication</li>
<li>RADIUS accounting</li>
<li>Captive portal</li>
<li>Voucher authentication</li>
<li>Session limits</li>
<li>Session monitoring</li>
<li>Remote disconnect</li>
<li>Heartbeat monitoring</li>
</ul>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">Important notes</h3>

<ul>
<li>Do not flash OpenWrt until exact hardware support is confirmed.</li>
<li>Backup the factory configuration/firmware information first.</li>
<li>Do not use another OpenWrt router's HotFii credentials.</li>
<li>OpenWrt installation procedures differ by manufacturer and model.</li>
</ul>
</div>

</div>
</div>
