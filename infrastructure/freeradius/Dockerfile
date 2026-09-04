# HotFii FreeRADIUS.
#
# Debian's own packages rather than the upstream image: `freeradius-postgresql`
# is a documented package that installs rlm_sql_postgresql.so, whereas the
# source-built images vary in which SQL drivers were compiled in.
# Debian 12 ships FreeRADIUS 3.2 with the BlastRADIUS fixes; its configuration
# tree keeps the historical 3.0 directory name.
#
# Two changes to the stock configuration are made here rather than through
# bind-mounts, because mods-enabled is a directory of symlinks and mounting into
# it is fragile:
#
#   1. The SQL module instance is named exactly `sql`. The default virtual server
#      references it as a bare `-sql`, so an instance called anything else (the
#      native-install example uses `hotfii_sql`) is silently never called.
#
#   2. `sql` is commented out inside `session { }` upstream. Without it the
#      Simultaneous-Use attribute HotFii writes to radcheck is ignored, and one
#      voucher works on unlimited devices at once.

FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive \
    RADDB=/etc/freeradius/3.0

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        freeradius \
        freeradius-postgresql \
        freeradius-utils \
        gettext-base \
        netcat-openbsd \
        ca-certificates \
    ; \
    rm -rf /var/lib/apt/lists/*; \
    test -f /usr/lib/freeradius/rlm_sql_postgresql.so

# Rendered at start-up so the database password never lands in an image layer.
COPY sql.conf ${RADDB}/mods-available/sql.template
COPY clients.conf ${RADDB}/clients.conf.template
COPY hotfii-dynamic-clients ${RADDB}/sites-available/hotfii-dynamic-clients
COPY entrypoint.sh /usr/local/bin/hotfii-radius-entrypoint
COPY patch-default.sh /usr/local/bin/hotfii-radius-patch-default

RUN set -eux; \
    chmod +x /usr/local/bin/hotfii-radius-entrypoint /usr/local/bin/hotfii-radius-patch-default; \
    /usr/local/bin/hotfii-radius-patch-default "$RADDB/sites-available/default"; \
    \
    ln -sf ../mods-available/sql "$RADDB/mods-enabled/sql"; \
    ln -sf ../sites-available/hotfii-dynamic-clients "$RADDB/sites-enabled/hotfii-dynamic-clients"; \
    \
    # authorize, accounting and post-auth already reference the module as -sql,
    # which is a no-op until it is enabled. Assert that rather than assume it: if
    # a future Debian revision changes the layout, the build should fail here
    # instead of the deployment failing at 2am.
    grep -q '^[[:space:]]*-sql$' "$RADDB/sites-available/default"; \
    \
    # The session section is the one that has to be edited. The upstream line is
    # a commented bare `sql` with no leading dash.
    grep -q '^#[[:space:]]*sql$' "$RADDB/sites-available/default"; \
    sed -i 's/^#\([[:space:]]*sql\)$/\1/' "$RADDB/sites-available/default"; \
    \
    # HotFii uses PostgreSQL as the authoritative accounting store.
    # Disable the stock file-based detail accounting module.
    grep -A20 '^accounting {' "$RADDB/sites-available/default" | grep -q '^[[:space:]]*detail$'; \
    sed -i '/^accounting {/,/^}/{s/^[[:space:]]*detail$/    # detail  # HotFii uses PostgreSQL accounting/}' "$RADDB/sites-available/default"; \
    \
    # HotFii does not use EAP inner-tunnel, so treat it as best effort.
    if [ -f "$RADDB/sites-available/inner-tunnel" ]; then \
        sed -i 's/^#\([[:space:]]*sql\)$/\1/' "$RADDB/sites-available/inner-tunnel"; \
    fi; \
    \
    chown -R freerad:freerad "$RADDB"

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["hotfii-radius-entrypoint"]
