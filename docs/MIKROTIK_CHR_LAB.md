# MikroTik CHR laboratory without physical hardware

CHR is the first certification laboratory because it runs RouterOS 7 with the same HotSpot, RADIUS, accounting, API, WireGuard, and CoA behavior used by physical MikroTik gateways.

## Virtual network

Create two virtual adapters on the CHR VM:

1. WAN: NAT or external switch so CHR can reach HotFii, FreeRADIUS, and Paystack test endpoints.
2. HOTSPOT-LAN: host-only or internal switch shared with a small customer test VM.

Do not bridge the hotspot test LAN directly into a home or office LAN. The test client should reach the Internet only through CHR.

Suggested addresses:

- CHR LAN: 10.20.0.1/24
- Test client: DHCP from CHR
- HotFii and FreeRADIUS: address reachable through WAN or optional WireGuard management

## Test sequence

1. Register a commerce or hybrid organization.
2. Create a location and add a MikroTik device.
3. Generate a 15-minute provisioning URL or copy the RouterOS script.
4. Paste the script into a RouterOS 7 terminal.
5. Configure a HotSpot server on HOTSPOT-LAN if CHR does not already have one.
6. Ensure the redirect supplies link_login, link_orig, mac, and ip to HotFii.
7. Run readiness tests.
8. Send a signed heartbeat.
9. Activate a sandbox voucher from the test client.
10. Confirm Access-Accept limits, radacct start and interim records, portal evidence, and live-session visibility.
11. Request disconnect and confirm Disconnect-ACK plus session termination.
12. Interrupt WAN access, restore it, and verify accounting and heartbeats recover without duplicate activations.

## Certification boundary

CHR proves RouterOS behavior but does not replace the physical pilot. Before MikroTik moves from beta to certified, repeat the suite on a physical RouterOS 7 router connected to a Starlink-backed hotspot and test power loss, link loss, captive-portal browsers, and sustained traffic.