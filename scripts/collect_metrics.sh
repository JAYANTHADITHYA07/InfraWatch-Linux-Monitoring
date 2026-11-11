#!/usr/bin/env bash
# Collect a point-in-time Linux health snapshot and print JSON to stdout.
set -u

json_escape() {
  local s=${1-}
  s=${s//\\/\\\\}
  s=${s//\"/\\\"}
  s=${s//$'\n'/ }
  s=${s//$'\r'/ }
  printf '%s' "$s"
}

read_cpu() {
  awk '/^cpu / { idle=$5; total=0; for (i=2;i<=NF;i++) total+=$i; print total, idle; exit }' /proc/stat
}

read -r total1 idle1 < <(read_cpu)
sleep 1
read -r total2 idle2 < <(read_cpu)
if (( total2 > total1 )); then
  cpu=$(awk -v t1="$total1" -v t2="$total2" -v i1="$idle1" -v i2="$idle2" 'BEGIN { printf "%.1f", 100*(1-(i2-i1)/(t2-t1)) }')
else
  cpu=0
fi

mem_total=$(awk '/^MemTotal:/ {print $2}' /proc/meminfo)
mem_avail=$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)
mem_used=$(awk -v t="$mem_total" -v a="$mem_avail" 'BEGIN { if(t>0) printf "%.1f", 100*(t-a)/t; else print 0 }')

read -r disk_total disk_used disk_pct < <(df -P / | awk 'NR==2 {gsub(/%/,"",$5); print $2, $3, $5}')
read -r var_total var_used var_pct < <(df -P /var 2>/dev/null | awk 'NR==2 {gsub(/%/,"",$5); print $2, $3, $5}')
[ -n "${var_pct:-}" ] || { var_total=0; var_used=0; var_pct=0; }

load1=$(awk '{print $1}' /proc/loadavg)
up_seconds=$(awk '{printf "%.0f", $1}' /proc/uptime)
up_days=$(awk -v s="$up_seconds" 'BEGIN {printf "%.1f", s/86400}')
host=$(hostname -f 2>/dev/null || hostname)
os=$( . /etc/os-release 2>/dev/null; printf '%s' "${PRETTY_NAME:-Linux}" )
kernel=$(uname -r)
ips=$(hostname -I 2>/dev/null | xargs || true)
failed_services=$(systemctl --failed --no-legend --plain 2>/dev/null | awk 'NF {n++} END {print n+0}')
failed_names=$(systemctl --failed --no-legend --plain 2>/dev/null | awk '{print $1}' | paste -sd ',' -)

# Determine overall status using simple configurable defaults.
status=NORMAL
reason="All configured checks are within thresholds"
if (( disk_pct >= 90 || var_pct >= 90 || failed_services > 0 )) || awk -v c="$cpu" -v m="$mem_used" 'BEGIN{exit !(c>=90 || m>=90)}'; then
  status=CRITICAL; reason="Critical resource threshold or failed service detected"
elif (( disk_pct >= 85 || var_pct >= 85 )) || awk -v c="$cpu" -v m="$mem_used" 'BEGIN{exit !(c>=80 || m>=80)}'; then
  status=WARNING; reason="One or more resource thresholds exceeded"
fi

printf '{\n'
printf '  "hostname": "%s",\n' "$(json_escape "$host")"
printf '  "os": "%s",\n' "$(json_escape "$os")"
printf '  "kernel": "%s",\n' "$(json_escape "$kernel")"
printf '  "ips": "%s",\n' "$(json_escape "$ips")"
printf '  "cpu_percent": %s,\n' "$cpu"
printf '  "memory_percent": %s,\n' "$mem_used"
printf '  "disk_root_percent": %s,\n' "${disk_pct:-0}"
printf '  "disk_var_percent": %s,\n' "${var_pct:-0}"
printf '  "load_1m": %s,\n' "$load1"
printf '  "uptime_days": %s,\n' "$up_days"
printf '  "failed_services": %s,\n' "$failed_services"
printf '  "failed_service_names": "%s",\n' "$(json_escape "${failed_names:-}")"
printf '  "status": "%s",\n' "$status"
printf '  "status_reason": "%s",\n' "$(json_escape "$reason")"
printf '  "collected_at": "%s"\n' "$(date -Is)"
printf '}\n'
