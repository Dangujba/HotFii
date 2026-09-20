<div class="card mb-4">
<div class="card-body">

<h2 class="h4 mb-4">Jagorar Saita TP-Link Omada</h2>

<div class="alert alert-info">
HotFii yana haɗuwa da TP-Link Omada ta
<strong>RADIUS Server Authentication</strong>,
<strong>External Web Portal</strong>,
<strong>RADIUS Accounting</strong> da
<strong>Disconnect Requests</strong>.
Ba a bukatar MikroTik-style provisioning script.
</div>

<div class="guide-step">
<h3 class="h5">1. Abubuwan da ake bukata</h3>

<ul>
<li>Omada Software, Hardware ko compatible Cloud Controller.</li>
<li>Omada Site.</li>
<li>Internet mai aiki.</li>
<li>Public IPv4 da Omada network zai fito da shi zuwa HotFii RADIUS.</li>
<li>Compatible Omada EAP/Gateway domin cikakken real-client test.</li>
</ul>

<div class="alert alert-warning">
Za a iya saita Controller ba tare da physical Omada hardware ba.
Amma RADIUS Authentication, Captive Portal, Accounting,
Session Tracking da Disconnect ba za su samu cikakken gwaji ba
sai an samu EAP/Gateway da real customer traffic.
</div>
</div>

<div class="guide-step">
<h3 class="h5">2. Shirya Omada Site da Customer Network</h3>

<p>
Bude Omada Controller sannan a zaɓi ko a kirkiri Site.
A kirkiri SSID/network da customers za su yi amfani da shi.
</p>

<p>
Idan hardware yana nan, a adopt EAP/Gateway sannan a tabbatar
network yana aiki kafin a kunna Portal Authentication.
</p>
</div>

<div class="guide-step">
<h3 class="h5">3. Kirkiri HotFii RADIUS Profile</h3>

<p>A Omada Controller je zuwa:</p>

<pre><code>Network Config → Profile → RADIUS Profile → Create New RADIUS Profile</code></pre>

<table class="table">
<tbody>
<tr><th>Name</th><td><code>HotFii Radius</code></td></tr>
<tr><th>VLAN Assignment</th><td>Off sai idan akwai bukata ta musamman</td></tr>
<tr><th>Require Message-Authenticator</th><td><strong>Off</strong></td></tr>
<tr><th>Authentication Server</th><td><code>{{ $radiusHost }}</code></td></tr>
<tr><th>RadSec</th><td><strong>Off</strong></td></tr>
<tr><th>Authentication Port</th><td><code>{{ $authPort }}</code></td></tr>
<tr><th>Authentication Password</th><td>Yi amfani da RADIUS secret na wannan device.</td></tr>
<tr><th>RADIUS Accounting</th><td><strong>Enable</strong></td></tr>
<tr><th>Accounting Server</th><td><code>{{ $radiusHost }}</code></td></tr>
<tr><th>Accounting Port</th><td><code>{{ $accountingPort }}</code></td></tr>
<tr><th>Accounting Password</th><td>Yi amfani da wannan device-specific RADIUS secret.</td></tr>
<tr><th>Interim Accounting</th><td>Enable idan akwai; ana bada shawarar 60 seconds.</td></tr>
<tr><th>RADIUS Proxy</th><td>Off</td></tr>
<tr><th>RADIUS CoA a RADIUS Profile</th><td><strong>Off ga HotFii Portal flow na yanzu</strong></td></tr>
</tbody>
</table>

<div class="alert alert-warning">
Ga HotFii PAP Portal,
<strong>Require Message-Authenticator ya kasance Off</strong>.
Kada a rikita RADIUS Profile CoA da
<strong>Disconnect Requests</strong> na Portal Authentication.
</div>

<p>A Save RADIUS Profile.</p>
</div>

<div class="guide-step">
<h3 class="h5">4. Saita Omada Controller Connection a HotFii</h3>

<p>
Bude wannan device a HotFii sannan a nemo
<strong>Omada Controller Connection</strong>.
</p>

<ul>
<li><strong>RADIUS Source Public IP:</strong> Public IPv4 da Omada ke amfani da shi zuwa HotFii.</li>
<li><strong>Controller Portal Host:</strong> IP/hostname da customer browser zai iya kaiwa.</li>
<li><strong>Scheme:</strong> HTTPS ya fi dacewa.</li>
<li><strong>Portal Port:</strong> yawanci <code>8843</code>.</li>
<li><strong>CoA / Disconnect Address:</strong> saka reachable address idan ana saita remote disconnect.</li>
</ul>

<p>A Windows za a iya amfani da:</p>

<pre><code>ipconfig
curl.exe https://api.ipify.org
Test-NetConnection &lt;controller-ip&gt; -Port 8843</code></pre>

<div class="alert alert-secondary">
<strong>Muhimmi:</strong>
<code>8043</code> yawanci Omada management interface ne,
amma <code>8843</code> shi ne HTTPS Portal/browser-auth service.
Kada a yi amfani da <code>127.0.0.1</code> a matsayin Controller Portal Host
ga customer devices.
</div>

@if(filled($managementConfig['portal_host'] ?? null))
<p>Browser-auth destination da HotFii ya ajiye ga wannan device:</p>

<pre><code>{{ ($managementConfig['portal_scheme'] ?? 'https') }}://{{ $managementConfig['portal_host'] }}:{{ $managementConfig['portal_port'] ?? 8843 }}/portal/radius/browserauth</code></pre>
@endif
</div>

<div class="guide-step">
<h3 class="h5">5. Kirkiri Omada Portal</h3>

<p>Je zuwa:</p>

<pre><code>Network Config → Authentication → Portal → Add Portal</code></pre>

<p>A <strong>Basic</strong> tab:</p>

<table class="table">
<tbody>
<tr><th>Portal Name</th><td><code>HotFii Portal</code></td></tr>
<tr><th>SSID &amp; Network</th><td>Zaɓi customer SSID/network.</td></tr>
<tr><th>HTTPS Redirection</th><td><strong>Enable</strong></td></tr>
<tr><th>Landing Page</th><td>Original URL</td></tr>
</tbody>
</table>
</div>

<div class="guide-step">
<h3 class="h5">6. Saita Portal Authentication</h3>

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
Kada a bar NAS ID na Omada kamar <code>TP-Link</code>.
A yi amfani da:
<code>{{ $device->nas_identifier }}</code>
</div>
</div>

<div class="guide-step">
<h3 class="h5">7. Saita HotFii External Web Portal</h3>

<p>Yi amfani da URL na wannan device:</p>

<pre><code>{{ $portalUrl }}</code></pre>

<p>
Idan Omada yana da protocol selector daban,
zaɓi <strong>https://</strong> sannan a shigar da hostname/path
ba tare da sake rubuta <code>https://</code> ba.
</p>

<div class="alert alert-warning">
Kada a yi amfani da Portal URL na wani HotFii router/controller.
Kowane device yana da nasa URL.
</div>
</div>

<div class="guide-step">
<h3 class="h5">8. Pre-Authentication Access</h3>

<p>
Kafin login, customer browser dole ya iya kaiwa HotFii.
A allow HotFii domain a pre-authentication/access list.
</p>

@if(parse_url(config('app.url'), PHP_URL_HOST))
<pre><code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></pre>
@endif

<p>
Idan online payment yana aiki, a allow payment domains da tsarin ke bukata.
</p>
</div>

<div class="guide-step">
<h3 class="h5">9. Yadda Omada Browser Authentication ke aiki</h3>

<p>
Bayan voucher ya samu karbuwa, HotFii zai mayar da
authentication context zuwa trusted Omada endpoint:
</p>

<pre><code>https://&lt;controller-host&gt;:8843/portal/radius/browserauth</code></pre>

<p>
HotFii yana kiyaye client MAC/IP, AP/Gateway, SSID, VLAN/radio
da original URL sannan ya POST username/password zuwa Omada.
</p>

<div class="alert alert-info">
<strong>Kada a gwada wannan endpoint ta bude shi kai tsaye.</strong>
Idan aka bude da browser kawai zai iya nuna
<strong>400 Bad Request</strong>.
Wannan normal ne saboda endpoint din yana jiran POST daga Omada flow.
</div>

<pre><code>Customer
  ↓
Omada EAP/Gateway
  ↓
HotFii External Portal
  ↓
Voucher accepted
  ↓
POST zuwa Omada :8843/portal/radius/browserauth
  ↓
Omada RADIUS Access-Request
  ↓
HotFii FreeRADIUS
  ↓
Access-Accept</code></pre>
</div>

<div class="guide-step">
<h3 class="h5">10. Gwada Customer</h3>

<ol>
<li>Haɗa waya/laptop da customer SSID.</li>
<li>Bude webpage.</li>
<li>Omada ya redirect customer zuwa HotFii.</li>
<li>Shigar da HotFii voucher.</li>
<li>Danna <strong>Connect to Internet</strong>.</li>
<li>HotFii ya mayar da authentication zuwa Omada.</li>
<li>Omada ya tabbatar da credentials ta HotFii FreeRADIUS.</li>
<li>Customer ya samu internet.</li>
</ol>
</div>

<div class="guide-step">
<h3 class="h5">11. Accounting da Session Tracking</h3>

<pre><code>Accounting-Start
Interim-Update
Accounting-Stop</code></pre>

<p>HotFii zai yi amfani da records din wajen nuna:</p>

<ul>
<li>Customer MAC/IP</li>
<li>Session start/stop</li>
<li>Duration</li>
<li>Upload/download usage</li>
<li>Plan da credential</li>
<li>Live session status</li>
</ul>
</div>

<div class="guide-step">
<h3 class="h5">12. Gwajin Disconnect</h3>

<p>
A kunna
<strong>Portal → Authentication → Disconnect Requests</strong>.
</p>

<p>
Sai an samu live authenticated session kafin a iya tabbatar
da HotFii dashboard Disconnect.
</p>

<p>
HotFii Disconnect/CoA port:
<code>{{ $coaPort }}</code>.
</p>
</div>

<div class="guide-step">
<h3 class="h5">13. Ma'anar Readiness Tests</h3>

<table class="table">
<tbody>
<tr><th>Configuration</th><td>HotFii ya ajiye Omada RADIUS source IP, controller portal endpoint da NAS registration.</td></tr>
<tr><th>RADIUS Authentication</th><td>Omada ya aika successful Access-Request zuwa HotFii.</td></tr>
<tr><th>Accounting</th><td>HotFii ya karbi Accounting-Start/interim traffic.</td></tr>
<tr><th>Captive Portal</th><td>Real Omada customer redirect ya isa HotFii.</td></tr>
<tr><th>Session Tracking</th><td>Accounting ya kirkiri/updated HotFii session.</td></tr>
<tr><th>CoA / Disconnect</th><td>An samu nasarar disconnect live Omada session daga HotFii.</td></tr>
</tbody>
</table>

<div class="alert alert-info">
Idan babu compatible EAP/Gateway da real client,
normal ne <strong>Configuration — Passed</strong>
yayin da sauran tests suke <strong>Pending</strong>.
</div>
</div>

<div class="guide-step" id="troubleshooting">
<h3 class="h5">14. Magance Matsala</h3>

<p><strong>Unknown RADIUS client:</strong>
tabbatar RADIUS Source Public IP da aka saka a HotFii shi ne public IP
da HotFii server yake gani.</p>

<p><strong>RADIUS Authentication Pending:</strong>
babu successful Omada Access-Request da ya isa HotFii tukuna.</p>

<p><strong>Voucher yana rejected:</strong>
duba RADIUS Secret,
<code>Require Message-Authenticator = Off</code>,
PAP da exact HotFii NAS ID.</p>

<p><strong>Portal baya redirect:</strong>
tabbatar Portal yana hade da SSID/network da ya dace,
kuma EAP/Gateway yana adopted/online.</p>

<p><strong>Browser-auth yana nuna Bad Request:</strong>
normal ne idan an bude endpoint kai tsaye.</p>

<p><strong>Authentication yayi amma session baya fitowa:</strong>
tabbatar RADIUS Accounting da UDP <code>{{ $accountingPort }}</code>.</p>

<p><strong>Disconnect baya aiki:</strong>
tabbatar Disconnect Requests yana enabled kuma HotFii zai iya kaiwa
configured controller/CoA address.</p>

<p><strong>Public IP ya canza:</strong>
sabunta RADIUS Source Public IP a HotFii.</p>
</div>

<div class="guide-step">
<h3 class="h5">Tsaro</h3>

<ul>
<li>Kowane Omada installation ya sami unique RADIUS Secret.</li>
<li>Kada a sake amfani da NAS ID ko secret na wata na'ura.</li>
<li>A yi amfani da HTTPS.</li>
<li>Kada a bude Omada management interface ga jama'a ba tare da bukata ba.</li>
<li>A kare Controller administrator accounts.</li>
<li>Idan RADIUS Secret ya fallasa, a canza shi.</li>
</ul>
</div>

</div>
</div>
