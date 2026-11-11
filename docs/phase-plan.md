# Build phases

1. **Static interface:** HTML/CSS/PHP page with sample JSON snapshot.
2. **VM preparation:** confirm OS release, Host-only IPs, resources, and VM-to-VM connectivity.
3. **Node Exporter:** install on both VMs and verify the `/metrics` endpoint from VM-01.
4. **Prometheus:** configure scrape targets and verify both targets are UP.
5. **Grafana:** provision Prometheus datasource and import the supplied Linux overview dashboard.
6. **Bash collection:** deploy the read-only collector and validate its JSON output on each host.
7. **Dashboard integration:** run the orchestrator from VM-01 and render current snapshots in PHP.
8. **Incident rules:** tune CPU, memory, filesystem and failed-service thresholds; test warning/critical states.
9. **Scheduling:** enable cron or the supplied systemd timer after manual collection is reliable.
10. **Failure testing:** safely stop a test service, simulate load and fill a disposable test filesystem; verify expected alerts.
11. **GitHub/documentation:** add screenshots, architecture, installation and troubleshooting notes.
12. **Later expansion:** learn Ansible and use it to deploy/execute checks across more hosts; Azure is optional.
