{{- range $key, $value := (key "loop/shared" | parseJSON) -}}
{{- $key | trimSpace -}}={{- $value | toJSON }}
{{ end -}}
{{- range service "mysql" }}
DB_SOCKET="{{- index .ServiceMeta "socket" | trimSpace -}}"
{{ end }}
{{ range $key, $value := (key "jedi" | parseJSON) -}}
{{- $key | trimSpace -}}={{- $value | toJSON }}
{{ end -}}
APP_ENV="production"
APP_URL="https://{{- with (key "nginx/hostnames" | parseJSON) -}}{{- index . (env "NOMAD_JOB_NAME") -}}{{- end -}}"
CAS_CLIENT_SERVICE="https://{{- with (key "nginx/hostnames" | parseJSON) -}}{{- index . (env "NOMAD_JOB_NAME") -}}{{- end -}}"
CAS_VALIDATION="ca"
CAS_CERT="/etc/ssl/certs/USERTrust_RSA_Certification_Authority.pem"
HOME="/secrets/"
REDIS_CLIENT="phpredis"
REDIS_SCHEME="null"
REDIS_PORT="-1"
REDIS_HOST="/alloc/tmp/redis.sock"
REDIS_PASSWORD="{{ env "NOMAD_ALLOC_ID" }}"
REDIS_DB=0
REDIS_CACHE_DB=1
