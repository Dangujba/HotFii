<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Jagorar Saita MikroTik RouterOS</h2>

<div class="alert alert-info">
HotFii yana iya shirya MikroTik RouterOS ta atomatik.
Idan router sabo ne, script na HotFii zai iya saita Hotspot,
DHCP, RADIUS, WireGuard, captive portal da monitoring.
</div>

<div class="guide-step">
<h3 class="h5">1. Abubuwan da ake bukata</h3>

<ul>
<li>MikroTik mai RouterOS 7 ko sama.</li>
<li>Izinin Administrator ta WinBox, WebFig ko Terminal.</li>
<li>Internet yana aiki a router.</li>
<li>An riga an kirkiri wannan MikroTik a HotFii.</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">2. Haɗa router</h3>

<p>A tsarin HotFii na router sabo:</p>

<ul>
<li><strong>ether1</strong> yawanci shi ne WAN/Internet.</li>
<li><strong>ether2</strong> yawanci shi ne LAN/Hotspot.</li>
</ul>

<p>
Idan router yana da HotSpot da yake aiki tun farko,
HotFii zai kiyaye shi maimakon ya goge shi.
</p>
</div>

<div class="guide-step">
<h3 class="h5">3. Tabbatar da Internet</h3>

<p>A MikroTik Terminal ka gudu:</p>

<pre><code>ping 1.1.1.1 count=4</code></pre>

<p>Kada a ci gaba sai internet yana aiki.</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Kwafi HotFii Provisioning Script</h3>

<p>
Komawa shafin wannan na’ura a HotFii sannan a nemo
<strong>RouterOS Provisioning Script</strong>.
</p>

<p>Danna <strong>Copy Script</strong>.</p>

<div class="alert alert-warning">
Wannan script na wannan router ne kawai.
Kada a yi amfani da shi a wani MikroTik daban.
</div>
</div>

<div class="guide-step">
<h3 class="h5">5. Gudanar da script</h3>

<ol>
<li>Bude WinBox.</li>
<li>Shiga MikroTik da izinin Administrator.</li>
<li>Bude <strong>New Terminal</strong>.</li>
<li>Manna script gaba daya sau daya.</li>
<li>Jira ya gama aiki.</li>
</ol>

<p>HotFii zai saita abubuwa kamar:</p>

<ul>
<li>WireGuard management tunnel</li>
<li>RADIUS authentication</li>
<li>RADIUS accounting</li>
<li>Disconnect / CoA</li>
<li>Captive portal</li>
<li>Heartbeat monitoring</li>
<li>HotSpot da DHCP idan ana bukata</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">6. Gwada WireGuard</h3>

<pre><code>ping 10.77.0.1 count=4</code></pre>

<p>
Idan ana samun reply, router yana iya kaiwa HotFii ta private management network.
</p>
</div>

<div class="guide-step">
<h3 class="h5">7. Haɗa wayar ko kwamfutar customer</h3>

<ol>
<li>Haɗa waya ko laptop da Hotspot.</li>
<li>Bude <code>http://neverssl.com</code>.</li>
<li>Browser ya kamata ya tura customer zuwa HotFii.</li>
<li>Shigar da voucher ko amfani da payment idan an kunna shi.</li>
<li>Danna <strong>Connect to Internet</strong>.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">8. Gudanar da readiness tests</h3>

<p>
A shafin na’urar a HotFii danna
<strong>Run readiness tests</strong>.
</p>

<p>Idan komai ya yi aiki za a ga:</p>

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
<h3 class="h5">Magance Matsala</h3>

<p><strong>Internet baya aiki:</strong> tabbatar ether1 yana da WAN IP da gateway.</p>

<p><strong>WireGuard baya aiki:</strong> tabbatar internet yana aiki sannan router zai iya kaiwa HotFii.</p>

<p><strong>Portal baya fitowa:</strong> tabbatar HotSpot yana aiki kuma customer ya samu LAN IP.</p>

<p><strong>RADIUS ya ki authentication:</strong> tabbatar WireGuard yana aiki kuma ba a canza RADIUS Secret da HotFii ya bayar ba.</p>

<p><strong>Router yana Offline:</strong> tabbatar HotFii heartbeat scheduler yana aiki a RouterOS.</p>
</div>

<div class="guide-step">
<h3 class="h5">Tsaro</h3>

<ul>
<li>Kada a wallafa provisioning script.</li>
<li>Kada a yi amfani da RADIUS Secret na wata na’ura.</li>
<li>A rika sabunta RouterOS.</li>
<li>A ba Administrator access ga amintattun ma’aikata kawai.</li>
</ul>
</div>

</div>
</div>
