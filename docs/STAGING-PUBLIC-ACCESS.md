# Expose staging ra Internet qua public IP (2026-07-28)

> Bối cảnh: máy staging Ubuntu (xem `docs/STAGING.md`) chỉ mới truy cập được
> qua LAN (`192.168.1.5:8100`) hoặc Tailscale (`100.99.170.2:8100`). Tài liệu
> này ghi lại các bước để bất kỳ ai có link cũng xem được qua public IP nhà
> mạng, **không cần cài Tailscale**.

## Kiến trúc

```
Internet ──> Router (public IP, port forward) ──> 192.168.1.5:8100 (Ubuntu staging)
                                                          │
                                                    docker-compose (infun-web nginx)
```

Chỉ **1 port duy nhất** (`8100`, web) được phép lộ ra Internet. Mọi port nội bộ
khác do container publish (MySQL `3307/3311/3312`, ProxySQL `6032-6034`,
Meilisearch `7701`, Mailpit `8026`) **tuyệt đối không forward** — chỉ truy cập
được qua LAN/Tailscale.

## 1. Lấy public IP hiện tại

```bash
curl -s -4 ifconfig.me
```

IP này **có thể đổi** nếu ISP không cấp cố định (phổ biến ở VN) — mỗi lần đổi
phải lặp lại bước 5 (`STAGING_APP_URL`) + bước 6 (router).

## 2. Firewall tầng OS — `ufw` (chặn INPUT)

`ufw` quản lý chain `INPUT` (traffic đích tới chính host) — áp dụng cho SSH.
**Không đủ để chặn port do Docker publish** (xem mục 3).

```bash
sudo apt-get install -y ufw
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH: chỉ LAN + Tailscale, không mở ra Internet
sudo ufw allow from 192.168.1.0/24 to any port 22 proto tcp
sudo ufw allow in on tailscale0 to any port 22 proto tcp

# Web staging: port DUY NHẤT được public
sudo ufw allow 8100/tcp

sudo ufw --force enable
```

## 3. ⚠️ Gotcha bắt buộc đọc — Docker bỏ qua hoàn toàn `ufw`

**Docker tự chèn iptables rule riêng (chain `DOCKER`/`DOCKER-FORWARD`) xử lý
traffic tới container qua chain `FORWARD`, không đi qua `INPUT` mà `ufw` quản
lý.** Hệ quả: dù `ufw status` báo "deny", mọi port container publish
(`3307`, `7701`, `8026`...) **vẫn mở toang ra ngoài** — `ufw allow`/`deny`
hoàn toàn vô nghĩa với chúng.

Cách khắc phục đúng: Docker có sẵn chain **`DOCKER-USER`** — chain rỗng dành
riêng để user chèn rule của mình, được evaluate **TRƯỚC** mọi rule khác của
Docker trong `FORWARD`. Đây là nơi DUY NHẤT filter được traffic vào container
theo source/dest mong muốn.

### 3a. Script rule (`/usr/local/bin/docker-user-firewall.sh`)

```bash
#!/usr/bin/env bash
set -euo pipefail

# Idempotent: xóa rule cũ trước khi thêm lại (service chạy lại mỗi khi
# docker.service start).
delete_if_exists() {
  while iptables -C DOCKER-USER "$@" 2>/dev/null; do
    iptables -D DOCKER-USER "$@"
  done
}

delete_if_exists -m conntrack --ctstate RELATED,ESTABLISHED -j RETURN
delete_if_exists -s 192.168.1.0/24 -j RETURN
delete_if_exists -s 100.64.0.0/10 -j RETURN
delete_if_exists -p tcp --dport 8100 -j RETURN
delete_if_exists -j DROP

# Chèn theo thứ tự NGƯỢC với thứ tự mong muốn (mỗi lần -I chèn lên đầu chain).
# Kết quả cuối cùng (trên -> dưới):
#   1. ESTABLISHED/RELATED RETURN — BẮT BUỘC đứng đầu. Gói tin CHIỀU TRẢ LỜI
#      (container -> client) có source là IP nội bộ container (172.18.x.x) và
#      dest port ngẫu nhiên (ephemeral port của client) — KHÔNG khớp bất kỳ
#      rule source/port nào bên dưới. Thiếu rule này: request đi vào được
#      (match rule LAN/port) nhưng response bị DROP ở rule cuối -> connection
#      timeout dù log tưởng như "đã cho phép". Bug này tốn nhiều vòng debug
#      thực tế mới lộ ra — xem lịch sử session dựng máy 2026-07-28.
#   2. LAN RETURN, 3. Tailscale RETURN, 4. port 8100 RETURN, 5. DROP còn lại.
iptables -I DOCKER-USER -j DROP
iptables -I DOCKER-USER -p tcp --dport 8100 -j RETURN
iptables -I DOCKER-USER -s 100.64.0.0/10 -j RETURN
iptables -I DOCKER-USER -s 192.168.1.0/24 -j RETURN
iptables -I DOCKER-USER -m conntrack --ctstate RELATED,ESTABLISHED -j RETURN
```

### 3b. Systemd unit — persist rule qua reboot

iptables rule **không tự tồn tại sau reboot** trừ khi có cơ chế khôi phục lại.
Vì Docker tự tạo lại chain `DOCKER-USER` (rỗng) mỗi khi `dockerd` khởi động,
cần 1 service chạy SAU `docker.service` để nạp lại rule:

```ini
# /etc/systemd/system/docker-user-firewall.service
[Unit]
Description=Restrict Docker-published ports to LAN/Tailscale, except 8100 (public web)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
ExecStart=/usr/local/bin/docker-user-firewall.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
```

Cài đặt (chạy 1 lần, cần sudo — dùng terminal thật, `sudo` qua SSH không-TTY
sẽ báo lỗi "a terminal is required to authenticate"):

```bash
sudo cp docker-user-firewall.sh /usr/local/bin/docker-user-firewall.sh
sudo chmod +x /usr/local/bin/docker-user-firewall.sh
sudo cp docker-user-firewall.service /etc/systemd/system/docker-user-firewall.service
sudo systemctl daemon-reload
sudo systemctl enable --now docker-user-firewall.service
```

**Mỗi lần sửa script**: phải `cp` đè lại vào `/usr/local/bin/` RỒI mới
`systemctl restart docker-user-firewall.service` — sửa file gốc trong home
KHÔNG tự động áp dụng (service chạy bản ở `/usr/local/bin`, không symlink).

### 3c. Verify (không cần sudo để đọc, nhưng cần sudo để dump lần đầu)

```bash
# Ghi ra file trước (1 lệnh sudo duy nhất — KHÔNG pipe 2 lệnh sudo qua nhau,
# vì stdin của sudo thứ 2 bị pipe chiếm mất, không đọc được password):
sudo sh -c 'iptables -L DOCKER-USER -n --line-numbers > /home/an-my/docker-user-chain.txt'
cat /home/an-my/docker-user-chain.txt
```

Kỳ vọng đúng 5 rule theo thứ tự: `ESTABLISHED,RELATED RETURN` → `LAN RETURN`
→ `Tailscale RETURN` → `dport 8100 RETURN` → `DROP`.

Test thực tế từ máy khác cùng LAN (thay `192.168.1.5` bằng IP thật):

```bash
curl -s -o /dev/null -w 'HTTP %{http_code}\n' http://192.168.1.5:8100/   # phải 200
timeout 5 bash -c "echo > /dev/tcp/192.168.1.5/3307" && echo MO || echo CHAN  # LAN -> phải MO
```

## 4. `.env` — `STAGING_APP_URL` phải khớp domain/IP thật truy cập

Toàn bộ base URL của app (thẻ `<base>`, `publicUrl()`, mail, sitemap, queue)
lấy từ `config('app.url')` = `APP_URL` (xem `docs/CLAUDE.md` mục "Base URL
trên staging"). Sai giá trị này = asset/link sai host.

```bash
# /home/an-my/infun/.env (docker compose tự đọc file này để interpolate ${...})
STAGING_APP_URL=http://<PUBLIC_IP>:8100
```

⚠️ **Hairpin NAT**: sau khi trỏ `APP_URL` sang public IP, thiết bị TRONG cùng
LAN truy cập lại chính public IP đó có thể KHÔNG vào được nếu router không hỗ
trợ "NAT loopback/hairpin" (nhiều router gia dụng không hỗ trợ). Test từ
trong LAN vẫn nên dùng `http://192.168.1.5:8100` trực tiếp; public IP chỉ để
test từ ngoài (di động tắt WiFi, dùng 4G).

Áp dụng: recreate container để nạp env mới (entrypoint tự `config:cache` lại
theo ENV hiện tại):

```bash
cd /home/an-my/infun
docker compose -f docker-compose.staging.yml up -d --force-recreate infun-php infun-web infun-queue infun-scheduler
```

## 5. Router — port forward

1. Đăng nhập trang quản trị router (thường `http://192.168.1.1`).
2. Mục **Port Forwarding / Virtual Server / NAT** (tên khác nhau tùy hãng).
3. Thêm rule: **External port `8100` → Internal IP `192.168.1.5`, Internal
   port `8100`, protocol TCP.**
4. Lưu lại.

## 6. Test từ ngoài mạng

Tắt WiFi trên điện thoại, dùng 4G/5G, truy cập `http://<PUBLIC_IP>:8100`.

**Không vào được dù đã forward đúng** → khả năng cao ISP dùng **CGNAT**
(carrier-grade NAT) — public IP thấy qua `ifconfig.me` không thực sự route
trực tiếp về router nhà bạn (IP đó bị share giữa nhiều khách hàng ở tầng
ISP). Port-forward trên router nhà là vô nghĩa trong trường hợp này —
phương án thay thế: **Cloudflare Tunnel** hoặc **ngrok** (reverse tunnel,
không cần mở port, tự có domain + HTTPS).

## Checklist khi public IP đổi (ISP không cấp IP tĩnh)

1. `curl -s -4 ifconfig.me` lấy IP mới.
2. Sửa rule port-forward trên router (nếu router yêu cầu IP cụ thể — thường
   port-forward theo LAN IP nội bộ nên bước này thường KHÔNG cần đổi, chỉ
   IP nội bộ 192.168.1.5 mới quan trọng ở đây).
3. Sửa `STAGING_APP_URL` trong `/home/an-my/infun/.env`.
4. `docker compose -f docker-compose.staging.yml up -d --force-recreate infun-php infun-web infun-queue infun-scheduler`.

Rule `ufw` + `DOCKER-USER` (mục 2-3) **không cần sửa lại** khi IP đổi — chúng
lọc theo dải LAN/Tailscale cố định + port cố định, không phụ thuộc public IP.
