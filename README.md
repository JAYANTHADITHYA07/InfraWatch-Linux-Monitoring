# InfraWatch – Linux Infrastructure Monitoring Dashboard

InfraWatch is a two-VM Linux infrastructure monitoring project built using Bash, PHP, HTML, CSS, Prometheus, Node Exporter and Grafana.

It collects Linux system metrics, monitors server availability, identifies infrastructure incidents and presents monitoring information through a custom dashboard and Grafana.

## 1. Project Architecture

The project uses two Rocky Linux virtual machines hosted in Oracle VirtualBox.

| Component | VM-01 | VM-02 |
|---|---|---|
| Operating System | Rocky Linux 10.1 | Rocky Linux 10.1 |
| Role | Monitoring server | Target server |
| Node Exporter | Yes | Yes |
| Prometheus | Yes | No |
| Grafana | Yes | No |
| Alertmanager | Yes | No |
| Bash metric collector | Yes | Yes |
| Incident detection | Yes | No |

### Monitoring architecture

- **VM-01:** Runs Prometheus, Grafana, Alertmanager, Node Exporter and automated incident detection.
- **VM-02:** Provides system metrics through Node Exporter and the Bash collector.
- **WSL Ubuntu:** Runs the PHP dashboard and coordinates remote metric collection over SSH.
- **Prometheus:** Scrapes Node Exporter metrics every 15 seconds.
- **Grafana:** Displays historical infrastructure metrics and monitoring dashboards.
- **Custom PHP dashboard:** Displays server inventory, current status, resource utilization and incident summaries.

## 2. Technologies Used

| Technology | Purpose |
|---|---|
| Rocky Linux | Server operating system |
| Bash Shell Scripting | Metric collection and automation |
| SSH | Secure remote server communication |
| PHP | Custom monitoring dashboard |
| HTML/CSS | Dashboard interface |
| Prometheus | Metrics collection |
| Node Exporter | Linux system metrics |
| Grafana | Metrics visualization |
| Alertmanager | Alert management |
| Nginx | Reverse proxy |
| Oracle VirtualBox | Virtual machine environment |
| WSL Ubuntu | Local development and dashboard hosting |

The project deliberately avoids Python, SQL databases, JavaScript frameworks and Docker.

## 3. Repository Structure

```text
linux-infrastructure-monitor/
│
├── config/
│   ├── hosts.conf.example
│   ├── prometheus/
│   │   └── prometheus.yml
│   └── grafana/
│       └── dashboards/
│           └── linux-infrastructure.json
│
├── dashboard/
│   ├── index.php
│   └── style.css
│
├── data/
│   └── .gitkeep
│
├── docs/
│   ├── network-checklist.md
│   └── phase-plan.md
│
├── scripts/
│   ├── collect_all.sh
│   └── collect_metrics.sh
│
├── systemd/
│   ├── infra-collector.service
│   └── infra-collector.timer
│
├── sample-data/
│   └── monitor01.json
│
├── .gitignore
└── README.md
```

Note: Live JSON snapshots and local SSH configuration are excluded from Git using `.gitignore`.

## 4. Server Configuration

Example lab configuration:

| Server | Host-only IP | SSH User |
|---|---|---|
| VM-01 | 192.168.56.20 | jayanthadithya |
| VM-02 | 192.168.56.102 | joyboy |

These are the current lab addresses. Different environments may require different IP addresses and usernames.

## 5. SSH Configuration

The WSL Ubuntu environment communicates with both virtual machines using SSH key authentication.

Example:

```bash
ssh jayanthadithya@192.168.56.20
ssh joyboy@192.168.56.102
```

The project uses `config/hosts.conf` to define remote collection targets.

Example format:

```text
LABEL|SSH_USER@HOST|REMOTE_COLLECTOR_PATH
```

Actual lab configuration:

```text
VM-01|jayanthadithya@192.168.56.20|/home/jayanthadithya/infrawatch-collector/collect_metrics.sh

VM-02|joyboy@192.168.56.102|/home/joyboy/infrawatch-collector/collect_metrics.sh
```

Create this file from the example template:

```bash
cp config/hosts.conf.example config/hosts.conf
```

Edit it with your actual SSH usernames, IP addresses and collector paths.

## 6. Metric Collection

The Bash collector gathers information from each Linux server.

Collected metrics include:

- Hostname
- Operating system
- Kernel version
- IP addresses
- CPU utilization
- Memory utilization
- Root filesystem utilization
- `/var` filesystem utilization
- System load
- Uptime
- Failed services
- Overall server health status

Run the collection script from the WSL project directory:

```bash
./scripts/collect_all.sh
```

The script connects to both VMs over SSH and generates JSON snapshots.

Expected output:

```text
Collecting metrics from VM-01...
SUCCESS: VM-01 data collected.

Collecting metrics from VM-02...
SUCCESS: VM-02 data collected.

-----------------------------------
InfraWatch collection completed.
```

Check the generated files:

```bash
ls -lh data/
```

Validate JSON:

```bash
python3 -m json.tool data/VM-01.json
python3 -m json.tool data/VM-02.json
```

Metric collection is currently triggered manually from WSL.

## 7. PHP Dashboard

The custom dashboard reads JSON snapshots from the `data/` directory.

It provides:

- Infrastructure overview
- Server inventory
- Server health status
- CPU and memory utilization
- Disk utilization
- Current incident summaries
- Embedded Grafana monitoring

The dashboard is hosted using PHP's built-in development server.

From the project directory:

```bash
php -S 127.0.0.1:8080 -t dashboard
```

Keep the PHP server running while using the dashboard.

## 8. Nginx Reverse Proxy Integration

WSL Nginx provides a common browser entry point for the PHP dashboard and Grafana.

Architecture:

```text
             Browser
                |
                |
       http://localhost:8081
                |
                |
           WSL Nginx
                |
          +-----+------+
          |            |
          |            |
        /              /grafana/
          |            |
          v            v
    PHP Server      VM-01 Grafana
    127.0.0.1:8080  192.168.56.20:3000
```

The reverse proxy allows Grafana to be embedded in the custom dashboard under the same browser origin.

Dashboard address:

```text
http://localhost:8081
```

Grafana is accessed through:

```text
http://localhost:8081/grafana/
```

## 9. Prometheus and Grafana

Prometheus collects metrics from Node Exporter on both virtual machines.

Example scrape configuration:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: prometheus
    static_configs:
      - targets:
          - localhost:9090

  - job_name: linux-servers
    static_configs:
      - targets:
          - 192.168.56.20:9100
        labels:
          instance_name: VM-01

      - targets:
          - 192.168.56.102:9100
        labels:
          instance_name: VM-02
```

Grafana visualizes:

- Server availability
- CPU utilization
- Memory utilization
- Disk utilization
- Network activity
- System load
- Uptime
- Disk I/O
- Failed services
- Active alerts

The custom PHP dashboard and Grafana have different responsibilities:

- PHP displays the latest collected server snapshots.
- Grafana displays time-series metrics collected by Prometheus.

## 10. Automated Incident Detection

InfraWatch includes a separate Bash-based incident detection workflow running on VM-01.

It checks infrastructure conditions and manages incident information.

The incident detection process is configured through a systemd service and timer.

Check timer status:

```bash
sudo systemctl status infrawatch-incident.timer
```

Check service status:

```bash
sudo systemctl status infrawatch-incident.service
```

View timer schedules:

```bash
sudo systemctl list-timers --all
```

The incident detection timer runs every two minutes.

## 11. Alerting

Prometheus alert rules identify infrastructure conditions such as:

- High CPU utilization
- High memory utilization
- High disk utilization
- Server availability failures
- Reboot detection
- Critical resource utilization

Alertmanager handles firing and resolved alerts and forwards configured notifications through email.

## 12. Security Considerations

- SSH communication uses key-based authentication.
- Node Exporter ports should not be exposed to public networks.
- Firewall rules should restrict monitoring access to trusted lab hosts.
- Avoid committing private SSH keys or local configuration files.
- The Bash collector is designed for read-only metric collection.

## 13. Project Limitations

InfraWatch is a learning and lab project, not a production monitoring platform.

- Metric snapshots are point-in-time measurements.
- Historical metric visualization is handled by Prometheus and Grafana.
- Metric collection currently requires manual execution from WSL.
- The lab depends on both virtual machines being powered on and reachable.
- The PHP built-in server is intended for development and lab use.

## 14. Future Improvements

Potential enhancements include:

- Scheduled remote metric collection
- Improved incident lifecycle management
- Additional infrastructure health checks
- Enhanced dashboard reporting
- Deployment documentation
- GitHub project documentation and screenshots

---

**Project:** InfraWatch – Linux Infrastructure Monitoring Dashboard

**Environment:** WSL Ubuntu + Oracle VirtualBox + Rocky Linux

**Primary Focus:** Linux Administration, Shell Scripting, Infrastructure Monitoring and Automation
