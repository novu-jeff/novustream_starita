$TTL 86400
@   IN  SOA ns1.staritawaterdistrictpamp.gov.ph. admin.staritawaterdistrictpamp.gov.ph. (
        2026080201 ; Serial (CHANGE on edits)
        3600       ; Refresh
        1800       ; Retry
        604800     ; Expire
        86400 )    ; Minimum

; Nameservers
@       IN  NS  ns1.staritawaterdistrictpamp.gov.ph.
@       IN  NS  ns2.staritawaterdistrictpamp.gov.ph.

; Glue / NS A records
ns1     IN  A   38.226.41.4
ns2     IN  A   38.226.41.4

; Website (apex)
@       IN  A   38.226.41.4
www     IN  A   38.226.41.4

; Novustream subdomains
portal  IN  A   38.226.41.4
admin   IN  A   38.226.41.4
