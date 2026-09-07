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


# HotFii: install CoovaChilli RADIUS VSA dictionary.
# CoovaChilli enterprise number: 14559.
DICT="$(find /etc/freeradius -maxdepth 2 -type f -name dictionary | head -n 1)"

if [ -z "$DICT" ]; then
    DICT="/etc/freeradius/3.0/dictionary"
    mkdir -p "$(dirname "$DICT")"
    touch "$DICT"
fi

if ! grep -Rqs "CoovaChilli-Max-Total-Octets" \
    /usr/share/freeradius /etc/freeradius 2>/dev/null
then
    if ! grep -RqsE '^VENDOR[[:space:]]+CoovaChilli[[:space:]]+14559' \
        /usr/share/freeradius /etc/freeradius 2>/dev/null
    then
        cat >> "$DICT" <<'EOF'

VENDOR CoovaChilli 14559
EOF
    fi

    cat >> "$DICT" <<'EOF'
BEGIN-VENDOR CoovaChilli
ATTRIBUTE CoovaChilli-Max-Input-Octets      1 integer
ATTRIBUTE CoovaChilli-Max-Output-Octets     2 integer
ATTRIBUTE CoovaChilli-Max-Total-Octets      3 integer
ATTRIBUTE CoovaChilli-Bandwidth-Max-Up      4 integer
ATTRIBUTE CoovaChilli-Bandwidth-Max-Down    5 integer
ATTRIBUTE CoovaChilli-Config                6 string
ATTRIBUTE CoovaChilli-Lang                  7 string
ATTRIBUTE CoovaChilli-Version               8 string
END-VENDOR CoovaChilli
EOF
fi
