# VirtualBox network checklist

The screenshots show Adapter 1 = NAT and Adapter 2 = Host-only on both VMs.

1. Boot both VMs.
2. On each VM, run `ip -4 addr` and identify the address on the Host-only interface (often `enp0s8`, but interface names vary).
3. Confirm the two Host-only addresses belong to the same subnet.
4. From VM-01, ping VM-02's Host-only IP.
5. Test SSH from VM-01 to VM-02.
6. Later, test TCP 9100 from VM-01 to VM-02 for Node Exporter.

NAT is for outbound internet access. Host-only is for private VM-to-VM and host-to-VM lab communication. Do not use the NAT address as the Prometheus target unless you have deliberately configured port forwarding.
