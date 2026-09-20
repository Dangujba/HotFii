<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Jagorar Saita Ubiquiti UniFi</h2>

<div class="alert alert-info">
HotFii yana haɗuwa da UniFi ta
<strong>UniFi Site Manager / Network API</strong> da
<strong>External Portal</strong>.
Wannan UniFi integration ba ya amfani da HotFii RADIUS domin guest authorization.
</div>

<div class="guide-step">
<h3 class="h5">1. Abubuwan da ake bukata</h3>

<ul>
<li>UniFi Network / UniFi OS.</li>
<li>UniFi Site.</li>
<li>Administrator access.</li>
<li>UniFi Site Manager API Key.</li>
<li>Adopted UniFi AP domin cikakken Captive Portal test.</li>
</ul>

<div class="alert alert-warning">
HotFii zai iya gwada Controller/API ba tare da AP ba.
Amma Captive Portal, Guest Authorization, Session Tracking da Disconnect
suna bukatar real adopted AP/client domin cikakken gwaji.
</div>
</div>

<div class="guide-step">
<h3 class="h5">2. Kirkiri Site Manager API Key</h3>

<p>
Shiga UniFi Site Manager sannan a kirkiri API Key
na account da yake da damar shiga Site da ake son amfani da shi.
</p>

<div class="alert alert-danger">
API Key kamar password ne.
Kada a tura shi ta chat, ticket ko public screenshot.
</div>
</div>

<div class="guide-step">
<h3 class="h5">3. Haɗa UniFi da HotFii</h3>

<ol>
<li>Bude wannan UniFi device a HotFii.</li>
<li>Nemo <strong>UniFi Cloud Connection</strong>.</li>
<li>Manna API Key.</li>
<li>Danna <strong>Discover Sites</strong>.</li>
<li>Danna <strong>Use this site</strong> ga Site da ya dace.</li>
</ol>

<p>
HotFii yana ajiye API configuration cikin tsaro sannan yana map
Site Manager Site ID zuwa UniFi Network API Site ID da ake bukata.
</p>

@if(filled($managementConfig['site_name'] ?? null))
<div class="alert alert-success">
Site da aka haɗa:
<strong>{{ $managementConfig['site_name'] }}</strong>
</div>
@endif
</div>

<div class="guide-step">
<h3 class="h5">4. Controller-only Verification</h3>

<p>
Bayan an zaɓi Site, a danna <strong>Run Readiness Tests</strong>.
Idan babu AP/client, ana iya ganin:
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
API Connection Passed yana nufin HotFii ya samu nasarar
tuntuɓar UniFi Network Site da aka zaɓa.
</p>
</div>

<div class="guide-step">
<h3 class="h5">5. Saita Customer Wi-Fi</h3>

<ol>
<li>A UniFi Network kirkiri ko zaɓi customer Wi-Fi/SSID.</li>
<li>Kunna Hotspot/Captive Portal.</li>
<li>Zaɓi External Portal option na version ɗin UniFi ɗinka.</li>
<li>Yi amfani da HotFii Portal URL na wannan device.</li>
</ol>

<pre><code>{{ $portalUrl }}</code></pre>

<div class="alert alert-warning">
Kada a yi amfani da Portal URL na wata HotFii device.
</div>
</div>

<div class="guide-step">
<h3 class="h5">6. Pre-Authentication Access</h3>

<p>
Kafin authentication, guest browser dole ya iya kaiwa HotFii.
A allow HotFii host a UniFi pre-authorization/walled-garden settings.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif

<p>
Idan online payment yana aiki, a allow payment domains da ake bukata.
</p>
</div>

<div class="guide-step">
<h3 class="h5">7. Yadda UniFi Guest Authorization ke aiki</h3>

<pre><code>Customer
  ↓
UniFi AP / Captive Portal
  ↓
HotFii External Portal
  ↓
Voucher accepted
  ↓
HotFii ya nemo client ta MAC
  ↓
UniFi Network API Guest Authorization
  ↓
Internet access</code></pre>

<p>
HotFii yana kirkirar UniFi-backed session sannan scheduler
yana synchronize status da usage daga UniFi Network API.
</p>
</div>

<div class="guide-step">
<h3 class="h5">8. Gwada Real Guest</h3>

<ol>
<li>Haɗa waya/laptop da UniFi customer Wi-Fi.</li>
<li>UniFi ya redirect browser zuwa HotFii.</li>
<li>Shigar da voucher.</li>
<li>Danna <strong>Connect to Internet</strong>.</li>
<li>HotFii ya nemo client a Site.</li>
<li>HotFii ya request guest authorization ta UniFi API.</li>
<li>Tabbatar internet yana aiki.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">9. Session Tracking da Disconnect</h3>

<p>
Wannan UniFi integration ba ya amfani da FreeRADIUS accounting session.
HotFii yana synchronize UniFi sessions daga Network API.
</p>

<p>
Idan live session yana nan, je
<strong>HotFii → Live Sessions</strong>
sannan a gwada <strong>Disconnect</strong>.
HotFii zai request guest unauthorization daga UniFi API.
</p>
</div>

<div class="guide-step">
<h3 class="h5">10. Ma'anar Readiness Tests</h3>

<table class="table">
<tbody>
<tr><th>Configuration</th><td>API Key, console da mapped UniFi Network Site sun samu configuration.</td></tr>
<tr><th>API Connection</th><td>HotFii ya samu nasarar tuntuɓar UniFi Network Site.</td></tr>
<tr><th>Captive Portal</th><td>Real UniFi guest redirect ya isa HotFii.</td></tr>
<tr><th>API Authorization</th><td>An authorize real guest ta UniFi API.</td></tr>
<tr><th>Session Tracking</th><td>HotFii ya synchronize UniFi guest session/usage.</td></tr>
<tr><th>Disconnect</th><td>An samu nasarar disconnect live UniFi guest.</td></tr>
</tbody>
</table>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">11. Magance Matsala</h3>

<p><strong>Discover Sites baya aiki:</strong>
tabbatar API Key da account permissions.</p>

<p><strong>Site ya bayyana amma API Connection ya fail:</strong>
sake zaɓar Site domin HotFii ya map Site Manager Site zuwa
UniFi Network Site.</p>

<p><strong>API Connection Passed amma Captive Portal Pending:</strong>
normal ne har sai real adopted AP/client ya yi redirect.</p>

<p><strong>Client not found:</strong>
tabbatar guest yana Site da aka zaɓa kuma MAC address da UniFi ya turo daidai ne.</p>

<p><strong>API Authorization Pending:</strong>
babu real HotFii guest da ya kammala UniFi authorization tukuna.</p>

<p><strong>Session Tracking Pending:</strong>
sai an samu real authorized UniFi guest session.</p>

<p><strong>Disconnect Pending:</strong>
sai an samu live session kafin a gwada disconnect.</p>
</div>

<div class="guide-step">
<h3 class="h5">Tsaro</h3>

<ul>
<li>Kada a raba Site Manager API Key.</li>
<li>Idan API Key ya fallasa, a rotate shi nan take.</li>
<li>A yi amfani da HTTPS.</li>
<li>A ba account permissions da HotFii ke bukata kawai.</li>
<li>Kada a nuna API Key a screenshot ko support message.</li>
</ul>
</div>

</div>
</div>
