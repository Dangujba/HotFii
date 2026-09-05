<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Jagorar Saita Ubiquiti UniFi</h2>

<div class="alert alert-info">
HotFii yana haɗuwa da UniFi ta UniFi Controller/API da External Portal.
Ba a bukatar MikroTik-style provisioning script.
</div>

<div class="guide-step">
<h3 class="h5">1. Abubuwan da ake bukata</h3>

<ul>
<li>UniFi Network Controller ko UniFi OS.</li>
<li>UniFi Site.</li>
<li>UniFi Access Point domin cikakken gwajin Wi-Fi.</li>
<li>Administrator access.</li>
<li>UniFi Site Manager API Key.</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">2. Kirkiri UniFi API Key</h3>

<p>
Shiga UniFi Site Manager sannan a kirkiri API Key
na account da yake da damar shiga Site da ake son amfani da shi.
</p>

<div class="alert alert-warning">
API Key kamar password ne. Kada a tura shi ta WhatsApp,
email ko a nuna shi a public screenshot.
</div>
</div>

<div class="guide-step">
<h3 class="h5">3. Haɗa UniFi da HotFii</h3>

<ol>
<li>Bude wannan UniFi device a HotFii.</li>
<li>Nemo <strong>UniFi Cloud Connection</strong>.</li>
<li>Manna API Key.</li>
<li>Danna <strong>Discover Sites</strong>.</li>
<li>Zaɓi Site da ya dace.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">4. Saita Guest Wi-Fi</h3>

<p>A UniFi Network:</p>

<ol>
<li>Kirkiri ko zaɓi Wi-Fi/SSID na customers.</li>
<li>Kunna Hotspot/Captive Portal.</li>
<li>Zaɓi External Portal Server.</li>
</ol>

<p>Yi amfani da wannan HotFii Portal URL:</p>

<pre><code>{{ $portalUrl }}</code></pre>
</div>

<div class="guide-step">
<h3 class="h5">5. Pre-authentication Access</h3>

<p>
Kafin customer ya shiga internet, dole browser dinsa ya iya kaiwa HotFii.
A saka HotFii domain a pre-authentication/walled-garden na UniFi.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif
</div>

<div class="guide-step">
<h3 class="h5">6. Gwada customer</h3>

<ol>
<li>Haɗa waya da UniFi Guest Wi-Fi.</li>
<li>UniFi ya tura browser zuwa HotFii.</li>
<li>Shigar da voucher.</li>
<li>Danna <strong>Connect to Internet</strong>.</li>
<li>HotFii zai ba customer damar shiga ta UniFi API.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">7. Gwada session</h3>

<p>
Bayan customer ya haɗu, HotFii ya kamata ya kirkiri session na UniFi.
</p>

<p>
A HotFii Sessions dashboard, gwada
<strong>Disconnect</strong>.
</p>
</div>

<div class="guide-step">
<h3 class="h5">8. Readiness Tests</h3>

<p>
Danna <strong>Run readiness tests</strong> a shafin UniFi device.
</p>

<p>
Wasu tests za su kasance Pending har sai an samu real AP da client session.
</p>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">Magance Matsala</h3>

<p><strong>Discover Sites ya ki aiki:</strong> tabbatar API Key da account permission.</p>

<p><strong>Portal baya fitowa:</strong> tabbatar External Portal yana kunne a Guest Wi-Fi.</p>

<p><strong>HotFii baya iya authorize customer:</strong> tabbatar Site da aka zaɓa shi ne wanda customer yake ciki.</p>

<p><strong>Client not found:</strong> tabbatar MAC address da UniFi ya turo.</p>
</div>

<div class="guide-step">
<h3 class="h5">Tsaro</h3>

<ul>
<li>Kada a raba API Key.</li>
<li>Idan ya fallasa, a canza shi nan take.</li>
<li>A yi amfani da HTTPS.</li>
<li>A ba UniFi account izinin da integration ke bukata kawai.</li>
</ul>
</div>

</div>
</div>
