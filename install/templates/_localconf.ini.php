;<?php exit(); __halt_compiler(); // security + highlighting fix ?>

; All string values should be quoted for security reasons!

[DB]
host     = "localhost"
username = ""
password = ""
database = ""

; Optional
;default_engine = "InnoDB"

; Optional
;default_charset = "utf8"

; Optional
;default_collation = "utf8_general_ci"

[date]
timezone = "Europe/Vienna"

; Optional
;[filestore]
;path = "upload"

; Optional
;[record]
;cache = "file"

[authenticator]
ACMtranetFacebook = "stlocal/ext/mtranet/class.ACMtranetFacebook.php"
ACMtranetTwitter = "stlocal/ext/mtranet/class.ACMtranetTwitter.php"
ACMtranetOnSite = "stlocal/ext/mtranet/class.ACMtranetOnSite.php"

[backend]
languages = "en/de"
default_theme = "dijit-claro"

[web]
cache = "file"

;allow usage of qt, qc, tt, qbt query parameters
;enableDebugParameter = true

;always redirect to HTTPS, except if disabled in backend, for domains matching regex
;preferHTTPS = "/^([^\.]+\.)?example\.com$/"

;domains for which to send HSTS header on HTTPS - these will also be preferred to be https automatically, so don't need to be included in preferHTTPS again!
;domainsHSTS[] = "*.example.com"

;optional ; possible values: "scssc", "sass", false
;scss_cli = "scssc"

;should only be used for development servers, as serving content intended for https without ssl is securitywise problematic
;disableHTTPS = true

; Optional
;[gfx]
;imagick_cli = true
;    disables fallback to GD for text rendering on osx with specific imagick version
;    this can be used for unittesting
;unfix_osx = true

; Optional
;[classfinder]
;cache = "file"

; Optional
;[mode]
;    possible values: "devlopment" "production"
;    defaults to: "production"
;installation = "development"

; Optional
;[ElasticSearch]
;server[] = "some-random.server.at:9200"

; Optional
;[email]
;systemFromAddress = "no-reply@www.no-reply.com"
