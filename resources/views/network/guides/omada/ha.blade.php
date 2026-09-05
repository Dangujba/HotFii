<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Jagorar Saita TP-Link Omada</h2>

<div class="alert alert-info">
HotFii yana haɗuwa da Omada ta External Portal,
RADIUS Authentication, RADIUS Accounting da Disconnect/CoA.
</div>

<div class="guide-step">
<h3 class="h5">1. Abubuwan da ake bukata</h3>

<ul>
<li>Omada Software, Hardware ko Cloud Controller.</li>
<li>Omada Site.</li>
<li>Omada Gateway ko EAP domin cikakken gwaji.</li>
<li>Internet yana aiki.</li>
<li>Public IPv4 da Omada zai yi amfani da shi wajen tuntuɓar HotFii RADIUS.</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">2. Adopt Omada devices</h3>

<p>
A Omada Controller, kirkiri ko zaɓi Site sannan a adopt Gateway/EAP.
</p>

<p>
Tabbatar network yana aiki kafin a kunna captive portal.
</p>
</div>

<div class="guide-step">
<h3 class="h5">3. Saita Omada Connection a HotFii</h3>

<p>
A HotFii bude
<strong>Omada Controller Connection</strong>.
</p>

<p>Shigar da:</p>

<ul>
<li>RADIUS Source Public IPv4.</li>
<li>Controller portal hostname/IP da customer browser zai iya kaiwa.</li>
<li>HTTP ko HTTPS.</li>
<li>Portal port.</li>
<li>CoA/Disconnect IP idan ya bambanta.</li>
</ul>

<p>
Yawanci Omada HTTPS portal yana amfani da
<code>8843</code>, amma a yi amfani da port din controller ɗinka.
</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Saita RADIUS a Omada</h3>

<table class="table">
<tr><th>Server</th><td><code>{{ $radiusHost }}</code></td></tr>
<tr><th>Authentication Port</th><td><code>{{ $authPort }}</code></td></tr>
<tr><th>Accounting Port</th><td><code>{{ $accountingPort }}</code></td></tr>
<tr><th>Secret</th><td>Yi amfani da secret na wannan device da aka nuna a sama.</td></tr>
<tr><th>Authentication</th><td>PAP</td></tr>
</table>

<p>Kunna RADIUS Accounting.</p>
</div>

<div class="guide-step">
<h3 class="h5">5. Saita Disconnect / CoA</h3>

<p>
Kunna RADIUS Disconnect Requests ko Dynamic Authorization idan controller yana da shi.
</p>

<p>HotFii yana amfani da port:</p>

<pre><code>{{ $coaPort }}</code></pre>

<p>
HotFii server dole ya iya kaiwa Controller a wannan port domin Disconnect ya yi aiki.
</p>
</div>

<div class="guide-step">
<h3 class="h5">6. Saita External Portal</h3>

<p>
A Omada, kirkiri ko gyara Portal Profile sannan zaɓi External Portal Server.
</p>

<p>Yi amfani da:</p>

<pre><code>{{ $portalUrl }}</code></pre>

<p>
Haɗa Portal Profile da SSID/network da customers za su yi amfani da shi.
</p>
</div>

<div class="guide-step">
<h3 class="h5">7. Pre-Authentication Access</h3>

<p>
Kafin login, customer dole ya iya kaiwa HotFii domain.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif
</div>

<div class="guide-step">
<h3 class="h5">8. Gwada customer</h3>

<ol>
<li>Haɗa waya/laptop da Omada Guest Network.</li>
<li>Bude website.</li>
<li>Omada ya tura browser zuwa HotFii.</li>
<li>Shigar da voucher.</li>
<li>Danna <strong>Connect to Internet</strong>.</li>
<li>HotFii ya mayar da credentials zuwa Omada.</li>
<li>Omada ya tabbatar da su ta HotFii FreeRADIUS.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">9. Tabbatar da Accounting da Session Tracking</h3>

<p>
Bayan customer ya shiga, Omada ya kamata ya aika Accounting Start
da Interim Updates zuwa HotFii.
</p>

<p>HotFii Session zai nuna:</p>

<ul>
<li>MAC address</li>
<li>IP address</li>
<li>Lokacin farawa</li>
<li>Data usage</li>
<li>Plan/Voucher</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">10. Readiness Tests</h3>

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
<h3 class="h5">Magance Matsala</h3>

<p><strong>Unknown RADIUS client:</strong> tabbatar Public IP da aka saka a HotFii shi ne IP da server yake gani.</p>

<p><strong>RADIUS ya ki voucher:</strong> tabbatar RADIUS Secret na Omada da na HotFii iri daya ne.</p>

<p><strong>Portal baya fitowa:</strong> tabbatar Portal Profile yana hade da SSID da ya dace.</p>

<p><strong>Authentication yayi amma session baya fitowa:</strong> tabbatar RADIUS Accounting yana kunne.</p>

<p><strong>Disconnect baya aiki:</strong> tabbatar HotFii zai iya kaiwa Controller CoA IP/Port.</p>
</div>

<div class="guide-step">
<h3 class="h5">Tsaro</h3>

<ul>
<li>Kowace Omada installation ta sami nata RADIUS Secret.</li>
<li>Kada a bude Controller management ga jama'a ba tare da bukata ba.</li>
<li>A yi amfani da HTTPS.</li>
<li>A kare administrator accounts.</li>
</ul>
</div>

</div>
</div>
