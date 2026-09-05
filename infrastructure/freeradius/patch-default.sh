#!/bin/sh
set -eu

FILE="${1:-/etc/freeradius/3.0/sites-available/default}"
TMP="${FILE}.hotfii.tmp"

awk '
BEGIN {
    added_preacct = 0
    added_post_auth = 0
    added_reject = 0
}

# Normalize accounting NAS-IP-Address to the actual RADIUS packet source.
#
# MikroTik:
#   Packet-Src-IP-Address = its HotFii WireGuard IP (10.77.0.x)
#
# Omada:
#   Packet-Src-IP-Address = the public RADIUS source IP registered in HotFii
#
# This keeps radacct.nasipaddress aligned with HotFii nas.nasname.
$0 ~ /^[[:space:]]*preacct[[:space:]]*\{/ && added_preacct == 0 {
    print
    print "        # HotFii: identify accounting by the actual registered RADIUS source."
    print "        update request {"
    print "                NAS-IP-Address := \"%{Packet-Src-IP-Address}\""
    print "        }"
    added_preacct = 1
    next
}

# MikroTik validates Message-Authenticator in RADIUS replies.
$0 ~ /^[[:space:]]*post-auth[[:space:]]*\{/ && added_post_auth == 0 {
    print
    print "        # HotFii: RouterOS validates Message-Authenticator in RADIUS replies."
    print "        update reply {"
    print "                Message-Authenticator := 0x00"
    print "        }"
    added_post_auth = 1
    next
}

# attr_filter may remove attributes from Access-Reject.
$0 ~ /^[[:space:]]*attr_filter\.access_reject[[:space:]]*$/ && added_reject == 0 {
    print
    print ""
    print "                # HotFii: restore Message-Authenticator after reject filtering."
    print "                update reply {"
    print "                        Message-Authenticator := 0x00"
    print "                }"
    added_reject = 1
    next
}

{
    print
}

END {
    if (added_preacct == 0 || added_post_auth == 0 || added_reject == 0) {
        exit 42
    }
}
' "$FILE" > "$TMP"

mv "$TMP" "$FILE"
