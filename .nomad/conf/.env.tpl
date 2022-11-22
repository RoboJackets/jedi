{{- range $key, $value := (key "loop/shared" | parseJSON) -}}
{{- $key | trimSpace -}}={{- $value | toJSON }}
{{ end -}}
{{- range service "mysql" }}
DB_SOCKET="{{- index .ServiceMeta "socket" | trimSpace -}}"
{{ end }}
{{- range service "redis" }}
REDIS_PATH="{{- index .ServiceMeta "socket" | trimSpace -}}"
{{ end }}
REDIS_PASSWORD="{{- key "redis/password" | trimSpace -}}"
{{ range $key, $value := (key "jedi" | parseJSON) -}}
{{- $key | trimSpace -}}={{- $value | toJSON }}
{{ end -}}
APP_ENV="production"
APP_URL="https://{{- with (key "nginx/hostnames" | parseJSON) -}}{{- index . (env "NOMAD_JOB_NAME") -}}{{- end -}}"
CAS_CLIENT_SERVICE="https://{{- with (key "nginx/hostnames" | parseJSON) -}}{{- index . (env "NOMAD_JOB_NAME") -}}{{- end -}}"
HOME="/secrets/"
