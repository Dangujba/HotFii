#!/bin/sh
set -eu

FILE="${1:-/etc/freeradius/3.0/sites-available/default}"
TMP="${FILE}.hotfii.tmp"

awk '
BEGIN {
    added_post_auth = 0
    added_reject = 0
}

# Add Message-Authenticator to normal Access-Accept replies.
$0 ~ /^[[:space:]]*post-auth[[:space:]]*\{/ && added_post_auth == 0 {
    print
    print "        # HotFii: RouterOS validates Message-Authenticator in RADIUS replies."
    print "        update reply {"
    print "                Message-Authenticator := 0x00"
    print "        }"
    added_post_auth = 1
    next
}

# attr_filter may remove attributes from Access-Reject, so add it again afterwards.
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
    if (added_post_auth == 0 || added_reject == 0) {
        exit 42
    }
}
' "$FILE" > "$TMP"

mv "$TMP" "$FILE"
